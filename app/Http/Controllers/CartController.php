<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Models\{
    Cart,
    CartItem,
    CartItemOption,

    Product,
    OptionValue,
    OptionGroup
};

class CartController extends Controller
{
    /* ====================== Helpers: common ====================== */

    private function isGuest(Request $r): bool
    {
        return !$r->user();
    }

    private function getUserCart(Request $r): Cart
    {
        return Cart::firstOrCreate(['user_id' => $r->user()->id]);
    }

    private function getGuestCart(Request $r): array
    {
        return $r->session()->get('guest_cart', []);
    }

    private function saveGuestCart(Request $r, array $items): void
    {
        $r->session()->put('guest_cart', array_values($items));
    }

    private function normalizeOptionIds($ids): array
    {
        return collect($ids ?? [])->unique()->sort()->values()->all();
    }

    private function productWithGroups(int $productId): Product
    {
        return Product::with(['optionGroups.values'])->findOrFail($productId);
    }

    /* ====================== Helpers: pricing ====================== */

    /** price = base + additive options (per-unit) + range delta (per-unit) */
    private function computeUnitPriceCents(int $productId, array $optionValueIds, array $rangeSelections): int
    {
        $product = $this->productWithGroups($productId);

        // 1) базовая цена
        $base = $product->price_cents;

        // 2) надбавки по value (radio/checkbox), НО только те, что multiply_by_qty=true
        $deltaValuesPerUnit = 0;
        $valueRows = OptionValue::whereIn('id', $optionValueIds)->get();
        foreach ($product->optionGroups as $g) {
            if (!in_array($g->type, [OptionGroup::TYPE_RADIO, OptionGroup::TYPE_CHECKBOX], true)) {
                continue;
            }
            if (!$g->multiply_by_qty) {
                continue; // эти прибавим к заказу, не к юниту
            }
            // соберём values для этой группы
            $valueRows->each(function ($v) use (&$deltaValuesPerUnit, $g) {
                if ($v->option_group_id === $g->id) {
                    $deltaValuesPerUnit += (int)$v->price_delta_cents;
                }
            });
        }

        // 3) надбавка по double_range_slider (пер-юнитная)
        $deltaRangePerUnit = $this->computeRangePerUnitDelta($product, $rangeSelections);

        return $base + $deltaValuesPerUnit + $deltaRangePerUnit;
    }

    /** piecewise / highest / weighted */
    private function computeRangePerUnitDelta(Product $product, array $rangeSelections): int
    {
        $sum = 0;

        // ожидаем массив элементов: ['option_group_id'=>int, 'selected_min'=>int, 'selected_max'=>int]
        foreach ($rangeSelections as $sel) {
            $g = $product->optionGroups
                ->first(fn($gg) => $gg->id === (int)$sel['option_group_id'] && $gg->type === OptionGroup::TYPE_RANGE);

            if (!$g) continue;

            $min = max((int)$g->slider_min, (int)$sel['selected_min']);
            $max = min((int)$g->slider_max, (int)$sel['selected_max']);
            $step = max(1, (int)$g->slider_step);

            // нормализуем и снапаем
            $min = $this->snapToStep($min, (int)$g->slider_min, $step);
            $max = $this->snapToStep($max, (int)$g->slider_min, $step);
            if ($min > $max) [$min, $max] = [$max, $min];

            // шаги считаем как "сколько уровней пройти" (exclusive min)
            $span = max(0, $max - $min);

            if ($span === 0) {
                $sum += 0;
                continue;
            }

            $pricingMode = $g->pricing_mode ?? 'flat';

            if ($pricingMode === 'flat') {
                $unit = (int)($g->unit_price_cents ?? 0);
                $sum += $this->applyBlocksAndCaps($span, $unit, [
                    'min_block' => null,
                    'multiplier' => null,
                    'cap_cents' => null,
                ]);
            } elseif ($pricingMode === 'tiered') {
                $tiers = is_array($g->tiers_json)
                    ? $g->tiers_json
                    : (json_decode((string) $g->tiers_json, true) ?: []);
                $strategy = $g->tier_combine_strategy ?: 'sum_piecewise';
                $baseFee = (int)($g->base_fee_cents ?? 0);
                $maxSpan = $g->max_span ? (int)$g->max_span : null;

                if ($maxSpan !== null && $span > $maxSpan) {
                    abort(422, 'Selected range exceeds maximum allowed span.');
                }

                $sum += $baseFee;
                $sum += $this->priceTiered($min, $max, $g->slider_min, $tiers, $strategy);
            }
        }

        return (int)$sum;
    }

