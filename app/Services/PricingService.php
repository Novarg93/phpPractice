<?php

namespace App\Services;

use App\Models\Product;
use App\Models\OptionValue;
use App\Models\OptionGroup;
use App\Support\RangePricing;
use Illuminate\Support\Arr;

class PricingService
{
    public function compute(Product $product, array $payload): array
    {
        $valueIds = array_map('intval', Arr::get($payload, 'option_value_ids', []));
        $ranges   = Arr::get($payload, 'range_options', []); // [{ option_group_id, selected_min, selected_max }]
        $qty      = max(1, (int) (Arr::get($payload, 'qty', 1) ?: 1));

        $base = (int) $product->price_cents;

        $perUnitAdd     = 0;
        $perOrderAdd    = 0;
        $perUnitFactor  = 1.0;
        $perOrderFactor = 1.0;

        // 1) selector (канонический) + legacy radio/checkbox
        if ($valueIds) {
            $values = OptionValue::with('group')
                ->whereIn('id', $valueIds)
                ->get()
                ->filter(fn($v) => $v->group && $v->group->product_id === $product->id);

            foreach ($values as $v) {
                $g = $v->group;

                // Канонический selector
                if (($g->type ?? null) === OptionGroup::TYPE_SELECTOR) {
                    $pricing = $g->pricing_mode ?? 'absolute'; // absolute|percent
                    $perUnit = (bool)($g->multiply_by_qty ?? false);

                    $deltaCents   = (int)($v->delta_cents    ?? $v->price_delta_cents ?? 0);
                    $deltaPercent = (float)($v->delta_percent ?? $v->value_percent    ?? 0.0);

                    if ($pricing === 'absolute') {
                        if ($perUnit) $perUnitAdd += $deltaCents;
                        else          $perOrderAdd += $deltaCents;
                    } else {
                        $f = 1 + ($deltaPercent / 100.0);
                        if ($perUnit) $perUnitFactor *= $f;
                        else          $perOrderFactor *= $f;
                    }
                    continue;
                }

                // Legacy абсолютные
                if ($g->type === OptionGroup::TYPE_RADIO || $g->type === OptionGroup::TYPE_CHECKBOX) {
                    $delta = (int)($v->price_delta_cents ?? 0);
                    if ($g->multiply_by_qty) $perUnitAdd += $delta; else $perOrderAdd += $delta;
                    continue;
                }

                // Legacy процентные
                if ($g->type === OptionGroup::TYPE_RADIO_PERCENT || $g->type === OptionGroup::TYPE_CHECKBOX_PERCENT) {
                    $p = (float)($v->value_percent ?? 0);
                    $f = 1 + ($p / 100.0);
                    if ($g->multiply_by_qty) $perUnitFactor *= $f; else $perOrderFactor *= $f;
                    continue;
                }
            }
        }

        // 2) double_range_slider → добавка к цене единицы
        foreach ($ranges as $ro) {
            $gid = (int)($ro['option_group_id'] ?? 0);
            $selMin = (int)($ro['selected_min'] ?? 0);
            $selMax = (int)($ro['selected_max'] ?? 0);

            $g = OptionGroup::query()->find($gid);
            if (!$g || $g->product_id !== $product->id || $g->type !== OptionGroup::TYPE_RANGE) {
                continue;
            }

            // используем твой RangePricing для цены и валидации
            try {
                $calc = RangePricing::calculate($g, $selMin, $selMax);
                $perUnitAdd += (int) $calc['total_cents'];
            } catch (\Throwable $e) {
                // некорректный выбор — просто игнорируем этот диапазон
            }
        }

        // 3) итог (как на фронте)
        $unit  = (int) round( ($base + $perUnitAdd) * $perUnitFactor );
        $total = (int) round( ($unit * $qty + $perOrderAdd) * $perOrderFactor );

        return compact(
            'base',
            'perUnitAdd',
            'perOrderAdd',
            'perUnitFactor',
            'perOrderFactor',
            'qty',
            'unit',
            'total'
        );
    }
}