<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemOption extends Model
{
    protected $fillable = [
        'order_item_id',
        'option_value_id',
        'option_group_id',
        'title',
        'price_delta_cents',
        'selected_min',
        'selected_max',
        'payload_json',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];
    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(OptionValue::class, 'option_value_id');
    }
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }
}
