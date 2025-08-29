<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CatalogController extends Controller
{
    public function index(Request $request, Game $game, ?Category $category = null)
    {
        // id категорий игры один раз
        $categoryIds = $game->categories()->pluck('id');

        // Категории игры + products_count + image_url для фронта
        $categories = $game->categories()
            ->withCount('products')
            ->get(['id', 'game_id', 'name', 'slug', 'type', 'image'])
            ->map(fn($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'slug'           => $c->slug,
                'type'           => $c->type,
                'image_url'      => $c->image_url,     // 👈 полный URL из аксессора
                'products_count' => $c->products_count,
            ]);

        // Общее число товаров по всем категориям игры
        $totalProducts = Product::query()
            ->where(function ($q) use ($categoryIds) {
                $q->whereHas('categories', fn($qq) => $qq->whereIn('categories.id', $categoryIds))
                    ->orWhereIn('category_id', $categoryIds);
            })
            ->distinct()
            ->count('products.id');

        // Базовый запрос по товарам
        $query = Product::query()
            ->active()
            ->with(['categories:id,name,slug'])
            ->select(['id', 'name', 'slug', 'price_cents', 'image', 'short', 'category_id']);

        // Фильтр по конкретной категории (или по всем категориям игры)
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

        // Пагинация + лёгкая трансформация под фронт
        $products = $query
            ->paginate(24)
            ->withQueryString()
            ->through(function ($p) {
                return [
                    'id'           => $p->id,
                    'name'         => $p->name,
                    'slug'         => $p->slug,
                    'price_cents'  => $p->price_cents,
                    'image_url'    => $p->image_url, // аксессор Product::image() уже вернёт /storage/...
                    'short'        => $p->short,
                    'categories'   => $p->categories->map(fn($c) => [
                        'id'   => $c->id,
                        'name' => $c->name,
                        'slug' => $c->slug,
                    ]),
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
                    'image_url'   => $category->image_url, // 👈 добавили
                    'description' => $category->description,
                    'short'       => $category->short ?? null,
                ]
                : null,
            'categories'    => $categories,
            'products'      => $products,
            'totalProducts' => $totalProducts,
            'seo'           => [
                'short'       => $category->short ?? null,
                'description' => $category->description ?? $game->description,
            ],
        ]);
    }
}
