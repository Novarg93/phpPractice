<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptionGroup extends Model
{
    protected $fillable = [
        'product_id',
        'title',
        'type',
        'is_required',
        'position',
        'slider_min',
        'slider_max',
        'slider_step',
        'slider_default',
        'multiply_by_qty',
        'qty_min',
        'qty_max',
        'qty_step',
        'qty_default',

        // новое для double range
        'range_default_min',
        'range_default_max',
        'pricing_mode',
        'unit_price_cents',
        'tier_combine_strategy',
        'tiers_json',
        'base_fee_cents',
        'max_span',
        'rounding',
        'currency',
    ];



    public const TYPE_RADIO    = 'radio_additive';
    public const TYPE_CHECKBOX = 'checkbox_additive';
    public const TYPE_SLIDER   = 'quantity_slider';
    public const TYPE_RANGE    = 'double_range_slider';
    public const TYPE_RADIO_PERCENT    = 'radio_percent';
    public const TYPE_CHECKBOX_PERCENT = 'checkbox_percent';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class)->orderBy('position');
    }

    protected $casts = [
        'is_required'     => 'bool',
        'multiply_by_qty' => 'bool',
        'tiers_json'      => 'array', // 👈
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
