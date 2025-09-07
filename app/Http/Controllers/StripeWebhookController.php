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



class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('Stripe-Signature', '');
        $secret    = config('services.stripe.webhook_secret');
        $payload   = $request->getContent();

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature failed', ['message' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        $type = $event->type ?? '';
        $obj  = $event->data->object ?? null; // 👈 объект SDK

        try {
            if ($type === 'checkout.session.completed' && $obj) {
                $sessionId = $obj->id ?? null;
                $orderId   = $obj->metadata->order_id ?? null;
                $userId    = (int)($obj->metadata->user_id ?? 0);
                $paymentId = $obj->payment_intent ?? $sessionId;

                // Ищем заказ: сначала по переданному ID, затем по session_id
                $order = $orderId ? Order::find($orderId) : null;
                if (!$order && $sessionId) {
                    $order = Order::where('checkout_session_id', $sessionId)->first();
                }

                if ($order && $order->status === Order::STATUS_PENDING) {
                    DB::transaction(function () use ($order, $paymentId, $userId) {
                        $order->update([
                            'status'     => Order::STATUS_PAID,
                            'paid_at'    => now(),
                            'payment_id' => $paymentId,
                        ]);

                        // 👇 ДОБАВЬ ЭТО: промоутим pending → paid
                        $order->items()
                            ->where('status', OrderItem::STATUS_PENDING)
                            ->update(['status' => OrderItem::STATUS_PAID]);

                        if ($userId > 0) {
                            CartTools::clearUserCart($userId);
                        }
                    });

                    DB::afterCommit(function () use ($order) {
                        Log::info('Broadcast OrderWorkflowUpdated (after stripe paid)', ['order_id' => $order->id]);
                        event(new \App\Events\OrderWorkflowUpdated($order->id));
                    }); // 👈 realtime
                }

                // Идемпотентный ответ
                return response('ok', 200);
            }

            // (опционально) можно обрабатывать payment_intent.succeeded/failed и т.п.
            return response('ignored', 200);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook error', ['type' => $type, 'ex' => $e]);
            // 500 → Stripe будет ретраить (это хорошо, если произошла временная ошибка)
            return response('error', 500);
        }
    }
}
