<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->input('query', ''));
        if ($q === '') {
            return response()->json(['products' => []]);
        }

        $products = Product::query()
            ->with(['category.game', 'categories.game']) // ← добрали и many-to-many на всякий
            ->where('is_active', true)
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('short', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%");
            })
            ->orderByRaw("CASE WHEN name LIKE ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END", ["{$q}%", "%{$q}%"])
            ->limit(10)
            ->get();

        $out = $products->map(function (Product $p) {
            // primary category или первая прикреплённая
            $cat = $p->category ?: $p->categories->first();
            $game = $cat?->game;

            return [
                'id'             => $p->id,
                'title'          => $p->name,
                'human_price'    => $this->formatPrice($p->price_cents),
                'stored_image'   => $p->image_url,   // аксессор
                'url_code'       => $p->slug,

                'game' => $game ? [
                    'title'              => $game->title ?? $game->name ?? null,
                    'url_code'           => $game->slug ?? null,
                    'stored_logo_image'  => $game->logo_url ?? $game->stored_logo_image ?? null,
                ] : null,

                // явные слуги под роут /games/{game}/{category}/{product}
                'game_slug'      => $game?->slug,
                'category_slug'  => $cat?->slug,
                'product_slug'   => $p->slug,
            ];
        })->values();

        return response()->json(['products' => $out]);
    }

    private function formatPrice(?int $cents): string
    {
        $c = (int) ($cents ?? 0);
        return '$' . number_format($c / 100, 2);
    }
}
