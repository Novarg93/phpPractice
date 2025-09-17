<?php

namespace App\Services\Payments;

use App\Contracts\Payments\RefundProvider;
use App\Contracts\Payments\RefundResult;
use App\Models\Order;
use Illuminate\Support\Str;

class FakeRefundProvider implements RefundProvider
{
    public function refundOrderAmount(Order $order, int $amountCents, ?string $reason = null): RefundResult
    {
        return new RefundResult(
            provider: 'stripe_test',
            status: 'succeeded', // симулируем мгновенный успешный возврат
            providerId: 'rf_test_' . Str::random(24),
            payload: [
                'order_id' => $order->id,
                'amount'   => $amountCents,
                'reason'   => $reason,
                'simulated'=> true,
                'created'  => now()->toIso8601String(),
            ]
        );
    }
}