    private function computeUnitAndTotalCents(int $productId, array $optionValueIds, array $rangeSelections, int $qty): array
    {
        $product   = $this->productWithGroups($productId);
        $groups    = $product->optionGroups->keyBy('id');      // [group_id => OptionGroup]
        $values    = \App\Models\OptionValue::whereIn('id', $optionValueIds)->get();

        // --- агрегаторы ---
        $addUnitAbs   = 0;   // аддитив к юниту
        $mulUnit      = 1.0; // проценты к юниту (мультипликативно)
        $addTotalAbs  = 0;   // аддитив к итогу позиции
        $mulTotal     = 1.0; // проценты к итогу позиции (мультипликативно)

        foreach ($values as $v) {
            $g = $groups->get($v->option_group_id);
            if (!$g) continue;

            $isPerUnit = (bool) $g->multiply_by_qty;

            // 0) Новый SELECTOR
            if (in_array($g->type, [\App\Models\OptionGroup::TYPE_SELECTOR, 'selector'], true)) {
                $mode = $g->pricing_mode === 'percent' ? 'percent' : 'absolute';

                if ($mode === 'percent') {
                    $p = (float) ($v->delta_percent ?? $v->value_percent ?? 0.0);
                    $factor = 1.0 + ($p / 100.0);
                    if ($isPerUnit) $mulUnit  *= $factor;
                    else            $mulTotal *= $factor;
                } else {
                    $delta = (int) ($v->delta_cents ?? $v->price_delta_cents ?? 0);
                    if ($isPerUnit) $addUnitAbs  += $delta;
                    else            $addTotalAbs += $delta;
                }
                continue;
            }

            // 1) Легаси аддитив
            if (in_array($g->type, [
                \App\Models\OptionGroup::TYPE_RADIO,
                \App\Models\OptionGroup::TYPE_CHECKBOX,
            ], true)) {
                $delta = (int) ($v->price_delta_cents ?? 0);
                if ($isPerUnit) $addUnitAbs  += $delta;
                else            $addTotalAbs += $delta;
                continue;
            }

            // 2) Легаси проценты
            if (in_array($g->type, [
                \App\Models\OptionGroup::TYPE_RADIO_PERCENT,
                \App\Models\OptionGroup::TYPE_CHECKBOX_PERCENT,
            ], true)) {
                $p = (float) ($v->value_percent ?? 0.0);
                $factor = 1.0 + ($p / 100.0);
                if ($isPerUnit) $mulUnit  *= $factor;
                else            $mulTotal *= $factor;
                continue;
            }
        }

        // per-unit delta для range
        $deltaRangePerUnit = $this->computeRangePerUnitDelta($product, $rangeSelections);

        // --- unit price ---
        $unitBase = (int)$product->price_cents + $addUnitAbs + $deltaRangePerUnit;
        $unit     = (int) round($unitBase * $mulUnit);

        // --- total line ---
        $subtotal       = $unit * max(1, $qty);
        $beforePercent  = $subtotal + $addTotalAbs;
        $lineTotal      = (int) round($beforePercent * $mulTotal);

        return [
            'unit'       => $unit,
            'line_total' => $lineTotal,
            'breakdown'  => [
                'base'            => (int)$product->price_cents,
                'addUnitAbs'      => $addUnitAbs,
                'mulUnit'         => $mulUnit,
                'rangePerUnit'    => $deltaRangePerUnit,
                'qty'             => $qty,
                'addTotalAbs'     => $addTotalAbs,
                'mulTotal'        => $mulTotal,
            ],
        ];
    }

