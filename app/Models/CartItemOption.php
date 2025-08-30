<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItemOption extends Model
{
    protected $fillable = [
        'cart_item_id',
        'option_value_id', // для radio/checkbox
        'option_group_id', // для range
        'selected_min',
        'selected_max',
        'price_delta_cents',
        'payload_json',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];

    public function item(): BelongsTo { return $this->belongsTo(CartItem::class); }
    public function optionValue(): BelongsTo { return $this->belongsTo(OptionValue::class); }
    public function group(): BelongsTo { return $this->belongsTo(OptionGroup::class, 'option_group_id'); }
}