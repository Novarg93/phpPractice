<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptionGroup extends Model
{
    protected $fillable = ['product_id', 'title', 'type', 'is_required', 'position','slider_min','slider_max','slider_step','slider_default', 'multiply_by_qty'];

    public const TYPE_RADIO   = 'radio_additive';    // DefaultRadiobuttonAdditive
    public const TYPE_CHECKBOX = 'checkbox_additive'; // DefaultCheckboxAdditive
    public const TYPE_SLIDER   = 'quantity_slider';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class)->orderBy('position');
    }

     protected $casts = [
        'is_required'      => 'bool',
        'multiply_by_qty'  => 'bool', // 👈
    ];
    
    protected static function booted()
    {
        static::saved(function (OptionGroup $group) {
            if ($group->type === self::TYPE_RADIO) {
                $group->loadMissing('values');
                $hasDefault = $group->values->contains(fn($v) => $v->is_default && $v->is_active);
                if (! $hasDefault) {
                    $first = $group->values->firstWhere('is_active', true);
                    if ($first && ! $first->is_default) {
                        $first->is_default = true;
                        $first->save();
                    }
                }
            }
        });
    }
}
