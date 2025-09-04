<?php

namespace App\Http\Controllers;

use App\Services\Cart\CartPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\{Cart, CartItem};

class CartController extends Controller
{
    public function __construct(private CartPricing $pricing) {}

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

    private function summaryPayload(Request $request): array
    {
        return app()->call([$this, 'summary'], ['request' => $request]);
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
                    $guestGaSet = collect($i['affix_ga_ids'] ?? [])->map(fn($v) => (int)$v)->unique();
                    $hasQtySlider = (bool) ($p?->optionGroups
                        ->contains('type', \App\Models\OptionGroup::TYPE_SLIDER) ?? false);

                    $rangeLabels = collect($i['range_options'] ?? [])
                        ->map(fn($r) => ((int)$r['selected_min']) . '-' . ((int)$r['selected_max']))
                        ->values()
                        ->all();

                    $vals = \App\Models\OptionValue::with('group')
                        ->whereIn('id', $i['option_value_ids'] ?? [])
                        ->get()
                        ->filter(fn($v) => $v->group)
                        ->sortBy(function ($v) {
                            $g = $v->group;
                            $priority = match ($g->code ?? null) {
                                'class' => 0,
                                'slot'  => 1,
                                'affix' => 2,
                                default => 100,
                            };
                            // составной числовой ключ: приоритет → позиция группы → позиция значения
                            return $priority * 1_000_000
                                + (int)($g->position ?? 0) * 1_000
                                + (int)($v->position ?? 0);
                        });

                    $optionLabels = $vals->map(function ($v) use ($guestGaSet) {
                        $g = $v->group;

                        // режим значения
                        $mode = 'absolute';
                        $valueCents = null;
                        $valuePercent = null;

                        if (($g->type ?? null) === \App\Models\OptionGroup::TYPE_SELECTOR || ($g->type ?? null) === 'selector') {
                            $mode = ($g->pricing_mode === 'percent') ? 'percent' : 'absolute';
                            if ($mode === 'percent') {
                                $valuePercent = (float)($v->delta_percent ?? $v->value_percent ?? 0);
                            } else {
                                $valueCents = (int)($v->delta_cents ?? $v->price_delta_cents ?? 0);
                            }
                        } elseif (in_array($g->type ?? null, [
                            \App\Models\OptionGroup::TYPE_RADIO_PERCENT,
                            \App\Models\OptionGroup::TYPE_CHECKBOX_PERCENT,
                        ], true)) {
                            $mode = 'percent';
                            $valuePercent = (float)($v->value_percent ?? 0);
                        } else {
                            $mode = 'absolute';
                            $valueCents = (int)($v->price_delta_cents ?? 0);
                        }

                        // вычисляем scope корректно и локально
                        $isUnit = ($g->multiply_by_qty === null) ? true : (bool) $g->multiply_by_qty;

                        return [
                            'id'            => $v->id,
                            'title'         => $v->title,
                            'calc_mode'     => $mode,              // absolute | percent
                            'scope'         => $isUnit ? 'unit' : 'total',
                            'value_cents'   => $valueCents,
                            'value_percent' => $valuePercent,
                            'is_ga'         => $guestGaSet->contains($v->id),
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
            'items.product.optionGroups',
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

                $optionLabels = $item->options
                    ->filter(fn($o) => $o->option_value_id && $o->optionValue && $o->optionValue->group)
                    ->sortBy(function ($o) {
                        $v = $o->optionValue;
                        $g = $v->group;
                        $priority = match ($g->code ?? null) {
                            'class' => 0,
                            'slot'  => 1,
                            'affix' => 2,
                            default => 100,
                        };
                        return $priority * 1_000_000
                            + (int)($g->position ?? 0) * 1_000
                            + (int)($v->position ?? 0);
                    })
                    ->map(function ($o) {
                        $v = $o->optionValue;
                        $g = $v->group;

                        $mode = 'absolute';
                        $valueCents = null;
                        $valuePercent = null;

                        if (($g->type ?? null) === \App\Models\OptionGroup::TYPE_SELECTOR || ($g->type ?? null) === 'selector') {
                            $mode = ($g->pricing_mode === 'percent') ? 'percent' : 'absolute';
                            if ($mode === 'percent') {
                                $valuePercent = (float)($v->delta_percent ?? $v->value_percent ?? 0);
                            } else {
                                $valueCents = (int)($v->delta_cents ?? $v->price_delta_cents ?? 0);
                            }
                        } elseif (in_array($g->type ?? null, [
                            \App\Models\OptionGroup::TYPE_RADIO_PERCENT,
                            \App\Models\OptionGroup::TYPE_CHECKBOX_PERCENT,
                        ], true)) {
                            $mode = 'percent';
                            $valuePercent = (float)($v->value_percent ?? 0);
                        } else {
                            $mode = 'absolute';
                            $valueCents = (int)($v->price_delta_cents ?? 0);
                        }

                        $isUnit = ($g->multiply_by_qty === null) ? true : (bool) $g->multiply_by_qty;

                        return [
                            'id'            => $v->id,
                            'title'         => $v->title,
                            'calc_mode'     => $mode,
                            'scope'         => $isUnit ? 'unit' : 'total',
                            'value_cents'   => $valueCents,
                            'value_percent' => $valuePercent,
                            'is_ga'         => (bool) $o->is_ga
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
            'product_id'                  => ['required', 'integer', 'exists:products,id'],
            'qty'                         => ['nullable', 'integer', 'min:1'],
            'option_value_ids'            => ['array'],
            'option_value_ids.*'          => ['integer', 'exists:option_values,id'],
            'range_options'               => ['array'],
            'range_options.*.option_group_id' => ['required', 'integer', 'exists:option_groups,id'],
            'range_options.*.selected_min'    => ['required', 'integer'],
            'range_options.*.selected_max'    => ['required', 'integer'],
            'affix_ga_ids'                => ['array'],
            'affix_ga_ids.*'              => ['integer', 'exists:option_values,id'],
        ]);

        $optionIds = $this->normalizeOptionIds($data['option_value_ids'] ?? []);
        $this->pricing->validateSelection($data['product_id'], $optionIds);

        $rangeList = $this->pricing->validateRangeSelections($data['product_id'], $data['range_options'] ?? []);
        $qty       = $this->pricing->validateAndResolveQty($data['product_id'], $data['qty'] ?? null);

        $affixGaIds = collect($data['affix_ga_ids'] ?? [])
            ->map(fn($v) => (int)$v)
            ->unique()
            ->values();

        // --- PRE-VALIDATE GA
        $product = $this->pricing->productWithGroups($data['product_id']);

        // Найдём affix-группу и проверим, что все GA входят в выбранные аффиксы
        $affixGroup = optional($product->optionGroups)->firstWhere(function ($g) {
            $t = mb_strtolower((string)($g->title ?? ''));
            $c = mb_strtolower((string)($g->code ?? ''));
            return $c === 'affix' || str_contains($t, 'affix') || str_contains($t, 'аффикс') || str_contains($t, 'характеристик');
        });

        $allowedAffixIds = collect($affixGroup?->values ?? [])->pluck('id')->map(fn($v) => (int)$v)->all();
        $invalidGa = $affixGaIds->reject(fn($vid) => in_array($vid, $optionIds, true) && in_array($vid, $allowedAffixIds, true));
        abort_if($invalidGa->isNotEmpty(), 422, 'Invalid GA selection.');

        // Жёстко контролируем лимит по GA single-selector (если он есть)
        $gaGroup = optional($product->optionGroups)->firstWhere(
            fn($g) =>
            mb_strtolower((string)($g->code ?? '')) === 'ga'
                || (bool)preg_match('/\b(ga|greater)\b/i', (string)($g->title ?? ''))
        );

        if ($gaGroup) {
            $selectedGaValueId = collect($gaGroup->values ?? [])->pluck('id')->intersect($optionIds)->first();
            if ($selectedGaValueId) {
                $gaValue = collect($gaGroup->values)->firstWhere('id', $selectedGaValueId);

                // 1) meta.ga_count
                $limit = null;
                $raw = $gaValue->meta['ga_count'] ?? null;
                if (is_numeric($raw)) {
                    $limit = (int)$raw;
                } else {
                    // 2) fallback: цифра в title (например "2 GA")
                    if (preg_match('/(\d+)/', (string)($gaValue->title ?? ''), $m)) {
                        $limit = (int)$m[1];
                    }
                }
                $limit = max(0, min(3, (int)($limit ?? 0)));
                abort_if($affixGaIds->count() > $limit, 422, 'GA count exceeds selected limit.');
            }
        }
        // --- END PRE-VALIDATE GA

        // Считаем цену
        $pricing = $this->pricing->computeUnitAndTotalCents(
            $data['product_id'],
            $optionIds,
            $rangeList,
            $qty
        );

        // Гость: складываем в сессию
        if ($this->isGuest($request)) {
            $items = $this->getGuestCart($request);
            $items[] = [
                'id'                => (string) \Illuminate\Support\Str::uuid(),
                'product_id'        => $data['product_id'],
                'qty'               => $qty,
                'unit_price_cents'  => $pricing['unit'],
                'line_total_cents'  => $pricing['line_total'],
                'option_value_ids'  => $optionIds,
                'range_options'     => $rangeList,
                'affix_ga_ids'      => $affixGaIds->all(),
            ];
            $this->saveGuestCart($request, $items);
            return response()->json(['ok' => true, 'summary' => $this->summaryPayload($request)]);
        }

        // Авторизованный: всё под транзакцией, но без "return" из неё
        DB::transaction(function () use ($request, $data, $pricing, $optionIds, $rangeList, $affixGaIds, $product) {
            $cart = $this->getUserCart($request);

            $new = $cart->items()->create([
                'product_id'       => $data['product_id'],
                'qty'              => $qty = ($pricing['qty'] ?? null) ?: ($data['qty'] ?? 1), // но лучше: просто $qty извне
                'unit_price_cents' => $pricing['unit'],
                'line_total_cents' => $pricing['line_total'],
            ]);

            // Лучше явно: $new->qty = $qty; $new->save();
            $new->update(['qty' => $qty]); // чтобы точно положить корректное значение

            foreach ($optionIds as $vid) {
                $new->options()->create([
                    'option_value_id' => $vid,
                    'is_ga'           => $affixGaIds->contains((int)$vid),
                ]);
            }

            foreach ($rangeList as $row) {
                $deltaForOne = $this->pricing->computeRangePerUnitDelta($product, [$row]);
                $group = $product->optionGroups->firstWhere('id', $row['option_group_id']);

                $new->options()->create([
                    'option_value_id'   => null,
                    'option_group_id'   => $row['option_group_id'],
                    'selected_min'      => $row['selected_min'],
                    'selected_max'      => $row['selected_max'],
                    'price_delta_cents' => $deltaForOne,
                    'payload_json'      => [
                        'pricing_mode'          => $group->pricing_mode ?? null,
                        'tier_combine_strategy' => $group->tier_combine_strategy ?? null,
                        'tiers'                 => is_array($group->tiers_json)
                            ? $group->tiers_json
                            : (json_decode((string)$group->tiers_json, true) ?: []),
                    ],
                ]);
            }
        });

        return response()->json(['ok' => true, 'summary' => $this->summaryPayload($request)]);
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

            $pricing = $this->pricing->computeUnitAndTotalCents(
                $i['product_id'],
                $i['option_value_ids'] ?? [],
                $i['range_options'] ?? [],
                (int)$data['qty']
            );

            $i['qty']               = (int)$data['qty'];
            $i['unit_price_cents']  = $pricing['unit'];
            $i['line_total_cents']  = $pricing['line_total'];

            $items = collect($items)->map(fn($row) => $row['id'] === $i['id'] ? $i : $row)->values()->all();
            $this->saveGuestCart($request, $items);

            return response()->json(['ok' => true, 'summary' => $this->summaryPayload($request)]);
        }

        $item = CartItem::with(['product', 'options'])->findOrFail($data['item_id']);

        $valueIds = $item->options->pluck('option_value_id')->filter()->values()->all();
        $ranges = $item->options
            ->filter(fn($o) => !is_null($o->option_group_id))
            ->map(fn($o) => [
                'option_group_id' => (int)$o->option_group_id,
                'selected_min'    => (int)$o->selected_min,
                'selected_max'    => (int)$o->selected_max,
            ])
            ->values()
            ->all();

        $pricing = $this->pricing->computeUnitAndTotalCents(
            $item->product_id,
            $valueIds,
            $ranges,
            (int)$data['qty']
        );

        $item->qty               = (int)$data['qty'];
        $item->unit_price_cents  = $pricing['unit'];
        $item->line_total_cents  = $pricing['line_total'];
        $item->save();

        return response()->json(['ok' => true, 'summary' => $this->summaryPayload($request)]);
    }

    public function remove(Request $request)
    {
        $data = $request->validate(['item_id' => ['required']]);

        if ($this->isGuest($request)) {
            $items = $this->getGuestCart($request);
            $items = collect($items)->reject(fn($i) => $i['id'] === $data['item_id'])->values()->all();
            $this->saveGuestCart($request, $items);
            return response()->json(['ok' => true, 'summary' => $this->summaryPayload($request)]);
        }

        $item = CartItem::findOrFail($data['item_id']);
        $item->options()->whereNull('option_value_id')->delete();
        $item->delete();

        return response()->json(['ok' => true, 'summary' => $this->summaryPayload($request)]);
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

    /* ====================== (Опционально) ручной перенос гостевой корзины ====================== */

    public function mergeGuestCartIntoUser(Request $request): void
    {
        if ($this->isGuest($request)) return;

        $guestItems = $this->getGuestCart($request);
        if (!count($guestItems)) return;

        DB::transaction(function () use ($request, $guestItems) {
            $cart = $this->getUserCart($request);

            foreach ($guestItems as $gi) {
                $productId      = (int)($gi['product_id'] ?? 0);
                $qty            = (int)($gi['qty'] ?? 1);
                $optionValueIds = array_map('intval', $gi['option_value_ids'] ?? []);
                $rangeOptions   = array_map(function ($r) {
                    return [
                        'option_group_id' => (int)($r['option_group_id'] ?? 0),
                        'selected_min'    => (int)($r['selected_min'] ?? 0),
                        'selected_max'    => (int)($r['selected_max'] ?? 0),
                    ];
                }, $gi['range_options'] ?? []);

                $pricing = $this->pricing->computeUnitAndTotalCents(
                    $productId,
                    $optionValueIds,
                    $rangeOptions,
                    $qty
                );

                $item = $cart->items()->create([
                    'product_id'        => $productId,
                    'qty'               => $qty,
                    'unit_price_cents'  => $pricing['unit'],
                    'line_total_cents'  => $pricing['line_total'],
                ]);

                $gaSet = collect($gi['affix_ga_ids'] ?? [])->map(fn($v) => (int)$v)->unique();
                foreach ($optionValueIds as $vid) {
                    $item->options()->create([
                        'option_value_id' => $vid,
                        'is_ga'           => $gaSet->contains((int)$vid),
                    ]);
                }
                $productLoaded = $this->pricing->productWithGroups($productId);
                foreach ($rangeOptions as $row) {
                    $deltaForOne = $this->pricing->computeRangePerUnitDelta($productLoaded, [$row]);
                    $group = $productLoaded->optionGroups->firstWhere('id', $row['option_group_id']);

                    $item->options()->create([
                        'option_value_id'   => null,
                        'option_group_id'   => $row['option_group_id'],
                        'selected_min'      => $row['selected_min'],
                        'selected_max'      => $row['selected_max'],
                        'price_delta_cents' => $deltaForOne,
                        'payload_json'      => [
                            'pricing_mode'          => $group->pricing_mode ?? null,
                            'tier_combine_strategy' => $group->tier_combine_strategy ?? null,
                            'tiers'                 => is_array($group->tiers_json)
                                ? $group->tiers_json
                                : (json_decode((string)$group->tiers_json, true) ?: []),
                        ],
                    ]);
                }
            }

            $this->saveGuestCart($request, []);
        });
    }
}
