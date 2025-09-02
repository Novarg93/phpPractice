<?php

namespace App\Services\Cart;

use App\Models\{Product, OptionGroup, OptionValue};

class CartPricing
{
    public function productWithGroups(int $productId): Product
    {
        return Product::with(['optionGroups.values'])->findOrFail($productId);
    }

    /** === ВАЛИДАЦИИ === */

    public function validateSelection(int $productId, array $optionValueIds): void
    {
        $product = $this->productWithGroups($productId);
        $chosen  = collect($optionValueIds);

        foreach ($chosen as $vid) {
            $belongs = $product->optionGroups->first(fn($g) => $g->values->contains('id', $vid));
            abort_unless($belongs, 422, 'Invalid option value selected.');
        }

        foreach ($product->optionGroups as $g) {
            if (in_array($g->type, [OptionGroup::TYPE_SELECTOR, 'selector'], true)) {
                $selected = $chosen->filter(fn($vid) => $g->values->contains('id', $vid));
                $single   = (($g->selection_mode ?? 'single') !== 'multi');

                if ($single && $selected->count() > 1) abort(422, 'Only one option can be selected in "' . $g->title . '".');

                if ($g->is_required) {
                    if ($single && $selected->count() !== 1) abort(422, '"' . $g->title . '" is required.');
                    if (!$single && $selected->count() < 1)   abort(422, 'Select at least one in "' . $g->title . '".');
                }
                continue;
            }

            if (in_array($g->type, [OptionGroup::TYPE_RADIO, OptionGroup::TYPE_RADIO_PERCENT], true)) {
                $selected = $chosen->filter(fn($vid) => $g->values->contains('id', $vid));
                if ($selected->count() > 1) abort(422, 'Only one option can be selected in "' . $g->title . '".');
                if ($g->is_required && $selected->count() !== 1) abort(422, '"' . $g->title . '" is required.');
            } elseif (in_array($g->type, [OptionGroup::TYPE_CHECKBOX, OptionGroup::TYPE_CHECKBOX_PERCENT], true)) {
                $selected = $chosen->filter(fn($vid) => $g->values->contains('id', $vid));
                if ($g->is_required && $selected->count() < 1) abort(422, 'Select at least one in "' . $g->title . '".');
            }
        }
    }

    public function validateAndResolveQty(int $productId, ?int $qtyFromRequest): int
    {
        $g = OptionGroup::where('product_id', $productId)->where('type', OptionGroup::TYPE_SLIDER)->first();
        if (!$g) return max(1, (int)($qtyFromRequest ?? 1));

        $min  = $g->qty_min ?? 1;
        $max  = $g->qty_max ?? PHP_INT_MAX;
        $step = max(1, (int)($g->qty_step ?? 1));
        $def  = $g->qty_default ?? $min;
        $q    = $qtyFromRequest ?? $def;

        if ($g->is_required && ($q === null)) abort(422, '"' . $g->title . '" is required.');
        if ($q < $min || $q > $max)          abort(422, 'Quantity must be between ' . $min . ' and ' . $max . '.');
        if (($q - $min) % $step !== 0)       abort(422, 'Invalid quantity step for "' . $g->title . '".');

        return (int)$q;
    }

    public function validateRangeSelections(int $productId, array $rangeSelections): array
    {
        $product = $this->productWithGroups($productId);

        $list = collect($rangeSelections ?? [])->map(fn($row) => [
            'option_group_id' => (int)($row['option_group_id'] ?? 0),
            'selected_min'    => (int)($row['selected_min'] ?? 0),
            'selected_max'    => (int)($row['selected_max'] ?? 0),
        ])->values();

        foreach ($product->optionGroups as $g) {
            if ($g->type !== OptionGroup::TYPE_RANGE) continue;

            $sel  = $list->firstWhere('option_group_id', $g->id);
            if ($g->is_required && !$sel) abort(422, '"' . $g->title . '" is required.');
            if (!$sel) continue;

            $min  = (int)$g->slider_min;
            $max  = (int)$g->slider_max;
            $step = max(1, (int)$g->slider_step);

            if (
                $sel['selected_min'] < $min || $sel['selected_min'] > $max
                || $sel['selected_max'] < $min || $sel['selected_max'] > $max
            ) {
                abort(422, 'Selected range for "' . $g->title . '" is out of bounds.');
            }

            if ((($sel['selected_min'] - $min) % $step) !== 0
                || (($sel['selected_max'] - $min) % $step) !== 0
            ) {
                abort(422, 'Invalid step for "' . $g->title . '".');
            }

            if ($g->pricing_mode === 'tiered' && $g->max_span) {
                $span = max(0, $sel['selected_max'] - $sel['selected_min']);
                if ($span > (int)$g->max_span) abort(422, 'Selected range exceeds maximum span for "' . $g->title . '".');
            }
        }

        return $list->all();
    }

