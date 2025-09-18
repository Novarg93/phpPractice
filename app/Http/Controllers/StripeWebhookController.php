<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Services\Cart\CartTools;
use Stripe\Webhook;
use App\Events\OrderWorkflowUpdated;
use App\Models\OrderItem;
use App\Models\Refund;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('Stripe-Signature', '');
        $secret    = config('services.stripe.webhook_secret');
        $payload   = $request->getContent();

        // Валидируем подпись и строим объект события
        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature failed', ['message' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        // Достаём тип и объект
        $type = $event->type ?? '';
        $obj  = $event->data->object ?? null;

        Log::info('Stripe webhook received', [
            'type' => $type,
            'id'   => $event->id ?? null,
        ]);

        // Роутим по типу события
        try {
            switch ($type) {
                case 'checkout.session.completed':
                    return $this->handleCheckoutCompleted($obj);

                case 'charge.refunded':
                    return $this->handleChargeRefunded($obj);

                case 'refund.created':
                case 'refund.updated':
                    return $this->handleRefundObject($obj, $type);

                default:
                    return response('ignored', 200);
            }
        } catch (\Throwable $e) {
            Log::error('Stripe webhook error', ['type' => $type, 'ex' => $e]);
            return response('error', 500);
        }
    }

    /**
     * checkout.session.completed → помечаем заказ оплаченным
     * и сохраняем ИМЕННО payment_intent (PI), а не session id.
     */
    private function handleCheckoutCompleted($session)
    {
        if (!$session) return response('ok', 200);

        $sessionId = $session->id ?? null;
        $orderId   = $session->metadata->order_id ?? null;
        $userId    = (int)($session->metadata->user_id ?? 0);
        $piId      = $session->payment_intent ?? null;

        // Ищем заказ по ID из metadata либо по сохранённому checkout_session_id
        $order = $orderId ? Order::find($orderId) : null;
        if (!$order && $sessionId) {
            $order = Order::where('checkout_session_id', $sessionId)->first();
        }
        if (!$order) return response('ok', 200);
        if ($order->status !== Order::STATUS_PENDING) return response('ok', 200);

        DB::transaction(function () use ($order, $piId, $userId) {
            $order->update([
                'status'     => Order::STATUS_PAID,
                'paid_at'    => now(),
                'payment_id' => $piId, // ← сохраняем PI
            ]);

            $order->items()
                ->where('status', OrderItem::STATUS_PENDING)
                ->update(['status' => OrderItem::STATUS_PAID]);

            if ($userId > 0) {
                CartTools::clearUserCart($userId);
            }
        });

        DB::afterCommit(function () use ($order) {
            Log::info('Broadcast OrderWorkflowUpdated (after stripe paid)', ['order_id' => $order->id]);
            
        });

        Log::info('Stripe checkout.session.completed processed', [
            'order_id' => $order->id,
            'payment_intent' => $piId,
        ]);

        return response('ok', 200);
    }

    /**
     * charge.refunded содержит массив refunds (полезно при частичных/мульти-рефандах)
     */
    private function handleChargeRefunded($charge)
    {
        if (!$charge) return response('ok', 200);

        Log::info('Stripe charge.refunded received', [
            'charge_id' => $charge->id ?? null,
            'payment_intent' => $charge->payment_intent ?? null,
        ]);

        $piId = $charge->payment_intent ?? null;
        if (!$piId) return response('ok', 200);

        $order = Order::where('payment_id', $piId)->first();
        if (!$order) {
            Log::warning('Stripe charge.refunded: order not found by PI', ['payment_intent' => $piId]);
            return response('ok', 200);
        }

        DB::transaction(function () use ($order, $charge) {
            // 1) Апсертим конкретные Refund-записи из charge.refunds.data
            $refunds = $charge->refunds->data ?? [];
            foreach ($refunds as $r) {
                /** @var \Stripe\Refund $r */
                $ref = Refund::firstOrNew(['provider_id' => (string) $r->id]);
                $ref->order_id         = $order->id;
                $ref->amount_cents     = (int) ($r->amount ?? 0);
                $ref->status           = (string) ($r->status ?? 'succeeded');
                $ref->reason           = $r->reason ?? null;
                $ref->provider         = 'stripe';
                $ref->provider_payload = is_array($r) ? $r : $r->toArray();
                $ref->event_type       = 'charge.refunded'; // 👈 вот здесь ставим тип события
                $ref->save();
            }

            // 2) Пересчитываем агрегаты заказа с кэпом
            $sumDb      = (int) $order->refunds()->sum('amount_cents');
            $totalCents = (int) $order->total_cents;
            $capped     = min($sumDb, $totalCents);

            $order->total_refunded_cents = $capped;

            $fullRefund = $capped >= $totalCents;
            if ($fullRefund) {
                $wasFullyRefunded = $order->status === Order::STATUS_REFUND;

                $order->status      = Order::STATUS_REFUND;
                $order->refunded_at = $order->refunded_at ?? now();
                $order->save();

                if (!$wasFullyRefunded) {
                    $order->items()
                        ->where('status', '!=', \App\Models\OrderItem::STATUS_REFUND)
                        ->update([
                            'status'       => \App\Models\OrderItem::STATUS_REFUND,
                            'cost_cents'   => null,
                            'profit_cents' => null,
                            'margin_bp'    => null,
                        ]);
                }
            } else {
                $order->status = Order::STATUS_PARTIAL_REFUND;
                $order->save();
            }
        });

        Log::info('Stripe charge.refunded aggregated', [
            'order_id' => $order->id,
            'total_refunded_cents' => $order->total_refunded_cents,
            'order_status' => $order->status,
        ]);

        
        return response('ok', 200);
    }

    /**
     * Подстраховка на случай отдельных событий refund.created|updated
     */
    private function handleRefundObject($refund, ?string $eventType = null)
    {
        if (!$refund) return response('ok', 200);

        Log::info('Stripe refund object received', [
            'refund_id' => $refund->id ?? null,
            'payment_intent' => $refund->payment_intent ?? null,
            'status' => $refund->status ?? null,
            'amount' => $refund->amount ?? null,
        ]);

        $piId = $refund->payment_intent ?? null;
        if (!$piId) return response('ok', 200);

        $order = Order::where('payment_id', $piId)->first();
        if (!$order) {
            Log::warning('Stripe refund.*: order not found by PI', ['payment_intent' => $piId]);
            return response('ok', 200);
        }

        DB::transaction(function () use ($order, $refund, $eventType) {
            // 1) Сохраняем/обновляем конкретный Refund
            $ref = Refund::firstOrNew(['provider_id' => (string) $refund->id]);
            $ref->order_id         = $order->id;
            $ref->amount_cents     = (int) ($refund->amount ?? 0);
            $ref->status           = (string) ($refund->status ?? 'succeeded');
            $ref->reason           = $refund->reason ?? null;
            $ref->provider         = 'stripe';
            $ref->provider_payload = is_array($refund) ? $refund : $refund->toArray();
            $ref->event_type       = $eventType;
            $ref->save();

            // 2) Пересчёт агрегатов с кэпом
            $sumDb      = (int) $order->refunds()->sum('amount_cents');
            $totalCents = (int) $order->total_cents;
            $capped     = min($sumDb, $totalCents);

            $order->total_refunded_cents = $capped;

            $fullRefund = $capped >= $totalCents;
            if ($fullRefund) {
                $wasFullyRefunded = $order->status === Order::STATUS_REFUND;

                $order->status = Order::STATUS_REFUND;
                $order->refunded_at = $order->refunded_at ?? now();
                $order->save();

                if (!$wasFullyRefunded) {
                    $order->items()
                        ->where('status', '!=', \App\Models\OrderItem::STATUS_REFUND)
                        ->update([
                            'status'       => \App\Models\OrderItem::STATUS_REFUND,
                            'cost_cents'   => null,
                            'profit_cents' => null,
                            'margin_bp'    => null,
                        ]);
                }
            } else {
                $order->status = Order::STATUS_PARTIAL_REFUND;
                $order->save();
            }
        });

        Log::info('Stripe refund.* applied to order', [
            'order_id' => $order->id,
            'total_refunded_cents' => $order->total_refunded_cents,
            'order_status' => $order->status,
        ]);

        
        return response('ok', 200);
    }
}
