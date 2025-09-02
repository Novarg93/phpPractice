<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;





class OptionGroup extends Model
{
    // ── типы (legacy + новый канонический) ────────────────────────────────
    public const TYPE_SELECTOR         = 'selector';             // 👈 НОВОЕ

    public const TYPE_RADIO            = 'radio_additive';
    public const TYPE_CHECKBOX         = 'checkbox_additive';
    public const TYPE_RADIO_PERCENT    = 'radio_percent';
    public const TYPE_CHECKBOX_PERCENT = 'checkbox_percent';
    public const TYPE_SLIDER           = 'quantity_slider';
    public const TYPE_RANGE            = 'double_range_slider';

    // Режимы селектора (для удобства)
    public const SEL_SINGLE  = 'single';
    public const SEL_MULTI   = 'multi';

    public const PRICE_ABSOLUTE = 'absolute';
    public const PRICE_PERCENT  = 'percent';

    protected $fillable = [
        'product_id',
        'title',
        'type',
        'is_required',
        'position',

        // селектор
        'selection_mode',     // single|multi
        'pricing_mode',       // absolute|percent
        'multiply_by_qty',
        'ui_variant',         // 👈 ДОБАВИЛИ

        // quantity_slider
        'qty_min',
        'qty_max',
        'qty_step',
        'qty_default',

        // double_range_slider
        'slider_min',
        'slider_max',
        'slider_step',
        'range_default_min',
        'range_default_max',
        'unit_price_cents',
        'tier_combine_strategy',
        'tiers_json',
        'base_fee_cents',
        'max_span',
        'rounding',
        'currency',
        'code',
    ];

    protected function code(): Attribute
    {
        return Attribute::make(
            get: fn($v) => $v,
            set: fn($v) => $v ? strtolower(trim($v)) : null,
        );
    }

    protected $casts = [
        'is_required'     => 'bool',
        'multiply_by_qty' => 'bool',
        'tiers_json'      => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class)->orderBy('position');
    }

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
        static::saving(function (\App\Models\OptionGroup $g) {
            if ($g->type === \App\Models\OptionGroup::TYPE_SELECTOR) {
                if (!in_array($g->pricing_mode, ['absolute', 'percent'], true)) {
                    $g->pricing_mode = 'absolute';
                }
            } elseif ($g->type === \App\Models\OptionGroup::TYPE_RANGE) {
                if (!in_array($g->pricing_mode, ['flat', 'tiered'], true)) {
                    $g->pricing_mode = 'flat';
                }
            }
        });
        
    }
}
