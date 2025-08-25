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
                'short' => $product->short,
                'description' => $product->description,
                // вот тут отдадим опции
                'option_groups' => $product->optionGroups->map(fn($g) => [
                    'id' => $g->id,
                    'title' => $g->title,
                    'type' => $g->type, 
                    'is_required' => (bool)$g->is_required,
                    'values' => $g->values->map(fn($v) => [
                        'id' => $v->id,
                        'title' => $v->title,
                        'price_delta_cents' => (int)$v->price_delta_cents,
                        'is_default' => (bool)$v->is_default, 
                    ])->values(),
                ])->values(),
            ],
        ]);
    }
}
