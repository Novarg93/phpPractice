<?php

namespace App\Services;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\GaProfile;
use Illuminate\Support\Facades\DB;

class UniqueD4Synchronizer
{
    public static function sync(OptionGroup $d4): void
    {
        DB::transaction(function () use ($d4) {
            // 1) режим и значения
            $pm = 'absolute';
            $ga = [0, 0, 0, 0];              // cents
            $gp = [null, null, null, null];  // percent

            if ($d4->unique_d4_is_global && $d4->ga_profile_id) {
                /** @var GaProfile|null $p */
                $p = $d4->gaProfile()->first();
                if ($p) {
                    $pm = in_array($p->pricing_mode, ['absolute', 'percent'], true) ? $p->pricing_mode : 'absolute';
                    $ga = [(int)$p->ga1_cents, (int)$p->ga2_cents, (int)$p->ga3_cents, (int)$p->ga4_cents];
                    $gp = [
                        self::f($p->ga1_percent),
                        self::f($p->ga2_percent),
                        self::f($p->ga3_percent),
                        self::f($p->ga4_percent),
                    ];
                }
            } else {
                $pm = in_array($d4->unique_d4_pricing_mode, ['absolute', 'percent'], true)
                    ? $d4->unique_d4_pricing_mode : 'absolute';

                $ga = [
                    (int) $d4->unique_d4_ga1_cents,
                    (int) $d4->unique_d4_ga2_cents,
                    (int) $d4->unique_d4_ga3_cents,
                    (int) $d4->unique_d4_ga4_cents,
                ];
                $gp = [
                    self::f($d4->unique_d4_ga1_percent),
                    self::f($d4->unique_d4_ga2_percent),
                    self::f($d4->unique_d4_ga3_percent),
                    self::f($d4->unique_d4_ga4_percent),
                ];
            }

            // 2) Группа GA (dropdown)
            $gaGroup = OptionGroup::firstOrNew([
                'product_id' => $d4->product_id,
                'code'       => 'ga',
            ]);

            $gaGroup->fill([
                'title'           => $gaGroup->exists ? $gaGroup->title : 'Greater Affixes',
                'type'            => OptionGroup::TYPE_SELECTOR,
                'selection_mode'  => OptionGroup::SEL_SINGLE,
                'ui_variant'      => 'dropdown',
                'is_required'     => true,
                'multiply_by_qty' => true,
                'pricing_mode'    => $pm,
                'position'        => $gaGroup->position ?? ($d4->position + 1),
            ]);
            $gaGroup->save();

            // 5 значений: Non GA + 1..4 GA
            $titles = ['Non GA', '1 GA', '2 GA', '3 GA', '4 GA'];
            foreach ($titles as $idx => $title) {
                $ov = OptionValue::firstOrNew([
                    'option_group_id' => $gaGroup->id,
                    'title'           => $title,
                ]);

                $ov->is_active  = true;
                $ov->is_default = ($idx === 0);
                $ov->position   = $idx;

                if ($pm === 'percent') {
                    $ov->delta_cents   = null;
                    $ov->delta_percent = $idx === 0 ? null : (self::f($gp[$idx - 1]) ?? 0.0);
                    $ov->price_delta_cents = null;
                    $ov->value_percent     = $ov->delta_percent;
                } else {
                    $ov->delta_cents       = $idx === 0 ? 0 : (int)($ga[$idx - 1] ?? 0);
                    $ov->delta_percent     = null;
                    $ov->price_delta_cents = $ov->delta_cents;
                    $ov->value_percent     = null;
                }

                // meta.ga_count — целое 0..4
                $meta = (array) ($ov->meta ?? []);
                $meta['ga_count'] = $idx;
                $ov->meta = $meta;

                $ov->save();
            }

            // подчистим лишние
            OptionValue::where('option_group_id', $gaGroup->id)
                ->whereNotIn('title', $titles)
                ->delete();

            // 3) Группа статов (4 чекбокса, цена 0)
            $labels = self::labels4($d4->unique_d4_labels);
            $statsGroup = OptionGroup::firstOrNew([
                'product_id' => $d4->product_id,
                'code'       => 'unique_d4_stats',
            ]);

            $statsGroup->fill([
                'title'           => $statsGroup->exists ? $statsGroup->title : 'Item attributes (choose GA)',
                'type'            => OptionGroup::TYPE_SELECTOR,
                'selection_mode'  => OptionGroup::SEL_MULTI,
                'ui_variant'      => 'list',
                'is_required'     => false,
                'multiply_by_qty' => false,
                'pricing_mode'    => 'absolute',
                'position'        => $statsGroup->position ?? ($gaGroup->position + 1),
            ]);
            $statsGroup->save();

            foreach ($labels as $i => $label) {
                $ov = OptionValue::firstOrNew([
                    'option_group_id' => $statsGroup->id,
                    'position'        => $i,
                ]);
                $ov->title             = $label;
                $ov->is_active         = true;
                $ov->is_default        = false;
                $ov->delta_cents       = 0;
                $ov->delta_percent     = null;
                $ov->price_delta_cents = 0;
                $ov->value_percent     = null;
                $ov->save();
            }

            OptionValue::where('option_group_id', $statsGroup->id)
                ->whereNotIn('position', [0, 1, 2, 3])
                ->delete();
        });
    }

    private static function labels4(?array $labels): array
    {
        $base = array_values(array_filter(array_map('strval', $labels ?? [])));
        while (count($base) < 4) $base[] = 'Attribute '.(count($base) + 1);
        return array_slice($base, 0, 4);
    }

    private static function f($v): ?float
    {
        if ($v === null || $v === '') return null;
        $n = (float) $v;
        return is_finite($n) ? $n : null;
    }
}