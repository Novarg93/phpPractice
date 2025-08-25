<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItemOption extends Model
{
    protected $fillable = ['cart_item_id','option_value_id'];

    public function item(): BelongsTo { return $this->belongsTo(CartItem::class); }
    public function optionValue(): BelongsTo { return $this->belongsTo(OptionValue::class); }
}