<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function show(Request $request, Game $game, Category $category)
    {
        abort_unless($category->game_id === $game->id, 404);

        $filters = $request->validate([
            'q' => ['nullable','string','max:100'],
            'sort' => ['nullable','in:name,price_cents,created_at'],
            'order' => ['nullable','in:asc,desc'],
        ]);

        $products = $category->products()->active()
            ->when($filters['q'] ?? null, fn($q,$v) => $q->where('name','like',"%{$v}%"))
            ->when($filters['sort'] ?? null, function ($q) use ($filters) {
                $col = $filters['sort'] ?? 'name';
                $dir = $filters['order'] ?? 'asc';
                $q->orderBy($col, $dir);
            })
            ->select(['id','category_id','name','slug','price_cents','image','short'])
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Catalog/Category', [
            'game'     => $game->only(['id','name','slug']),
            'category' => $category->only(['id','name','slug','type','image']),
            'products' => $products,
            'filters'  => $filters,
        ]);
    }
}