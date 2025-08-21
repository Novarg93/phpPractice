<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CatalogController extends Controller
{
    public function index(Request $request, Game $game, ?Category $category = null)
{
    $categories = $game->categories()
        ->withCount(['products'])
        ->get(['id','game_id','name','slug','type','image'])
        ->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'type' => $c->type,
            'image' => $c->image,
            'products_count' => $c->products_count,
        ]);

    // считаем общее количество товаров по всем категориям игры
    $totalProducts = \App\Models\Product::query()
        ->whereHas('categories', fn($q) => $q->whereIn('categories.id', $game->categories()->pluck('id')))
        ->orWhereIn('category_id', $game->categories()->pluck('id'))
        ->distinct()
        ->count('products.id');

    $query = \App\Models\Product::query()->active()
        ->with(['categories:id,name,slug'])
        ->select(['id','name','slug','price_cents','image','short','category_id']);

    if ($category) {
        $query->where(function ($q) use ($category) {
            $q->whereHas('categories', fn($qq) => $qq->where('categories.id', $category->id))
              ->orWhere('category_id', $category->id);
        })->distinct();
    } else {
        $categoryIds = $game->categories()->pluck('id');
        $query->where(function ($q) use ($categoryIds) {
            $q->whereHas('categories', fn($qq) => $qq->whereIn('categories.id', $categoryIds))
              ->orWhereIn('category_id', $categoryIds);
        })->distinct();
    }

    $products = $query->paginate(24)->withQueryString();

    return Inertia::render('Catalog/Game', [
        'game'          => $game->only(['id','name','slug','image_url','description']),
        'category'      => $category ? $category->only(['id','name','slug','type','image','description','short']) : null,
        'categories'    => $categories,
        'products'      => $products,
        'totalProducts' => $totalProducts, 
        'seo'           => [
            'short' => $category->short ?? null,
            'description' => $category->description ?? $game->description,
        ],
    ]);
}
}