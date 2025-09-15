<?php

namespace App\Services;

use App\Models\{Order, OrderItem, Refund, RefundItem, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\OrderAuditLogger;
use Illuminate\Support\Facades\App;



class RefundService
{
    /**
     * Рефанд одной строки по qty (или по сумме).
     * Если $qty > 0 — считаем сумму пропорционально line_total; если $amountCents > 0 — берём её.
     */
    public function refundItem(OrderItem $item, ?float $qty, ?int $amountCents, ?string $reason, ?User $actor = null): Refund
    {
        $item->load('order');
        $order = $item->order;

        if ($qty !== null) {
            if ($qty <= 0 || $qty > $item->refundableQty()) {
                throw ValidationException::withMessages(['qty' => 'Invalid qty for refund.']);
            }
            $ratio = $qty / (float)$item->qty;
            $amountCents = (int) round($item->line_total_cents * $ratio);
        } else {
            if ($amountCents === null || $amountCents <= 0 || $amountCents > $item->refundableAmountCents()) {
                throw ValidationException::withMessages(['amount' => 'Invalid amount for refund.']);
            }
        }

        if ($amountCents > $order->refundableAmountCents()) {
            throw ValidationException::withMessages(['amount' => 'Exceeds order refundable amount.']);
        }

        return DB::transaction(function () use ($order, $item, $qty, $amountCents, $reason, $actor) {
            /** @var Refund $refund */
            $refund = Refund::create([
                'order_id'     => $order->id,
                'amount_cents' => $amountCents,
                'status'       => 'succeeded', // без провайдера считаем успешным сразу
                'reason'       => $reason,
                'created_by'   => $actor?->id,
            ]);

            App::make(OrderAuditLogger::class)->refundCreated(
                $order,
                $amountCents,
                $actor,
                $reason,
                [
                    'mode'    => $qty !== null ? 'qty' : 'amount',
                    'item_id' => $item->id,
                    'qty'     => $qty,
                ]
            );

            RefundItem::create([
                'refund_id'      => $refund->id,
                'order_item_id'  => $item->id,
                'qty'            => $qty,
                'amount_cents'   => $amountCents,
            ]);

            // обновляем агрегаты на item / order
            $item->refunded_qty          = (float)$item->refunded_qty + (float)($qty ?? 0);
            $item->refunded_amount_cents = (int)$item->refunded_amount_cents + $amountCents;
            $item->status                = \App\Models\OrderItem::STATUS_REFUND;
            $item->save();

            $order->total_refunded_cents = (int)$order->total_refunded_cents + $amountCents;
            $order->refunded_at = $order->refundableAmountCents() === 0 ? now() : $order->refunded_at;
            // если все строки ушли в REFUND → статус заказа REFUND, иначе PARTIAL_REFUND
            $allRefunded = $order->items()->where('status', '!=', \App\Models\OrderItem::STATUS_REFUND)->count() === 0;
            $order->status = $allRefunded ? \App\Models\Order::STATUS_REFUND : \App\Models\Order::STATUS_PARTIAL_REFUND;
            $order->save();

            // можно дернуть твой event/broadcast, если нужно
            event(new \App\Events\OrderWorkflowUpdated($order->id));

            return $refund->load(['items.orderItem', 'order']);
        });
    }

    /**
     * Быстрый рефанд по заказу фиксированной суммой (без распределения по строкам).
     */
    public function refundOrderAmount(Order $order, int $amountCents, ?string $reason, ?User $actor = null): Refund
    {
        if ($amountCents <= 0 || $amountCents > $order->refundableAmountCents()) {
            throw ValidationException::withMessages(['amount' => 'Invalid amount for refund.']);
        }

        return DB::transaction(function () use ($order, $amountCents, $reason, $actor) {
            $refund = Refund::create([
                'order_id'     => $order->id,
                'amount_cents' => $amountCents,
                'status'       => 'succeeded',
                'reason'       => $reason,
                'created_by'   => $actor?->id,
            ]);

            App::make(OrderAuditLogger::class)->refundCreated(
                $order,
                $amountCents,
                $actor,
                $reason,
                ['mode' => 'order_amount']
            );

            $order->total_refunded_cents = (int)$order->total_refunded_cents + $amountCents;
            $order->status = \App\Models\Order::STATUS_PARTIAL_REFUND;
            if ($order->refundableAmountCents() === 0) {
                $order->status = \App\Models\Order::STATUS_REFUND;
                $order->refunded_at = now();
            }
            $order->save();

            event(new \App\Events\OrderWorkflowUpdated($order->id));

            return $refund;
        });
    }
}
