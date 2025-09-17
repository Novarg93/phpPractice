<?php

namespace App\Contracts\Payments;

use App\Models\Order;

class RefundResult {
    public function __construct(
        public string  $provider,       // 'stripe_test' | 'stripe'
        public string  $status,         // 'succeeded' | 'pending' | 'failed' | ...
        public ?string $providerId = null,
        public ?array  $payload    = null,
    ) {}
}

interface RefundProvider
{
    /** Частичный/полный рефанд по заказу на фиксированную сумму (в центах). */
    public function refundOrderAmount(Order $order, int $amountCents, ?string $reason = null): RefundResult;
}