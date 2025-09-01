<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Category;
use App\Models\Product;
use App\Models\OptionGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CatalogController extends Controller
{
    public function index(Request $request, Game $game, ?Category $category = null)
    {
        $categoryIds = $game->categories()->pluck('id');

        $categories = $game->categories()
            ->withCount('products')
            ->get(['id', 'game_id', 'name', 'slug', 'type', 'image'])
            ->map(fn($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'slug'           => $c->slug,
                'type'           => $c->type,
                'image_url'      => $c->image_url,
                'products_count' => $c->products_count,
            ]);

        $totalProducts = Product::query()
            ->where(function ($q) use ($categoryIds) {
                $q->whereHas('categories', fn($qq) => $qq->whereIn('categories.id', $categoryIds))
                  ->orWhereIn('category_id', $categoryIds);
            })
            ->distinct()
            ->count('products.id');

        // Важно: добавили price_preview в select 👇
        $query = Product::query()
            ->active()
            ->with([
                'categories:id,name,slug',
                'optionGroups:id,product_id,type,qty_min,qty_max,qty_step,slider_min,slider_max,slider_step,pricing_mode,unit_price_cents,base_fee_cents',
            ])
            ->select([
                'id', 'name', 'slug',
                'price_cents', 'price_preview', // 👈 добавили
                'image', 'short', 'category_id',
            ]);

        if ($category) {
            $query->where(function ($q) use ($category) {
                $q->whereHas('categories', fn($qq) => $qq->where('categories.id', $category->id))
                  ->orWhere('category_id', $category->id);
            })->distinct();
        } else {
            $query->where(function ($q) use ($categoryIds) {
                $q->whereHas('categories', fn($qq) => $qq->whereIn('categories.id', $categoryIds))
                  ->orWhereIn('category_id', $categoryIds);
            })->distinct();
        }

        $products = $query
            ->paginate(24)
            ->withQueryString()
            ->through(function ($p) {
                $range = $p->optionGroups->firstWhere('type', OptionGroup::TYPE_RANGE);
                $qty   = $p->optionGroups->firstWhere('type', OptionGroup::TYPE_SLIDER);

                if ($range) {
                    $pricingPreview = [
                        'kind'           => 'range',
                        'pricing_mode'   => $range->pricing_mode ?? 'flat',
                        'unit_cents'     => (int) ($range->unit_price_cents ?? 0),
                        'step'           => (int) max(1, $range->slider_step ?? 1),
                        'min'            => (int) ($range->slider_min ?? 1),
                        'max'            => (int) max($range->slider_min ?? 1, $range->slider_max ?? 1),
                        'base_fee_cents' => (int) ($range->base_fee_cents ?? 0),
                    ];
                } elseif ($qty) {
                    $pricingPreview = [
                        'kind'       => 'qty',
                        'unit_cents' => (int) $p->price_cents,
                        'step'       => (int) max(1, $qty->qty_step ?? 1),
                        'min'        => (int) ($qty->qty_min ?? 1),
                        'max'        => (int) max($qty->qty_min ?? 1, $qty->qty_max ?? 1),
                    ];
                } else {
                    $pricingPreview = [
                        'kind'       => 'plain',
                        'unit_cents' => (int) $p->price_cents,
                    ];
                }

                return [
                    'id'              => $p->id,
                    'name'            => $p->name,
                    'slug'            => $p->slug,
                    'price_cents'     => (int) $p->price_cents,
                    'price_preview'   => $p->price_preview, // 👈 теперь точно приедет
                    'image_url'       => $p->image_url,
                    'short'           => $p->short,
                    'categories'      => $p->categories->map(fn($c) => [
                        'id'   => $c->id,
                        'name' => $c->name,
                        'slug' => $c->slug,
                    ]),
                    'pricing_preview' => $pricingPreview,
                ];
            });

        return Inertia::render('Catalog/Game', [
            'game'          => $game->only(['id', 'name', 'slug', 'image_url', 'description']),
            'category'      => $category
                ? [
                    'id'          => $category->id,
                    'name'        => $category->name,
                    'slug'        => $category->slug,
                    'type'        => $category->type,
                    'image_url'   => $category->image_url,
                    'description' => $category->description,
                    'short'       => $category->short ?? null,
                ]
                : null,
            'categories'    => $categories,
            'products'      => $products,
            'totalProducts' => $totalProducts,
            'seo'           => [
                // Используем nullsafe, чтобы не падать при $category === null 👇
                'short'       => $category?->short ?? null,                 // 👈
                'description' => $category?->description ?? $game->description, // 👈
            ],
        ]);
    }
}