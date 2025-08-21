<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class CategoryProductBackfillSeeder extends Seeder {
    public function run(): void {
        Product::query()
            ->whereNotNull('category_id')
            ->chunkById(500, function ($chunk) {
                foreach ($chunk as $p) {
                    // добавим запись в пивот и отметим как primary
                    $p->categories()->syncWithoutDetaching([
                        $p->category_id => ['is_primary' => true],
                    ]);
                }
            });
    }
}