    /** === РАСЧЁТЫ === */

    public function computeUnitAndTotalCents(int $productId, array $optionValueIds, array $rangeSelections, int $qty): array
    {
        $product = $this->productWithGroups($productId);
        $groups  = $product->optionGroups->keyBy('id');
        $values  = OptionValue::whereIn('id', $optionValueIds)->get();

        $addUnitAbs     = 0;
        $sumPercUnit    = 0.0;   // 👈 вместо mulUnit
        $addTotalAbs    = 0;
        $sumPercTotal   = 0.0;   // 👈 вместо mulTotal

        foreach ($values as $v) {
            $g = $groups->get($v->option_group_id);
            if (!$g) continue;

            $isPerUnit = (bool) $g->multiply_by_qty;

            if (in_array($g->type, [OptionGroup::TYPE_SELECTOR, 'selector'], true)) {
                $mode = $g->pricing_mode === 'percent' ? 'percent' : 'absolute';

                if ($mode === 'percent') {
                    $p = (float)($v->delta_percent ?? $v->value_percent ?? 0.0);
                    if ($isPerUnit) $sumPercUnit  += $p;
                    else $sumPercTotal += $p;   // 👈 суммируем
                } else {
                    $delta = (int)($v->delta_cents ?? $v->price_delta_cents ?? 0);
                    if ($isPerUnit) $addUnitAbs += $delta;
                    else $addTotalAbs += $delta;
                }
                continue;
            }

            if (in_array($g->type, [OptionGroup::TYPE_RADIO, OptionGroup::TYPE_CHECKBOX], true)) {
                $delta = (int)($v->price_delta_cents ?? 0);
                if ($isPerUnit) $addUnitAbs += $delta;
                else $addTotalAbs += $delta;
                continue;
            }

            if (in_array($g->type, [OptionGroup::TYPE_RADIO_PERCENT, OptionGroup::TYPE_CHECKBOX_PERCENT], true)) {
                $p = (float)($v->value_percent ?? 0.0);
                if ($isPerUnit) $sumPercUnit  += $p;
                else $sumPercTotal += $p;      // 👈 суммируем
                continue;
            }
        }

        $deltaRangePerUnit = $this->computeRangePerUnitDelta($product, $rangeSelections);

        $unitBase = (int)$product->price_cents + $addUnitAbs + $deltaRangePerUnit;
        $unit     = (int) round($unitBase * (1.0 + $sumPercUnit / 100.0));          // 👈 применяем один раз

        $subtotal      = $unit * max(1, $qty);
        $beforePercent = $subtotal + $addTotalAbs;
        $lineTotal     = (int) round($beforePercent * (1.0 + $sumPercTotal / 100.0)); // 👈 применяем один раз

        return [
            'unit'       => $unit,
            'line_total' => $lineTotal,
            'breakdown'  => [
                'base'            => (int)$product->price_cents,
                'addUnitAbs'      => $addUnitAbs,
                'sumPercUnit'     => $sumPercUnit,    // 👈 для отладки можно вернуть суммы, а не множители
                'rangePerUnit'    => $deltaRangePerUnit,
                'qty'             => $qty,
                'addTotalAbs'     => $addTotalAbs,
                'sumPercTotal'    => $sumPercTotal,
            ],
        ];
    }

