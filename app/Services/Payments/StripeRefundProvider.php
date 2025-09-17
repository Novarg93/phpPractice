<?php

namespace App\Services\Payments;

use App\Contracts\Payments\RefundProvider;
use App\Contracts\Payments\RefundResult;
use App\Models\Order;
use Stripe\StripeClient;

class StripeRefundProvider implements RefundProvider
{
    public function __construct(private StripeClient $stripe) {}

    public function refundOrderAmount(Order $order, int $amountCents, ?string $reason = null): RefundResult
    {
        // пример на payment_intent (ты сохраняешь его в $order->payment_id)
        $resp = $this->stripe->refunds->create([
            'payment_intent' => $order->payment_id,
            'amount'         => $amountCents,
            // 'reason'      => 'requested_by_customer', // по желанию
        ]);

        if (empty($order->payment_id)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'order' => 'Order has no payment_id to refund (PaymentIntent).',
            ]);
        }

        return new RefundResult(
            provider: 'stripe',
            status: (string)($resp->status ?? 'succeeded'),
            providerId: (string)($resp->id ?? null),
            payload: $resp->toArray()
        );
    }
}