    /** piecewise ценообразование по тировым интервалам */
    private function priceTiered(int $selMin, int $selMax, int $baseMin, array $tiers, string $strategy): int
    {
        // нормализуем тиеры: сортировка по from, clamp
        $tiers = collect($tiers)
            ->map(function ($t) {
                return [
                    'from'             => (int)($t['from'] ?? 0),
                    'to'               => (int)($t['to'] ?? 0),
                    'unit_price_cents' => (int)($t['unit_price_cents'] ?? 0),
                    'min_block'        => isset($t['min_block']) ? (int)$t['min_block'] : null,
                    'multiplier'       => isset($t['multiplier']) ? (float)$t['multiplier'] : null,
                    'cap_cents'        => isset($t['cap_cents']) ? (int)$t['cap_cents'] : null,
                ];
            })
            ->sortBy('from')
            ->values()
            ->all();

        $spanTotal = max(0, $selMax - $selMin);

        if ($spanTotal === 0) return 0;

        // Посчитаем долю по каждому тиру
        $piecewise = 0;
        $highestUnit = 0;
        $weightedSum = 0;

        foreach ($tiers as $t) {
            $from = max($t['from'], $selMin);
            $to   = min($t['to'],   $selMax);
            if ($to <= $from) continue;

            $steps = $to - $from; // exclusive min

            $unit = $t['unit_price_cents'];
            if ($t['multiplier']) {
                $unit = (int)round($unit * (float)$t['multiplier']);
            }

            // min_block — округляем до кратности блока вверх
            if ($t['min_block']) {
                $steps = (int)ceil($steps / (int)$t['min_block']) * (int)$t['min_block'];
            }

            $cost = $unit * $steps;
            if ($t['cap_cents'] !== null) {
                $cost = min($cost, (int)$t['cap_cents']);
            }

            $piecewise += $cost;
            $highestUnit = max($highestUnit, $unit);
            $weightedSum += $unit * ($to - $from);
        }

        return match ($strategy) {
            'highest_tier_only' => $highestUnit * $spanTotal,
            'weighted_average'  => (int)round(($spanTotal > 0 ? $weightedSum / $spanTotal : 0) * $spanTotal),
            default             => $piecewise, // sum_piecewise
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

        if ($minBlock) {
            $steps = (int)ceil($steps / (int)$minBlock) * (int)$minBlock;
        }
        if ($mult) {
            $unit = (int)round($unit * (float)$mult);
        }
        $cost = $unit * $steps;
        if ($cap !== null) {
            $cost = min($cost, (int)$cap);
        }
        return $cost;
    }

    private function summaryPayload(Request $request): array
    {
        // можно вызвать существующий метод summary() и получить массив
        return app()->call([$this, 'summary'], ['request' => $request]);
    }


    /* ====================== Helpers: validation ====================== */

    private function validateSelection(int $productId, array $optionValueIds): void
    {
        $product = $this->productWithGroups($productId);
        $chosen  = collect($optionValueIds);

        // (A) каждый value относится к продукту
        foreach ($chosen as $vid) {
            $belongs = $product->optionGroups->first(fn($g) => $g->values->contains('id', $vid));
            abort_unless($belongs, 422, 'Invalid option value selected.');
        }

        // (B) правила по типам
        foreach ($product->optionGroups as $g) {
            // >>> NEW: selector (single/multi)
            if (in_array($g->type, [\App\Models\OptionGroup::TYPE_SELECTOR, 'selector'], true)) {
                $selected = $chosen->filter(fn($vid) => $g->values->contains('id', $vid));
                $single = (($g->selection_mode ?? 'single') !== 'multi');

                if ($single && $selected->count() > 1) {
                    abort(422, 'Only one option can be selected in "' . $g->title . '".');
                }

                if ($g->is_required) {
                    if ($single && $selected->count() !== 1) {
                        abort(422, '"' . $g->title . '" is required.');
                    }
                    if (! $single && $selected->count() < 1) {
                        abort(422, 'Select at least one in "' . $g->title . '".');
                    }
                }

                continue;
            }

            // single (legacy radio / radio_percent)
            if (in_array($g->type, [
                \App\Models\OptionGroup::TYPE_RADIO,
                \App\Models\OptionGroup::TYPE_RADIO_PERCENT,
            ], true)) {
                $selected = $chosen->filter(fn($vid) => $g->values->contains('id', $vid));
                if ($selected->count() > 1) {
                    abort(422, 'Only one option can be selected in "' . $g->title . '".');
                }
                if ($g->is_required && $selected->count() !== 1) {
                    abort(422, '"' . $g->title . '" is required.');
                }
            }
            // multi (legacy checkbox / checkbox_percent)
            elseif (in_array($g->type, [
                \App\Models\OptionGroup::TYPE_CHECKBOX,
                \App\Models\OptionGroup::TYPE_CHECKBOX_PERCENT,
            ], true)) {
                $selected = $chosen->filter(fn($vid) => $g->values->contains('id', $vid));
                if ($g->is_required && $selected->count() < 1) {
                    abort(422, 'Select at least one in "' . $g->title . '".');
                }
            }

            // quantity_slider и double_range_slider валидируем отдельно (как и было)
        }
    }

    private function validateAndResolveQty(int $productId, ?int $qtyFromRequest): int
    {
        $g = OptionGroup::where('product_id', $productId)
            ->where('type', OptionGroup::TYPE_SLIDER)
            ->first();

        if (!$g) {
            return max(1, (int)($qtyFromRequest ?? 1));
        }

        $min  = $g->qty_min  ?? 1;
        $max  = $g->qty_max  ?? PHP_INT_MAX;
        $step = max(1, (int)($g->qty_step ?? 1));
        $def  = $g->qty_default ?? $min;
        $q    = $qtyFromRequest ?? $def;

        if ($g->is_required && ($q === null)) {
            abort(422, '"' . $g->title . '" is required.');
        }
        if ($q < $min || $q > $max) {
            abort(422, 'Quantity must be between ' . $min . ' and ' . $max . '.');
        }
        if (($q - $min) % $step !== 0) {
            abort(422, 'Invalid quantity step for "' . $g->title . '".');
        }
        return (int)$q;
    }

    private function validateRangeSelections(int $productId, array $rangeSelections): array
    {
        $product = $this->productWithGroups($productId);

        // нормализуем массив (мог прийти null/пустой)
        $list = collect($rangeSelections ?? [])
            ->map(function ($row) {
                return [
                    'option_group_id' => (int)($row['option_group_id'] ?? 0),
                    'selected_min'    => (int)($row['selected_min'] ?? 0),
                    'selected_max'    => (int)($row['selected_max'] ?? 0),
                ];
            })
            ->values();

        // пробежимся по всем range-группам продукта
        foreach ($product->optionGroups as $g) {
            if ($g->type !== OptionGroup::TYPE_RANGE) continue;

            $sel = $list->firstWhere('option_group_id', $g->id);

            if ($g->is_required && !$sel) {
                abort(422, '"' . $g->title . '" is required.');
            }
            if (!$sel) continue;

            $min = (int)$g->slider_min;
            $max = (int)$g->slider_max;
            $step = max(1, (int)$g->slider_step);

            // границы
            if (
                $sel['selected_min'] < $min || $sel['selected_min'] > $max ||
                $sel['selected_max'] < $min || $sel['selected_max'] > $max
            ) {
                abort(422, 'Selected range for "' . $g->title . '" is out of bounds.');
            }

            // снап к шагу (проверка)
            if ((($sel['selected_min'] - $min) % $step) !== 0 ||
                (($sel['selected_max'] - $min) % $step) !== 0
            ) {
                abort(422, 'Invalid step for "' . $g->title . '".');
            }

            // пустой диапазон ок (0), но если бизнес-требование: запретить, раскомментируй
            // if ($sel['selected_max'] <= $sel['selected_min']) {
            //     abort(422, 'Invalid range for "'.$g->title.'".');
            // }

            // ограничение по max_span (если настроено)
            if ($g->pricing_mode === 'tiered' && $g->max_span) {
                $span = max(0, $sel['selected_max'] - $sel['selected_min']);
                if ($span > (int)$g->max_span) {
                    abort(422, 'Selected range exceeds maximum span for "' . $g->title . '".');
                }
            }
        }

        return $list->all();
    }

    /* ====================== Pages ====================== */

    public function index(Request $request)
    {
        if ($this->isGuest($request)) {
            $items = $this->getGuestCart($request);

            $totalQty = count($items);
            $totalSum = collect($items)->sum('line_total_cents');

            return Inertia::render('Cart/Index', [
                'items' => collect($items)->map(function ($i) {
                    $p = \App\Models\Product::with('optionGroups')->find($i['product_id']);

                    $hasQtySlider = (bool) ($p?->optionGroups
                        ->contains('type', \App\Models\OptionGroup::TYPE_SLIDER) ?? false);

                    // ⬇️ подписи диапазонов
                    $rangeLabels = collect($i['range_options'] ?? [])
                        ->map(fn($r) => ((int)$r['selected_min']) . '-' . ((int)$r['selected_max']))
                        ->values()
                        ->all();

                    // ⬇️ выбранные option values (аддитив/процент)
                    $vals = \App\Models\OptionValue::with('group')
                        ->whereIn('id', $i['option_value_ids'] ?? [])
                        ->get()
                        ->filter(fn($v) => $v->group) // защита
                        ->sortBy([
                            fn($v) => $v->group->position ?? 0,
                            fn($v) => $v->position ?? 0,
                        ]);

                    $optionLabels = $vals->map(function ($v) {
                        $g = $v->group;
                        $isPercent = in_array($g->type ?? null, [
                            \App\Models\OptionGroup::TYPE_RADIO_PERCENT,
                            \App\Models\OptionGroup::TYPE_CHECKBOX_PERCENT,
                        ], true);

                        return [
                            'id'            => $v->id,
                            'title'         => $v->title,
                            'calc_mode'     => $isPercent ? 'percent' : 'absolute',
                            'scope'         => ($g->multiply_by_qty ?? false) ? 'unit' : 'total',
                            'value_cents'   => (int) $v->price_delta_cents,
                            'value_percent' => $v->value_percent !== null ? (float)$v->value_percent : null,
                        ];
                    })->values()->all();

                    return [
                        'id' => $i['id'],
                        'product' => [
                            'id' => $p?->id,
                            'name' => $p?->name ?? 'Unknown',
                            'image_url' => $p?->image_url,
                        ],
                        'qty' => $i['qty'],
                        'unit_price_cents' => $i['unit_price_cents'],
                        'line_total_cents' => $i['line_total_cents'],
                        'range_labels' => $rangeLabels,
                        'options' => $optionLabels,
                        'has_qty_slider' => $hasQtySlider,
                    ];
                })->values(),
                'total_qty' => $totalQty,
                'total_sum_cents' => $totalSum,
            ]);
        }

        $cart = $this->getUserCart($request)->load([
            'items.product.optionGroups',        // 👈 добавили
            'items.options.optionValue.group',
        ]);

        return Inertia::render('Cart/Index', [
            'items' => $cart->items->map(function ($item) {
                $hasQtySlider = (bool) $item->product->optionGroups
                    ->contains('type', \App\Models\OptionGroup::TYPE_SLIDER);
                $rangeLabels = $item->options
                    ->filter(fn($o) => !is_null($o->option_group_id))
                    ->map(fn($o) => ((int)$o->selected_min) . '-' . ((int)$o->selected_max))
                    ->values()
                    ->all();

                // ⬇️ опции из option_value_id (аддитив/процент)
                $optionLabels = $item->options
                    ->filter(fn($o) => $o->option_value_id && $o->optionValue && $o->optionValue->group)
                    ->sortBy([
                        fn($o) => $o->optionValue->group->position ?? 0,
                        fn($o) => $o->optionValue->position ?? 0,
                    ])
                    ->map(function ($o) {
                        $v = $o->optionValue;
                        $g = $v->group;
                        $isPercent = in_array($g->type ?? null, [
                            \App\Models\OptionGroup::TYPE_RADIO_PERCENT,
                            \App\Models\OptionGroup::TYPE_CHECKBOX_PERCENT,
                        ], true);

                        return [
                            'id'            => $v->id,
                            'title'         => $v->title,
                            'calc_mode'     => $isPercent ? 'percent' : 'absolute',
                            'scope'         => ($g->multiply_by_qty ?? false) ? 'unit' : 'total',
                            'value_cents'   => (int) $v->price_delta_cents,
                            'value_percent' => $v->value_percent !== null ? (float)$v->value_percent : null,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $item->id,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'image_url' => $item->product->image_url,
                    ],
                    'qty' => $item->qty,
                    'unit_price_cents' => $item->unit_price_cents,
                    'line_total_cents' => $item->line_total_cents,
                    'range_labels' => $rangeLabels,
                    'options' => $optionLabels,
                    'has_qty_slider' => $hasQtySlider,
                ];
            })->values(),
            'total_qty' => $cart->items->count(),
            'total_sum_cents' => $cart->items->sum('line_total_cents'),
        ]);
    }

    /* ====================== API ====================== */

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id'         => ['required', 'integer', 'exists:products,id'],
            'qty'                => ['nullable', 'integer', 'min:1'],
            'option_value_ids'   => ['array'],
            'option_value_ids.*' => ['integer', 'exists:option_values,id'],
            'range_options'      => ['array'],
            'range_options.*.option_group_id' => ['required', 'integer', 'exists:option_groups,id'],
            'range_options.*.selected_min'    => ['required', 'integer'],
            'range_options.*.selected_max'    => ['required', 'integer'],
        ]);

