<?php

namespace App\Models;

use App\Services\UniqueD4Synchronizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptionGroup extends Model
{
    // ── типы (legacy + новый канонический) ────────────────────────────────
    public const TYPE_SELECTOR         = 'selector';             // 👈 канонический селектор

    public const TYPE_RADIO            = 'radio_additive';
    public const TYPE_CHECKBOX         = 'checkbox_additive';
    public const TYPE_RADIO_PERCENT    = 'radio_percent';
    public const TYPE_CHECKBOX_PERCENT = 'checkbox_percent';
    public const TYPE_SLIDER           = 'quantity_slider';
    public const TYPE_RANGE            = 'double_range_slider';
    public const TYPE_BUNDLE           = 'bundle';

    // Новый композитный тип для D4
    public const TYPE_UNIQUE_D4        = 'unique_item_d4';

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
        'ui_variant',

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

        // Unique D4 (жёсткая форма)
        'unique_d4_is_global',
        'ga_profile_id',
        'unique_d4_labels',          // array из 4 лейблов
        'unique_d4_pricing_mode',    // absolute|percent (для local)
        'unique_d4_ga1_cents',
        'unique_d4_ga2_cents',
        'unique_d4_ga3_cents',
        'unique_d4_ga4_cents',
        'unique_d4_ga1_percent',
        'unique_d4_ga2_percent',
        'unique_d4_ga3_percent',
        'unique_d4_ga4_percent',
    ];

    protected $casts = [
        'is_required'         => 'bool',
        'multiply_by_qty'     => 'bool',
        'tiers_json'          => 'array',

        // Unique D4
        'unique_d4_is_global' => 'bool',
        'unique_d4_labels'    => 'array',
    ];

    /**
     * code: всегда нижним регистром и без пробелов по краям
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            get: fn($v) => $v,
            set: fn($v) => $v ? strtolower(trim($v)) : null,
        );
    }

    // ── Связи ──────────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class)->orderBy('position');
    }

    public function bundleItems(): HasMany
    {
        return $this->hasMany(BundleItem::class)->orderBy('position');
    }

    public function gaProfile(): BelongsTo
    {
        return $this->belongsTo(GaProfile::class, 'ga_profile_id');
    }

    // ── Хуки модели ────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        // Гарантия дефолтного значения для радио-групп (legacy radio_additive)
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

            // Синхронизация внутренних групп для Unique D4
            if ($group->type === self::TYPE_UNIQUE_D4) {
                // Создаёт/обновляет связку групп: GA (dropdown) + unique_d4_stats (4 чекбокса)
                UniqueD4Synchronizer::sync($group);
            }
        });

        // Нормализация pricing_mode для известных типов
        static::saving(function (OptionGroup $g) {
            if ($g->type === \App\Models\OptionGroup::TYPE_UNIQUE_D4 && $g->unique_d4_is_global) {
                $key = (string) request()->input('data.optionGroups.*.ga_profile_key'); // в Filament сложнее достать
                // Поэтому надежнее — сделаем так: если в атрибутах уже есть эти временные “виртуальные” поля, берём их:
                $key   = $g->getAttribute('ga_profile_key')   ?? $key ?? 'unique_d4_default';
                $title = $g->getAttribute('ga_profile_title') ?? 'Unique D4 shared pricing';
                $pm    = $g->getAttribute('ga_profile_pricing_mode') ?? 'absolute';
                $v1    = $g->getAttribute('ga_profile_ga1');
                $v2    = $g->getAttribute('ga_profile_ga2');
                $v3    = $g->getAttribute('ga_profile_ga3');
                $v4    = $g->getAttribute('ga_profile_ga4');

                if ($key) {
                    $p = \App\Models\GaProfile::firstOrNew(['key' => $key]);
                    $p->title        = $title;
                    $p->pricing_mode = in_array($pm, ['absolute', 'percent'], true) ? $pm : 'absolute';
                    if ($p->pricing_mode === 'percent') {
                        $p->ga1_percent = (float) $v1;
                        $p->ga2_percent = (float) $v2;
                        $p->ga3_percent = (float) $v3;
                        $p->ga4_percent = (float) $v4;
                        // cents занулим, чтобы не путаться:
                        $p->ga1_cents = $p->ga2_cents = $p->ga3_cents = $p->ga4_cents = 0;
                    } else {
                        $p->ga1_cents = (int) $v1;
                        $p->ga2_cents = (int) $v2;
                        $p->ga3_cents = (int) $v3;
                        $p->ga4_cents = (int) $v4;
                        $p->ga1_percent = $p->ga2_percent = $p->ga3_percent = $p->ga4_percent = null;
                    }
                    $p->save();
                    $g->ga_profile_id = $p->id;
                }
            }
        });
    }
}
