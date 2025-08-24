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

    return Inertia::render('Product/Show', [
        'game' => $game,
        'category' => $category,
        'product' => $product,
    ]);
}
}