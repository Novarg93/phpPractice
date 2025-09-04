<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{

    public const STATUS_PENDING     = 'pending';      // заказ создан, ушли на оплату
    public const STATUS_PAID        = 'paid';         // оплата подтвердилась (webhook)
    public const STATUS_IN_PROGRESS = 'in_progress';  // взяли в работу (ручное)
    public const STATUS_COMPLETED   = 'completed';    // доставили (ручное)
    public const STATUS_REFUND      = 'refund';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'user_id',
        'status',
        'currency',
        'subtotal_cents',
        'shipping_cents',
        'tax_cents',
        'total_cents',
        'payment_method',
        'payment_id',
        'placed_at',
        'shipping_address',
        'billing_address',
        'notes',
        'game_payload',
        'checkout_session_id',
    ];

    protected $casts = [
        'placed_at' => 'datetime',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'game_payload' => 'array',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING     => 'Pending',
            self::STATUS_PAID        => 'Paid',
            self::STATUS_IN_PROGRESS => 'In progress',
            self::STATUS_COMPLETED   => 'Completed',
            self::STATUS_REFUND      => 'Refund',
            self::STATUS_CANCELED    => 'Canceled',
        ];
    }

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];



    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
