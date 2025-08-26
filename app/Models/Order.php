<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id','status','currency','subtotal_cents','shipping_cents','tax_cents','total_cents',
        'payment_method','payment_id','placed_at','shipping_address','billing_address','notes',
    ];

    protected $casts = [
        'placed_at' => 'datetime',
        'shipping_address' => 'array',
        'billing_address' => 'array',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
}