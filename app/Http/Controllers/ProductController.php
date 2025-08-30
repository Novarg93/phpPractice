<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Category;
use App\Models\Product;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function show(\App\Models\Game $game, \App\Models\Category $category, \App\Models\Product $product)
    {
        $belongs = $product->category_id === $category->id
            || $product->categories()->whereKey($category->id)->exists();

        abort_unless($belongs, 404);

        $product->load([
            'optionGroups.values' => fn($q) => $q->where('is_active', true)
        ]);

        return Inertia::render('Product/Show', [
            'game' => $game,
            'category' => $category,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price_cents' => $product->price_cents,
                'is_active' => $product->is_active,
                'track_inventory' => $product->track_inventory,
                'stock' => $product->stock,
                'image' => $product->image,
                'image_url' => $product->image_url,
                'short' => $product->short,
                'description' => $product->description,




                // вот тут отдадим опции
                'option_groups' => $product->optionGroups->map(function ($g) {
                    $base = [
                        'id'           => $g->id,
                        'title'        => $g->title,
                        'type'         => $g->type,
                        'is_required'  => (bool) $g->is_required,
                        'multiply_by_qty' => (bool) $g->multiply_by_qty,
                    ];

                    switch ($g->type) {
                        case \App\Models\OptionGroup::TYPE_RADIO:
                        case \App\Models\OptionGroup::TYPE_CHECKBOX:
                            return array_merge($base, [
                                'values' => $g->values
                                    ->where('is_active', true)
                                    ->map(fn($v) => [
                                        'id'                 => $v->id,
                                        'title'              => $v->title,
                                        'price_delta_cents'  => (int) $v->price_delta_cents,
                                        'is_default'         => (bool) $v->is_default,
                                    ])->values(),
                            ]);

                        case \App\Models\OptionGroup::TYPE_SLIDER: // quantity_slider
                            return array_merge($base, [
                                'qty_min'     => (int)($g->qty_min     ?? 1),
                                'qty_max'     => (int)max($g->qty_min ?? 1, $g->qty_max ?? 1),
                                'qty_step'    => (int)max(1, (int)($g->qty_step ?? 1)),
                                'qty_default' => (int)($g->qty_default ?? ($g->qty_min ?? 1)),
                            ]);

                        case \App\Models\OptionGroup::TYPE_RANGE: // double_range_slider
                            return array_merge($base, [
                                // числовые границы и шаг
                                'slider_min'        => (int)($g->slider_min  ?? 1),
                                'slider_max'        => (int)max($g->slider_min ?? 1, $g->slider_max ?? 1),
                                'slider_step'       => (int)max(1, (int)($g->slider_step ?? 1)),

                                // дефолтный выбранный диапазон
                                'range_default_min' => isset($g->range_default_min)
                                    ? (int)$g->range_default_min
                                    : (int)($g->slider_min ?? 1),
                                'range_default_max' => isset($g->range_default_max)
                                    ? (int)$g->range_default_max
                                    : (int)max($g->slider_min ?? 1, $g->slider_max ?? 1),

                                // ценообразование
                                'pricing_mode'          => $g->pricing_mode ?? 'flat',
                                'unit_price_cents'      => isset($g->unit_price_cents) ? (int)$g->unit_price_cents : null,
                                'tier_combine_strategy' => $g->tier_combine_strategy ?: 'sum_piecewise',
                                'base_fee_cents'        => (int)($g->base_fee_cents ?? 0),
                                'max_span'              => isset($g->max_span) ? (int)$g->max_span : null,

                                // тиеры (переименуем в "tiers" для фронта)
                                'tiers' => collect($g->tiers_json ?? [])->map(function ($t) {
                                    return [
                                        'from'             => (int)($t['from'] ?? 0),
                                        'to'               => (int)($t['to'] ?? 0),
                                        'unit_price_cents' => (int)($t['unit_price_cents'] ?? 0),
                                        'label'            => $t['label'] ?? null,
                                        'min_block'        => isset($t['min_block']) ? (int)$t['min_block'] : null,
                                        'multiplier'       => isset($t['multiplier']) ? (float)$t['multiplier'] : null,
                                        'cap_cents'        => isset($t['cap_cents']) ? (int)$t['cap_cents'] : null,
                                    ];
                                })->values(),
                            ]);

                        default:
                            return $base;
                    }
                })->values(),
            ],
        ]);
    }
}
