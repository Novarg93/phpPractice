<?php

namespace App\Support;

use App\Models\OptionGroup;
use InvalidArgumentException;

class RangePricing
{
    /**
     * Проверяем диапазон: в пределах [slider_min..slider_max], выровнен по step,
     * min <= max, соблюдён max_span (в "единицах" шага, с включительными границами).
     */
    public static function validateSelection(OptionGroup $g, int $min, int $max): void
    {
        if ($g->type !== OptionGroup::TYPE_RANGE) {
            throw new InvalidArgumentException('Group is not double_range_slider');
        }

        $Smin = (int) $g->slider_min;
        $Smax = (int) $g->slider_max;
        $step = max(1, (int) $g->slider_step);

        if ($min < $Smin || $max > $Smax || $min > $max) {
            throw new InvalidArgumentException('Selected range out of bounds');
        }

        // Выравнивание по сетке, заданной Smin и step
        if ((($min - $Smin) % $step) !== 0 || (($max - $Smin) % $step) !== 0) {
            throw new InvalidArgumentException('Selected values not aligned to step');
        }

        // Ограничение максимального охвата (в штуках с учётом step), границы включительные
        if ($g->max_span) {
            $units = intdiv(($max - $min), $step) + 1;
            if ($units > (int) $g->max_span) {
                throw new InvalidArgumentException('Selected span exceeds max_span');
            }
        }
    }

    /**
     * Основной расчёт.
     * Возвращает total/subtotal/base_fee и breakdown по сегментам.
     * Для flat: subtotal = units * unit_price.
     * Для tiered: считаем piecewise + применяем стратегию комбинирования.
     */
    public static function calculate(OptionGroup $g, int $min, int $max): array
    {
        self::validateSelection($g, $min, $max);

        $Smin = (int) $g->slider_min;
        $step = max(1, (int) $g->slider_step);

        // Кол-во "единиц" в выбранном диапазоне (границы включительные)
        $units = intdiv(($max - $min), $step) + 1;
        $base  = (int) ($g->base_fee_cents ?? 0);

        if (($g->pricing_mode ?? 'flat') === 'flat') {
            $unitPrice = (int) ($g->unit_price_cents ?? 0);
            $subtotal  = $units * $unitPrice;
            $total     = $base + $subtotal;

            return [
                'total_cents'     => $total,
                'subtotal_cents'  => $subtotal,
                'base_fee_cents'  => $base,
                'breakdown'       => [[
                    'from'              => $min,
                    'to'                => $max,
                    'units'             => $units,
                    'unit_price_cents'  => $unitPrice,
                    'label'             => 'flat',
                    'subtotal_cents'    => $subtotal,
                ]],
            ];
        }

        // tiered
        $tiers    = (array) ($g->tiers_json ?? []);
        $strategy = $g->tier_combine_strategy ?: 'sum_piecewise';
        $parts    = self::splitByTiers($g, $min, $max, $tiers);

        $breakdown = [];
        $sum = 0;

        foreach ($parts as $p) {
            // кол-во штук внутри сегмента по сетке шага
            $segUnits = intdiv(($p['to'] - $p['from']), $step) + 1;
            $unit     = (int) $p['unit_price_cents'];

            // базовый piecewise
            $subtotal = $segUnits * $unit;

            // опциональные модификаторы
            if (!empty($p['min_block']) && (int)$p['min_block'] > 1) {
                $block = (int) $p['min_block'];
                $blocks = (int) ceil($segUnits / $block);
                $subtotal = $blocks * $block * $unit;
            }

            if (!empty($p['multiplier']) && (float)$p['multiplier'] != 1.0) {
                $subtotal = (int) round($subtotal * (float)$p['multiplier']);
            }

            if (!empty($p['cap_cents'])) {
                $subtotal = min($subtotal, (int) $p['cap_cents']);
            }

            $breakdown[] = [
                'from'              => $p['from'],
                'to'                => $p['to'],
                'units'             => $segUnits,
                'unit_price_cents'  => $unit,
                'label'             => $p['label'] ?? null,
                'subtotal_cents'    => $subtotal,
            ];

            $sum += $subtotal;
        }

        // формируем итог согласно стратегии
        $variable = match ($strategy) {
            'highest_tier_only' => self::highestTierOnlyTotal($breakdown, $units), // учитываем все units выбранного диапазона
            'weighted_average'  => self::weightedAverageTotal($breakdown),         // как и раньше: сумма piecewise
            default             => $sum,                                           // sum_piecewise
        };

        return [
            'total_cents'     => $base + $variable,
            'subtotal_cents'  => $sum,
            'base_fee_cents'  => $base,
            'breakdown'       => $breakdown,
        ];
    }

    /**
     * Разбивает выбранный диапазон на пересечения с тирами и
     * подравнивает границы по сетке (Smin/step).
     */
    protected static function splitByTiers(OptionGroup $g, int $min, int $max, array $tiers): array
    {
        $Smin = (int) $g->slider_min;
        $step = max(1, (int) $g->slider_step);

        $ranges = [];

        foreach ($tiers as $t) {
            $f  = (int) ($t['from'] ?? $min);
            $to = (int) ($t['to']   ?? $max);
            if ($to < $min || $f > $max) {
                continue;
            }

            // пересечение с выбранным диапазоном
            $segFrom = max($min, $f);
            $segTo   = min($max, $to);

            // подгоняем к сетке: ближайший допустимый >= segFrom и <= segTo
            $alignedFrom = $segFrom + (($step - (($segFrom - $Smin) % $step)) % $step);
            $alignedTo   = $segTo   - ((($segTo - $Smin) % $step));

            if ($alignedFrom > $alignedTo) {
                continue; // полностью между шагами — ничего не добавляем
            }

            $ranges[] = [
                'from'              => $alignedFrom,
                'to'                => $alignedTo,
                'unit_price_cents'  => (int) ($t['unit_price_cents'] ?? 0),
                'label'             => $t['label'] ?? null,
                'min_block'         => isset($t['min_block'])   ? (int) $t['min_block'] : null,
                'multiplier'        => isset($t['multiplier'])  ? (float) $t['multiplier'] : null,
                'cap_cents'         => isset($t['cap_cents'])   ? (int) $t['cap_cents'] : null,
            ];
        }

        usort($ranges, fn($a, $b) => $a['from'] <=> $b['from']);
        return $ranges;
    }

    /**
     * Стратегия "highest_tier_only": берём наивысшую цену из breakdown,
     * умножаем на ВСЕ units выбранного диапазона (а не только покрытые тирами).
     */
    protected static function highestTierOnlyTotal(array $breakdown, int $fullUnits): int
    {
        $maxUnit = 0;
        foreach ($breakdown as $b) {
            if (($b['unit_price_cents'] ?? 0) > $maxUnit) {
                $maxUnit = (int) $b['unit_price_cents'];
            }
        }
        return $maxUnit * $fullUnits;
    }

    /**
     * "weighted_average" оставляем эквивалентом piecewise-суммы (как и было).
     * Если понадобится реальное усреднение — легко поменять.
     */
    protected static function weightedAverageTotal(array $breakdown): int
    {
        $sum = 0;
        foreach ($breakdown as $b) {
            $sum += (int) ($b['subtotal_cents'] ?? 0);
        }
        return $sum;
    }
}