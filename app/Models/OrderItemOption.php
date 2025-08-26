<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemOption extends Model
{
    protected $fillable = ['order_item_id','option_value_id','title','price_delta_cents'];

    public function item(): BelongsTo { return $this->belongsTo(OrderItem::class); }
}