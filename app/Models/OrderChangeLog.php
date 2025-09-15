<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderChangeLog extends Model
{
    protected $fillable = [
        'order_id','order_item_id','actor_id','action','field',
        'old_cents','new_cents','old_value','new_value','note','meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function item(): BelongsTo { return $this->belongsTo(OrderItem::class, 'order_item_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
