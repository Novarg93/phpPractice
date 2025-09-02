<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\Game;
use App\Models\Category;
use App\Models\Product;
use App\Models\OptionGroup;
use App\Models\OptionValue;

class SelectorPricingTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $game = Game::query()->create([
            'name' => 'Test Game ' . Str::random(6),
            'slug' => Str::slug('test-game-' . Str::random(6)),
        ]);

        $cat = Category::query()->create([
            'name'    => 'Test Cat ' . Str::random(6),
            'slug'    => Str::slug('test-' . Str::random(6)),
            'game_id' => $game->id,
            'type'    => 'product', // 👈 добавь ЭТО (или то значение, что ожидает твоя БД)
        ]);

        return Product::query()->create([
            'name'        => 'Test Prod ' . Str::random(6),
            'slug'        => Str::slug('test-prod-' . Str::random(6)),
            'sku'         => 'SKU-' . Str::upper(Str::random(6)),
            'price_cents' => 10000,
            'is_active'   => true,
            'category_id' => $cat->id,
        ]);
    }

    public function test_persists_selector_in_percent_mode_and_stores_only_delta_percent_for_values(): void
    {
        $product = $this->makeProduct();

        $group = OptionGroup::query()->create([
            'product_id'      => $product->id,
            'title'           => 'GA Percent',
            'type'            => OptionGroup::TYPE_SELECTOR,
            'selection_mode'  => OptionGroup::SEL_SINGLE,
            'pricing_mode'    => OptionGroup::PRICE_PERCENT,  // percent
            'multiply_by_qty' => true,
            'ui_variant'      => 'list',
        ]);

        $v0 = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'title'           => '0 GA',
            'delta_percent'   => 0.0,
            'is_active'       => true,
            'is_default'      => true,
            'position'        => 0,
        ]);

        $v1 = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'title'           => '1 GA',
            'delta_percent'   => 50.0,
            'is_active'       => true,
            'position'        => 1,
        ]);

        $group->refresh();
        $this->assertSame('percent', $group->pricing_mode);

        $v0->refresh();
        $this->assertIsFloat($v0->delta_percent);
        $this->assertSame(0.0, $v0->delta_percent);
        $this->assertNull($v0->delta_cents);

        $v1->refresh();
        $this->assertIsFloat($v1->delta_percent);
        $this->assertSame(50.0, $v1->delta_percent);
        $this->assertNull($v1->delta_cents);
    }

    public function test_switches_selector_to_absolute_and_stores_only_delta_cents_nulling_percent(): void
    {
        $product = $this->makeProduct();

        $group = OptionGroup::query()->create([
            'product_id'      => $product->id,
            'title'           => 'GA Switch',
            'type'            => OptionGroup::TYPE_SELECTOR,
            'selection_mode'  => OptionGroup::SEL_SINGLE,
            'pricing_mode'    => OptionGroup::PRICE_PERCENT,
            'multiply_by_qty' => true,
            'ui_variant'      => 'list',
        ]);

        $v = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'title'           => '1 GA',
            'delta_percent'   => 150.0,
            'is_active'       => true,
            'position'        => 0,
        ]);

        // Переключили режим группы на absolute
        $group->update(['pricing_mode' => OptionGroup::PRICE_ABSOLUTE]);
        $group->refresh();
        $this->assertSame('absolute', $group->pricing_mode);

        // Имитация поведения формы: чистим percent, пишем cents
        $v->update([
            'delta_cents'        => 1234,
            'delta_percent'      => null,
            'value_percent'      => null, // legacy alias
            'price_delta_cents'  => 0, // legacy alias
        ]);

        $v->refresh();
        $this->assertSame(1234, $v->delta_cents);
        $this->assertNull($v->delta_percent);
        $this->assertSame(0, $v->price_delta_cents);
    }

    public function test_keeps_legacy_radio_checkbox_intact_smoke(): void
    {
        $product = $this->makeProduct();

        $radio = OptionGroup::query()->create([
            'product_id' => $product->id,
            'title'      => 'Legacy Radio',
            'type'       => OptionGroup::TYPE_RADIO,
            'is_required' => false,
            'position'   => 0,
        ]);

        $rv = OptionValue::query()->create([
            'option_group_id'   => $radio->id,
            'title'             => 'R-1',
            'price_delta_cents' => 500, // legacy поле
            'is_active'         => true,
            'position'          => 0,
        ]);

        $this->assertSame(OptionGroup::TYPE_RADIO, $radio->refresh()->type);
        $this->assertSame(500, $rv->refresh()->price_delta_cents);
    }
}
