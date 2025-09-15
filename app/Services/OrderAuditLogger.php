<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderChangeLog;
use App\Models\User;

class OrderAuditLogger
{
    public function costUpdated(OrderItem $item, ?int $old, ?int $new, ?User $actor = null, ?string $note = null): void
    {
        OrderChangeLog::create([
            'order_id'      => $item->order_id,
            'order_item_id' => $item->id,
            'actor_id'      => $actor?->id,
            'action'        => 'cost_updated',
            'field'         => 'cost_cents',
            'old_cents'     => $old,
            'new_cents'     => $new,
            'note'          => $note,
        ]);
    }

    public function refundCreated(Order $order, int $amountCents, ?User $actor = null, ?string $note = null, array $meta = []): void
    {
        OrderChangeLog::create([
            'order_id'      => $order->id,
            'actor_id'      => $actor?->id,
            'action'        => 'refund_created',
            'field'         => 'refund_amount_cents',
            'new_cents'     => $amountCents,
            'note'          => $note,
            'meta'          => $meta ?: null,
        ]);
    }

    public function statusChangedOnOrder(Order $order, string $old, string $new, ?User $actor = null): void
    {
        OrderChangeLog::create([
            'order_id'      => $order->id,
            'actor_id'      => $actor?->id,
            'action'        => 'order_status_changed',
            'field'         => 'status',
            'old_value'     => $old,
            'new_value'     => $new,
        ]);
    }

    public function statusChangedOnItem(OrderItem $item, string $old, string $new, ?User $actor = null): void
    {
        OrderChangeLog::create([
            'order_id'      => $item->order_id,
            'order_item_id' => $item->id,
            'actor_id'      => $actor?->id,
            'action'        => 'item_status_changed',
            'field'         => 'status',
            'old_value'     => $old,
            'new_value'     => $new,
        ]);
    }
}
