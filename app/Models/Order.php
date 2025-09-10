<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use App\Models\OrderItem;


class Order extends Model
{

    public const STATUS_PENDING     = 'pending';      // заказ создан, ушли на оплату
    public const STATUS_PAID        = 'paid';         // оплата подтвердилась (webhook)
    public const STATUS_IN_PROGRESS = 'in_progress';  // взяли в работу (ручное)
    public const STATUS_COMPLETED   = 'completed';    // доставили (ручное)
    public const STATUS_REFUND      = 'refund';
    public const STATUS_PARTIAL_REFUND = 'partial_refund';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'user_id',
        'status',
        'currency',
        'subtotal_cents',
        'shipping_cents',
        'tax_cents',
        'total_cents',
        'promo_code_id',
        'promo_discount_cents',
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
        'placed_at'        => 'datetime',
        'paid_at'          => 'datetime',
        'shipping_address' => 'array',
        'completed_at'     => 'datetime',
        'refunded_at'      => 'datetime',
        'billing_address'  => 'array',
        'game_payload'     => 'array',
        'delivery_seconds' => 'integer',
    ];

    public static function statusOptions(): array
    {
        return [

            self::STATUS_PENDING        => 'Pending',
            self::STATUS_PAID           => 'Paid',
            self::STATUS_IN_PROGRESS    => 'In progress',
            self::STATUS_COMPLETED      => 'Completed',
            self::STATUS_PARTIAL_REFUND => 'Partial refund',
            self::STATUS_REFUND         => 'Refund',
            self::STATUS_CANCELED       => 'Canceled',
        ];
    }

    public function syncStatusFromItems(): string
    {
        $statuses = $this->items()->pluck('status')->all();
        if (!$statuses) {
            return $this->status;
        }

        $allCompleted  = collect($statuses)->every(fn($s) => $s === OrderItem::STATUS_COMPLETED);
        $anyInProgress = in_array(OrderItem::STATUS_IN_PROGRESS, $statuses, true);
        $anyPaid       = in_array(OrderItem::STATUS_PAID,        $statuses, true);
        $hasRefund     = in_array(OrderItem::STATUS_REFUND,      $statuses, true);

        $new = match (true) {
            $hasRefund     => self::STATUS_REFUND,
            $allCompleted  => self::STATUS_COMPLETED,
            $anyInProgress => self::STATUS_IN_PROGRESS,
            $anyPaid       => self::STATUS_PAID,
            default        => self::STATUS_PENDING,
        };

        // если статус не меняется — но заказ уже completed и delivery_seconds ещё не посчитан/некорректен
        if ($new === $this->status) {
            if ($new === self::STATUS_COMPLETED && $this->completed_at && (is_null($this->delivery_seconds) || $this->delivery_seconds <= 0)) {
                $from = $this->paid_at ?: $this->created_at;
                $to   = $this->completed_at;
                $this->delivery_seconds = ($from && $to)
                    ? max(0, $to->getTimestamp() - $from->getTimestamp())
                    : null;
                $this->save();
            }
            return $new;
        }
        // применяем новый статус + системные даты
        $this->status = $new;

        if ($new === self::STATUS_PAID && !$this->paid_at) {
            $this->paid_at = now();
        }

        if ($new === self::STATUS_COMPLETED) {
            if (!$this->completed_at) {
                $this->completed_at = now();
            }
            $from = $this->paid_at ?: $this->created_at; // старт: paid или created (pending)
            $to   = $this->completed_at;
            $this->delivery_seconds = ($from && $to)
                ? max(0, $to->getTimestamp() - $from->getTimestamp())
                : null;
        }

        $this->save();
        return $new;
    }



    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public function recalcTotals(): void
    {
        $items = $this->items()->get();

        $workItems = $items->where('status', '!=', OrderItem::STATUS_REFUND);
        $saleSum   = (int) $workItems->sum('line_total_cents');
        $costSum   = (int) $workItems->sum(fn($i) => (int) ($i->cost_cents ?? 0));
        $profitSum = (int) $workItems->sum(function ($i) {
            $sale  = (int) $i->line_total_cents;
            $cost  = (int) ($i->cost_cents ?? 0);
            return $sale - $cost;
        });

        $this->total_cost_cents   = $costSum ?: null;
        $this->total_profit_cents = $profitSum ?: null;
        $this->margin_bp          = $saleSum > 0 ? intdiv($profitSum * 10000, $saleSum) : null;
        $this->save();
    }


    protected static function booted()
    {
        static::updated(function (Order $order) {
            if ($order->wasChanged([
                'status',
                'paid_at',
                'completed_at',
                'refunded_at',
                'total_cost_cents',
                'total_profit_cents',
                'margin_bp',
                'delivery_seconds',
            ])) {
                Log::info('Order updated ⇒ broadcasting', [
                    'order_id' => $order->id,
                    'changed'  => $order->getChanges(),
                ]);
                event(new \App\Events\OrderWorkflowUpdated($order->id));
            }
        });
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }
    public function promoRedemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }
}