        $optionIds = $this->normalizeOptionIds($data['option_value_ids'] ?? []);
        $this->validateSelection($data['product_id'], $optionIds);

        $rangeList = $this->validateRangeSelections($data['product_id'], $data['range_options'] ?? []);
        $qty = $this->validateAndResolveQty($data['product_id'], $data['qty'] ?? null);

        // ⬇️ новый расчёт (unit + total с %)
        $pricing = $this->computeUnitAndTotalCents(
            $data['product_id'],
            $optionIds,
            $rangeList,
            $qty
        );

        if ($this->isGuest($request)) {
            $items = $this->getGuestCart($request);

            $items[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'product_id'        => $data['product_id'],
                'qty'               => $qty,
                'unit_price_cents'  => $pricing['unit'],
                'line_total_cents'  => $pricing['line_total'],     // 👈 уже с per-order и % на итог
                'option_value_ids'  => $optionIds,
                'range_options'     => $rangeList,
            ];

            $this->saveGuestCart($request, $items);
            return response()->json([
                'ok' => true,
                'summary' => $this->summaryPayload($request),
            ]);
        }

        // auth user → в БД
        $cart = $this->getUserCart($request);

        $new = $cart->items()->create([
            'product_id'        => $data['product_id'],
            'qty'               => $qty,
            'unit_price_cents'  => $pricing['unit'],
            'line_total_cents'  => $pricing['line_total'],        // 👈 уже с per-order и %
        ]);

