<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Category;
use App\Models\Product;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function show(Game $game, Category $category, Product $product)
    {
        abort_unless($category->game_id === $game->id && $product->category_id === $category->id, 404);

        return Inertia::render('Products/Show', [
            'game'    => $game->only(['id','name','slug']),
            'category'=> $category->only(['id','name','slug','type']),
            'product' => $product,
        ]);
    }
}