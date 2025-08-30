<?php

namespace App\Support;

use App\Models\OptionGroup;
use InvalidArgumentException;

class RangePricing
{
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
        if ( (($min - $Smin) % $step) !== 0 || (($max - $Smin) % $step) !== 0 ) {
            throw new InvalidArgumentException('Selected values not aligned to step');
        }
        if ($g->max_span && ($max - $min + 1) > $g->max_span) {
            throw new InvalidArgumentException('Selected span exceeds max_span');
        }
    }

    public static function calculate(OptionGroup $g, int $min, int $max): array
    {
        self::validateSelection($g, $min, $max);

        $span = $max - $min + 1;
        $base = (int) ($g->base_fee_cents ?? 0);

        if ($g->pricing_mode === 'flat') {
            $unit = (int) ($g->unit_price_cents ?? 0);
            $subtotal = $span * $unit;
            $total = $base + $subtotal;

            return [
                'total_cents' => $total,
                'subtotal_cents' => $subtotal,
                'base_fee_cents' => $base,
                'breakdown' => [[
                    'from' => $min,
                    'to' => $max,
                    'units' => $span,
                    'unit_price_cents' => $unit,
                    'label' => 'flat',
                    'subtotal_cents' => $subtotal,
                ]],
            ];
        }

        // tiered
        $tiers = (array) ($g->tiers_json ?? []);
        $strategy = $g->tier_combine_strategy ?: 'sum_piecewise';
        $parts = self::splitByTiers($min, $max, $tiers);

        $breakdown = [];
        $sum = 0;

        foreach ($parts as $p) {
            $units = $p['to'] - $p['from'] + 1;
            $unit = (int) $p['unit_price_cents'];
            $subtotal = $units * $unit;

            if (!empty($p['min_block']) && $p['min_block'] > 1) {
                $blocks = (int) ceil($units / $p['min_block']);
                $subtotal = $blocks * $p['min_block'] * $unit;
            }

            if (!empty($p['multiplier']) && $p['multiplier'] != 1.0) {
                $subtotal = (int) round($subtotal * (float)$p['multiplier']);
            }

            if (!empty($p['cap_cents'])) {
                $subtotal = min($subtotal, (int) $p['cap_cents']);
            }

            $breakdown[] = [
                'from' => $p['from'],
                'to' => $p['to'],
                'units' => $units,
                'unit_price_cents' => $unit,
                'label' => $p['label'] ?? null,
                'subtotal_cents' => $subtotal,
            ];

            $sum += $subtotal;
        }

        $total = $base + match ($strategy) {
            'highest_tier_only' => self::highestTierOnlyTotal($breakdown),
            'weighted_average'  => self::weightedAverageTotal($breakdown),
            default             => $sum, // sum_piecewise
        };

        return [
            'total_cents'      => $total,
            'subtotal_cents'   => $sum,
            'base_fee_cents'   => $base,
            'breakdown'        => $breakdown,
        ];
    }

    protected static function splitByTiers(int $min, int $max, array $tiers): array
    {
        $ranges = [];
        foreach ($tiers as $t) {
            $f = (int) $t['from']; $to = (int) $t['to'];
            if ($to < $min || $f > $max) continue;
            $segFrom = max($min, $f);
            $segTo   = min($max, $to);
            $ranges[] = [
                'from' => $segFrom,
                'to' => $segTo,
                'unit_price_cents' => (int) $t['unit_price_cents'],
                'label' => $t['label'] ?? null,
                'min_block' => $t['min_block'] ?? null,
                'multiplier' => $t['multiplier'] ?? null,
                'cap_cents' => $t['cap_cents'] ?? null,
            ];
        }

        // если часть диапазона не покрыта никакой ступенью — считаем её с нулевой ценой
        $covered = 0;
        foreach ($ranges as $r) { $covered += ($r['to'] - $r['from'] + 1); }
        $span = $max - $min + 1;
        if ($covered < $span) {
            $ranges[] = [
                'from' => $min,
                'to'   => $max,
                'unit_price_cents' => 0,
                'label' => 'uncovered',
            ];
        }

        // склеивать/сортировать не обязательно, но порядок приятнее
        usort($ranges, fn($a,$b) => $a['from'] <=> $b['from']);
        return $ranges;
    }

    protected static function highestTierOnlyTotal(array $breakdown): int
    {
        $maxUnit = 0; $units = 0;
        foreach ($breakdown as $b) {
            if ($b['unit_price_cents'] > $maxUnit) {
                $maxUnit = $b['unit_price_cents'];
                $units = $b['to'] - $b['from'] + 1;
            } else {
                $units += $b['to'] - $b['from'] + 1;
            }
        }
        return $maxUnit * $units;
    }

    protected static function weightedAverageTotal(array $breakdown): int
    {
        $sum = 0;
        foreach ($breakdown as $b) $sum += $b['subtotal_cents'];
        return $sum;
    }
}