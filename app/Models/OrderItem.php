<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PAID        = 'paid';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_REFUND      = 'refund';

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'unit_price_cents',
        'qty',
        'line_total_cents',
        'cost_cents',
        'profit_cents',
        'margin_bp',
        'status',
        'link_screen',
    ];

     protected $attributes = [
        'status' => self::STATUS_PENDING, // 👈 default
    ];

    protected $casts = [
        'cost_cents'   => 'integer',
        'profit_cents' => 'integer',
        'margin_bp'    => 'integer',
    ];

    public function recalcProfit(): void
    {
        if ($this->cost_cents !== null) {
            $sale = (int) $this->line_total_cents;
            $this->profit_cents = $sale - (int) $this->cost_cents;
            $this->margin_bp    = $sale > 0 ? intdiv($this->profit_cents * 10000, $sale) : null;
        } else {
            $this->profit_cents = null;
            $this->margin_bp    = null;
        }
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
