<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Game;
use App\Models\Category;
use App\Models\Product;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $game = Game::updateOrCreate(
            ['slug' => 'diablo-4'],
            ['name' => 'Diablo 4', 'description' => 'Sanctuary services & items']
        );

        $cats = collect([
            ['name' => 'Items',    'slug' => 'items',    'type' => 'items'],
            ['name' => 'Currency', 'slug' => 'currency', 'type' => 'currency'],
            ['name' => 'Leveling', 'slug' => 'leveling', 'type' => 'leveling'],
        ])->map(fn($c) => Category::updateOrCreate(
            ['game_id' => $game->id, 'slug' => $c['slug']],
            ['name' => $c['name'], 'type' => $c['type']]
        ))->keyBy('slug');

        foreach ([['Ancestral Sword', 1999], ['Unique Amulet', 3499]] as [$name,$price]) {
            Product::updateOrCreate(
                ['category_id' => $cats['items']->id, 'slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'price_cents' => $price,
                    'is_active' => true,
                    'track_inventory' => false, // анлим по умолчанию
                    'short' => 'High roll'
                ]
            );
        }

        foreach ([['Gold 10M',  499], ['Gold 50M', 1999]] as [$name,$price]) {
            Product::updateOrCreate(
                ['category_id' => $cats['currency']->id, 'slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'price_cents' => $price,
                    'is_active' => true,
                    'track_inventory' => false,
                    'short' => 'Fast delivery'
                ]
            );
        }
    }
}