    public function computeRangePerUnitDelta(Product $product, array $rangeSelections): int
    {
        $sum = 0;

        foreach ($rangeSelections as $sel) {
            $g = $product->optionGroups
                ->first(fn($gg) => $gg->id === (int)$sel['option_group_id'] && $gg->type === OptionGroup::TYPE_RANGE);

            if (!$g) continue;

            $min  = max((int)$g->slider_min, (int)$sel['selected_min']);
            $max  = min((int)$g->slider_max, (int)$sel['selected_max']);
            $step = max(1, (int)$g->slider_step);

            $min = $this->snapToStep($min, (int)$g->slider_min, $step);
            $max = $this->snapToStep($max, (int)$g->slider_min, $step);
            if ($min > $max) [$min, $max] = [$max, $min];

            $span = max(0, $max - $min);
            if ($span === 0) continue;

            $pricingMode = $g->pricing_mode ?? 'flat';

            if ($pricingMode === 'flat') {
                $unit = (int)($g->unit_price_cents ?? 0);
                $sum += $this->applyBlocksAndCaps($span, $unit, [
                    'min_block' => null,
                    'multiplier' => null,
                    'cap_cents' => null,
                ]);
            } elseif ($pricingMode === 'tiered') {
                $tiers    = is_array($g->tiers_json) ? $g->tiers_json : (json_decode((string)$g->tiers_json, true) ?: []);
                $strategy = $g->tier_combine_strategy ?: 'sum_piecewise';
                $baseFee  = (int)($g->base_fee_cents ?? 0);
                $maxSpan  = $g->max_span ? (int)$g->max_span : null;

                if ($maxSpan !== null && $span > $maxSpan) abort(422, 'Selected range exceeds maximum allowed span.');

                $sum += $baseFee;
                $sum += $this->priceTiered($min, $max, $g->slider_min, $tiers, $strategy);
            }
        }

        return (int)$sum;
    }

    /** ===== Helpers (private) ===== */

    private function priceTiered(int $selMin, int $selMax, int $baseMin, array $tiers, string $strategy): int
    {
        $tiers = collect($tiers)->map(fn($t) => [
            'from'             => (int)($t['from'] ?? 0),
            'to'               => (int)($t['to'] ?? 0),
            'unit_price_cents' => (int)($t['unit_price_cents'] ?? 0),
            'min_block'        => isset($t['min_block']) ? (int)$t['min_block'] : null,
            'multiplier'       => isset($t['multiplier']) ? (float)$t['multiplier'] : null,
            'cap_cents'        => isset($t['cap_cents']) ? (int)$t['cap_cents'] : null,
        ])->sortBy('from')->values()->all();

        $spanTotal   = max(0, $selMax - $selMin);
        if ($spanTotal === 0) return 0;

        $piecewise   = 0;
        $highestUnit = 0;
        $weightedSum = 0;

        foreach ($tiers as $t) {
            $from = max($t['from'], $selMin);
            $to   = min($t['to'],   $selMax);
            if ($to <= $from) continue;

            $steps = $to - $from;

            $unit = $t['unit_price_cents'];
            if ($t['multiplier']) $unit = (int)round($unit * (float)$t['multiplier']);

            if ($t['min_block']) $steps = (int)ceil($steps / (int)$t['min_block']) * (int)$t['min_block'];

            $cost = $unit * $steps;
            if ($t['cap_cents'] !== null) $cost = min($cost, (int)$t['cap_cents']);

            $piecewise   += $cost;
            $highestUnit  = max($highestUnit, $unit);
            $weightedSum += $unit * ($to - $from);
        }

        return match ($strategy) {
            'highest_tier_only' => $highestUnit * $spanTotal,
            'weighted_average'  => (int)round(($spanTotal > 0 ? $weightedSum / $spanTotal : 0) * $spanTotal),
            default             => $piecewise,
        };
    }

    private function snapToStep(int $val, int $base, int $step): int
    {
        $off = ($val - $base) % $step;
        return $val - $off;
    }

    private function applyBlocksAndCaps(int $steps, int $unit, array $opts): int
    {
        $minBlock = $opts['min_block'] ?? null;
        $mult     = $opts['multiplier'] ?? null;
        $cap      = $opts['cap_cents'] ?? null;

        if ($minBlock) $steps = (int)ceil($steps / (int)$minBlock) * (int)$minBlock;
        if ($mult)     $unit  = (int)round($unit * (float)$mult);

        $cost = $unit * $steps;
        if ($cap !== null) $cost = min($cost, (int)$cap);

        return $cost;
    }
}
