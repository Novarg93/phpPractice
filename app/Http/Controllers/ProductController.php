<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Category;
use App\Models\Product;
use App\Models\OptionGroup;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function show(Game $game, Category $category, Product $product)
    {
        $belongs = $product->category_id === $category->id
            || $product->categories()->whereKey($category->id)->exists();

        abort_unless($belongs, 404);

        $product->load([
            'optionGroups.values' => fn($q) => $q->where('is_active', true),
        ]);

        return Inertia::render('Product/Show', [
            'game' => $game,
            'category' => $category,
            'product' => [
                'id'             => $product->id,
                'name'           => $product->name,
                'slug'           => $product->slug,
                'sku'            => $product->sku,
                'price_cents'    => $product->price_cents,
                'is_active'      => $product->is_active,
                'track_inventory' => $product->track_inventory,
                'stock'          => $product->stock,
                'image'          => $product->image,
                'image_url'      => $product->image_url,
                'short'          => $product->short,
                'description'    => $product->description,
                'price_preview' => $product->price_preview,

                'option_groups' => $product->optionGroups->map(function ($g) {
                    // Общая часть для всех групп (type зададим ниже)
                    $base = [
                        'id'              => $g->id,
                        'title'           => $g->title,
                        'is_required'     => (bool) $g->is_required,
                        'multiply_by_qty' => is_null($g->multiply_by_qty) ? true : (bool) $g->multiply_by_qty,
                    ];
                    if (($g->type ?? null) === \App\Models\OptionGroup::TYPE_SELECTOR || ($g->type ?? null) === 'selector') {
                        return array_merge($base, [
                            'type'           => 'selector',
                            'selection_mode' => $g->selection_mode === 'multi' ? 'multi' : 'single',
                            'pricing_mode'   => $g->pricing_mode   === 'percent' ? 'percent' : 'absolute',
                            'values'         => $g->values->where('is_active', true)->map(function ($v) use ($g) {
                                $isPercent = ($g->pricing_mode === 'percent');
                                return [
                                    'id'            => $v->id,
                                    'title'         => $v->title,
                                    'delta_cents'   => $isPercent ? null : (int)($v->delta_cents ?? $v->price_delta_cents ?? 0),
                                    'delta_percent' => $isPercent ? (float)($v->delta_percent ?? $v->value_percent ?? 0) : null,
                                    'is_default'    => (bool) $v->is_default,
                                ];
                            })->values(),
                        ]);
                    }

                    // 2) Legacy radio/checkbox → нормализуем в selector
                    if (in_array($g->type, [
                        \App\Models\OptionGroup::TYPE_RADIO,
                        \App\Models\OptionGroup::TYPE_CHECKBOX,
                        'radio_additive',
                        'checkbox_additive',
                    ])) {
                        $selection = ($g->type === \App\Models\OptionGroup::TYPE_CHECKBOX || $g->type === 'checkbox_additive') ? 'multi' : 'single';
                        return array_merge($base, [
                            'type'           => 'selector',
                            'selection_mode' => $selection,
                            'pricing_mode'   => 'absolute',
                            'values'         => $g->values->where('is_active', true)->map(fn($v) => [
                                'id'            => $v->id,
                                'title'         => $v->title,
                                'delta_cents'   => (int)($v->delta_cents ?? $v->price_delta_cents ?? 0),
                                'delta_percent' => null,
                                'is_default'    => (bool) $v->is_default,
                            ])->values(),
                        ]);
                    }

                    if (in_array($g->type, [
                        \App\Models\OptionGroup::TYPE_RADIO_PERCENT,
                        \App\Models\OptionGroup::TYPE_CHECKBOX_PERCENT,
                        'radio_percent',
                        'checkbox_percent',
                    ])) {
                        $selection = ($g->type === \App\Models\OptionGroup::TYPE_CHECKBOX_PERCENT || $g->type === 'checkbox_percent') ? 'multi' : 'single';
                        return array_merge($base, [
                            'type'           => 'selector',
                            'selection_mode' => $selection,
                            'pricing_mode'   => 'percent',
                            'values'         => $g->values->where('is_active', true)->map(fn($v) => [
                                'id'            => $v->id,
                                'title'         => $v->title,
                                'delta_cents'   => null,
                                'delta_percent' => (float)($v->delta_percent ?? $v->value_percent ?? 0),
                                'is_default'    => (bool) $v->is_default,
                            ])->values(),
                        ]);
                    }

                    // 3) quantity_slider
                    if ($g->type === \App\Models\OptionGroup::TYPE_SLIDER || $g->type === 'quantity_slider') {
                        return array_merge($base, [
                            'type'         => 'quantity_slider',
                            'qty_min'      => (int)($g->qty_min ?? 1),
                            'qty_max'      => (int)max($g->qty_min ?? 1, $g->qty_max ?? 1),
                            'qty_step'     => (int)max(1, (int)($g->qty_step ?? 1)),
                            'qty_default'  => (int)($g->qty_default ?? ($g->qty_min ?? 1)),
                        ]);
                    }

                    // 4) double_range_slider (как у тебя)
                    if ($g->type === \App\Models\OptionGroup::TYPE_RANGE || $g->type === 'double_range_slider') {
                        return array_merge($base, [
                            'type'               => 'double_range_slider',
                            'slider_min'         => (int)($g->slider_min  ?? 1),
                            'slider_max'         => (int)max($g->slider_min ?? 1, $g->slider_max ?? 1),
                            'slider_step'        => (int)max(1, (int)($g->slider_step ?? 1)),
                            'range_default_min'  => isset($g->range_default_min) ? (int)$g->range_default_min : (int)($g->slider_min ?? 1),
                            'range_default_max'  => isset($g->range_default_max) ? (int)$g->range_default_max : (int)max($g->slider_min ?? 1, $g->slider_max ?? 1),
                            'pricing_mode'          => $g->pricing_mode ?? 'flat',
                            'unit_price_cents'      => isset($g->unit_price_cents) ? (int)$g->unit_price_cents : null,
                            'tier_combine_strategy' => $g->tier_combine_strategy ?: 'sum_piecewise',
                            'base_fee_cents'        => (int)($g->base_fee_cents ?? 0),
                            'max_span'              => isset($g->max_span) ? (int)$g->max_span : null,
                            'tiers' => collect($g->tiers_json ?? [])->map(fn($t) => [
                                'from'             => (int)($t['from'] ?? 0),
                                'to'               => (int)($t['to'] ?? 0),
                                'unit_price_cents' => (int)($t['unit_price_cents'] ?? 0),
                                'label'            => $t['label'] ?? null,
                                'min_block'        => isset($t['min_block']) ? (int)$t['min_block'] : null,
                                'multiplier'       => isset($t['multiplier']) ? (float)$t['multiplier'] : null,
                                'cap_cents'        => isset($t['cap_cents']) ? (int)$t['cap_cents'] : null,
                            ])->values(),
                        ]);
                    }

                    // fallback — просто вернём тип
                    return array_merge($base, ['type' => $g->type]);

                    // 2) LEGACY SELECTORS (оставляем как было — для совместимости)
                    switch ($g->type) {
                        case OptionGroup::TYPE_RADIO:
                        case 'radio_additive':
                            return array_merge($base, [
                                'type'   => 'radio_additive',
                                'values' => $g->values->map(fn($v) => [
                                    'id'                 => $v->id,
                                    'title'              => $v->title,
                                    'price_delta_cents'  => (int) ($v->price_delta_cents ?? 0),
                                    'value_percent'      => null,
                                    'is_default'         => (bool) $v->is_default,
                                ])->values(),
                            ]);

                        case OptionGroup::TYPE_CHECKBOX:
                        case 'checkbox_additive':
                            return array_merge($base, [
                                'type'   => 'checkbox_additive',
                                'values' => $g->values->map(fn($v) => [
                                    'id'                 => $v->id,
                                    'title'              => $v->title,
                                    'price_delta_cents'  => (int) ($v->price_delta_cents ?? 0),
                                    'value_percent'      => null,
                                    'is_default'         => (bool) $v->is_default,
                                ])->values(),
                            ]);

                        case OptionGroup::TYPE_RADIO_PERCENT:
                        case 'radio_percent':
                            return array_merge($base, [
                                'type'   => 'radio_percent',
                                'values' => $g->values->map(fn($v) => [
                                    'id'                 => $v->id,
                                    'title'              => $v->title,
                                    'price_delta_cents'  => null,
                                    'value_percent'      => (float) ($v->value_percent ?? 0),
                                    'is_default'         => (bool) $v->is_default,
                                ])->values(),
                            ]);

                        case OptionGroup::TYPE_CHECKBOX_PERCENT:
                        case 'checkbox_percent':
                            return array_merge($base, [
                                'type'   => 'checkbox_percent',
                                'values' => $g->values->map(fn($v) => [
                                    'id'                 => $v->id,
                                    'title'              => $v->title,
                                    'price_delta_cents'  => null,
                                    'value_percent'      => (float) ($v->value_percent ?? 0),
                                    'is_default'         => (bool) $v->is_default,
                                ])->values(),
                            ]);

                            // 3) quantity_slider
                        case OptionGroup::TYPE_SLIDER:
                        case 'quantity_slider':
                            return array_merge($base, [
                                'type'         => 'quantity_slider',
                                'qty_min'      => (int) ($g->qty_min ?? 1),
                                'qty_max'      => (int) max($g->qty_min ?? 1, $g->qty_max ?? 1),
                                'qty_step'     => (int) max(1, (int) ($g->qty_step ?? 1)),
                                'qty_default'  => (int) ($g->qty_default ?? ($g->qty_min ?? 1)),
                            ]);

                            // 4) double_range_slider
                        case OptionGroup::TYPE_RANGE:
                        case 'double_range_slider':
                            return array_merge($base, [
                                'type'               => 'double_range_slider',
                                'slider_min'         => (int) ($g->slider_min  ?? 1),
                                'slider_max'         => (int) max($g->slider_min ?? 1, $g->slider_max ?? 1),
                                'slider_step'        => (int) max(1, (int) ($g->slider_step ?? 1)),
                                'range_default_min'  => isset($g->range_default_min)
                                    ? (int) $g->range_default_min
                                    : (int) ($g->slider_min ?? 1),
                                'range_default_max'  => isset($g->range_default_max)
                                    ? (int) $g->range_default_max
                                    : (int) max($g->slider_min ?? 1, $g->slider_max ?? 1),
                                'pricing_mode'          => $g->pricing_mode ?? 'flat',
                                'unit_price_cents'      => isset($g->unit_price_cents) ? (int) $g->unit_price_cents : null,
                                'tier_combine_strategy' => $g->tier_combine_strategy ?: 'sum_piecewise',
                                'base_fee_cents'        => (int) ($g->base_fee_cents ?? 0),
                                'max_span'              => isset($g->max_span) ? (int) $g->max_span : null,
                                'tiers' => collect($g->tiers_json ?? [])->map(fn($t) => [
                                    'from'             => (int) ($t['from'] ?? 0),
                                    'to'               => (int) ($t['to'] ?? 0),
                                    'unit_price_cents' => (int) ($t['unit_price_cents'] ?? 0),
                                    'label'            => $t['label'] ?? null,
                                    'min_block'        => isset($t['min_block']) ? (int) $t['min_block'] : null,
                                    'multiplier'       => isset($t['multiplier']) ? (float) $t['multiplier'] : null,
                                    'cap_cents'        => isset($t['cap_cents']) ? (int) $t['cap_cents'] : null,
                                ])->values(),
                            ]);

                        default:
                            // На всякий — вернём базу с оригинальным типом (чтобы не падало)
                            return array_merge($base, [
                                'type' => $g->type,
                            ]);
                    }
                })->values(),
            ],
        ]);
    }
}