        // сохраним выбранные option_values (и аддитивные, и процентные)
        foreach ($optionIds as $vid) {
            $new->options()->create(['option_value_id' => $vid]);
        }

        // сохраним диапазоны (как и раньше)
        $productLoaded = $this->productWithGroups($data['product_id']);
        foreach ($rangeList as $row) {
            $deltaForOne = $this->computeRangePerUnitDelta($productLoaded, [$row]); // per-unit delta

            $new->options()->create([
                'option_value_id'   => null, // признак: диапазонная запись
                'option_group_id'   => $row['option_group_id'],
                'selected_min'      => $row['selected_min'],
                'selected_max'      => $row['selected_max'],
                'price_delta_cents' => $deltaForOne,
                'payload_json'      => [
                    'pricing_mode'          => $productLoaded->optionGroups
                        ->firstWhere('id', $row['option_group_id'])->pricing_mode ?? null,
                    'tier_combine_strategy' => $productLoaded->optionGroups
                        ->firstWhere('id', $row['option_group_id'])->tier_combine_strategy ?? null,
                    'tiers'                 => is_array($productLoaded->optionGroups
                        ->firstWhere('id', $row['option_group_id'])->tiers_json)
                        ? $productLoaded->optionGroups
                        ->firstWhere('id', $row['option_group_id'])->tiers_json
                        : (json_decode((string)($productLoaded->optionGroups
                            ->firstWhere('id', $row['option_group_id'])->tiers_json), true) ?: []),
                ],
            ]);
        }

