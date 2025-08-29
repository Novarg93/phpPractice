<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id','product_id','product_name','unit_price_cents','qty','line_total_cents'
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function options(): HasMany { return $this->hasMany(OrderItemOption::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}