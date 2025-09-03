<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleItem extends Model
{
    protected $fillable = [
        'option_group_id','product_id','qty_min','qty_max','qty_step','qty_default','position',
    ];

    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}