        return response()->json([
            'ok' => true,
            'summary' => $this->summaryPayload($request),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'item_id' => ['required'],
            'qty'     => ['required', 'integer', 'min:1'],
        ]);

        if ($this->isGuest($request)) {
            $items = $this->getGuestCart($request);
            $i = collect($items)->firstWhere('id', $data['item_id']);
            abort_if(!$i, 404);

            $i['qty'] = (int)$data['qty'];
            $i['line_total_cents'] = $i['unit_price_cents'] * $i['qty'];

            $items = collect($items)->map(fn($row) => $row['id'] === $i['id'] ? $i : $row)->values()->all();
            $this->saveGuestCart($request, $items);

            return response()->json([
                'ok' => true,
                'summary' => $this->summaryPayload($request),
            ]);
        }

        $item = CartItem::findOrFail($data['item_id']);
        $item->qty = (int)$data['qty'];
        $item->line_total_cents = $item->unit_price_cents * $item->qty;
        $item->save();

        return response()->json([
            'ok' => true,
            'summary' => $this->summaryPayload($request),
        ]);
    }

    public function remove(Request $request)
    {
        $data = $request->validate(['item_id' => ['required']]);

        if ($this->isGuest($request)) {
            $items = $this->getGuestCart($request);
            $items = collect($items)->reject(fn($i) => $i['id'] === $data['item_id'])->values()->all();
            $this->saveGuestCart($request, $items);
            return response()->json([
                'ok' => true,
                'summary' => $this->summaryPayload($request),
            ]);
        }

        $item = CartItem::findOrFail($data['item_id']);
        // удалим «диапазонные» строки из cart_item_options (они с option_value_id = null)
        $item->options()->whereNull('option_value_id')->delete();
        $item->delete();

        return response()->json([
            'ok' => true,
            'summary' => $this->summaryPayload($request),
        ]);
    }

    public function summary(Request $request)
    {
        if ($this->isGuest($request)) {
            $items = $this->getGuestCart($request);
            return [
                'total_qty'       => count($items),
                'total_sum_cents' => collect($items)->sum('line_total_cents'),
            ];
        }

        $cart = $this->getUserCart($request)->load('items');
        return [
            'total_qty'       => $cart->items->count(),
            'total_sum_cents' => $cart->items->sum('line_total_cents'),
        ];
    }
}
