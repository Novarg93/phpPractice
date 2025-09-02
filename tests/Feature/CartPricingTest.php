<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\{Game, Category, Product, OptionGroup, OptionValue};
use App\Services\Cart\CartPricing;

class CartPricingTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $game = Game::query()->create([
            'name' => 'Test Game '.Str::random(5),
            'slug' => Str::slug('game-'.Str::random(5)),
        ]);

        $cat = Category::query()->create([
            'name'    => 'Test Cat '.Str::random(5),
            'slug'    => Str::slug('cat-'.Str::random(5)),
            'game_id' => $game->id,
            'type'    => 'product', // если есть enum/константа — подставь её
        ]);

        return Product::query()->create([
            'name'        => 'Prod '.Str::random(5),
            'slug'        => Str::slug('prod-'.Str::random(5)),
            'sku'         => 'SKU-'.Str::upper(Str::random(6)),
            'price_cents' => 10_000,
            'is_active'   => true,
            'category_id' => $cat->id,
        ]);
    }

    /** absolute (per-unit) + multiply_by_qty=true */
    public function test_absolute_per_unit_adds_to_unit_and_multiplies_by_qty(): void
    {
        $p   = $this->makeProduct();
        $grp = OptionGroup::query()->create([
            'product_id'      => $p->id,
            'title'           => 'ABS',
            'type'            => OptionGroup::TYPE_SELECTOR,
            'selection_mode'  => OptionGroup::SEL_SINGLE,
            'pricing_mode'    => OptionGroup::PRICE_ABSOLUTE,
            'multiply_by_qty' => true,        // 👈 per unit
        ]);

        $v = OptionValue::query()->create([
            'option_group_id'   => $grp->id,
            'title'             => '+3.00',
            'delta_cents'       => 300,      // absolute
            'is_active'         => true,
            'is_default'        => true,
            'position'          => 0,
            // подстрахуемся от NOT NULL в старых миграциях:
            'price_delta_cents' => 0,
        ]);

        $svc = new CartPricing();

        $svc->validateSelection($p->id, [$v->id]);
        $qty = $svc->validateAndResolveQty($p->id, 2);
        $ranges = $svc->validateRangeSelections($p->id, []);

        $res = $svc->computeUnitAndTotalCents($p->id, [$v->id], $ranges, $qty);

        // unit = 10000 + 300 = 10300
        $this->assertSame(10_300, $res['unit']);
        // line = unit * qty = 20600
        $this->assertSame(20_600, $res['line_total']);
    }

    /** percent (per-unit): два значения 10% и 5% суммируются = 15%, НЕ компаундатся */
    public function test_percent_per_unit_sums_not_compounds(): void
    {
        $p   = $this->makeProduct();
        $grp = OptionGroup::query()->create([
            'product_id'      => $p->id,
            'title'           => 'PERC',
            'type'            => OptionGroup::TYPE_SELECTOR,
            'selection_mode'  => OptionGroup::SEL_MULTI,     // multi
            'pricing_mode'    => OptionGroup::PRICE_PERCENT, // percent
            'multiply_by_qty' => true,                       // per unit
        ]);

        $v10 = OptionValue::query()->create([
            'option_group_id'   => $grp->id,
            'title'             => '+10%',
            'delta_percent'     => 10.0,
            'is_active'         => true,
            'position'          => 0,
            'price_delta_cents' => 0,
        ]);
        $v5 = OptionValue::query()->create([
            'option_group_id'   => $grp->id,
            'title'             => '+5%',
            'delta_percent'     => 5.0,
            'is_active'         => true,
            'position'          => 1,
            'price_delta_cents' => 0,
        ]);

        $svc = new CartPricing();

        $svc->validateSelection($p->id, [$v10->id, $v5->id]);
        $qty = $svc->validateAndResolveQty($p->id, 3);
        $ranges = $svc->validateRangeSelections($p->id, []);

        $res = $svc->computeUnitAndTotalCents($p->id, [$v10->id, $v5->id], $ranges, $qty);

        // percent sum = 15% -> unit = 10000 * 1.15 = 11500
        $this->assertSame(11_500, $res['unit']);
        // line = unit * 3 = 34500
        $this->assertSame(34_500, $res['line_total']);
    }

    /** percent (total-level): multiply_by_qty=false → проценты применяются один раз к итогу */
    public function test_percent_total_level_applies_once_after_abs_total(): void
    {
        $p   = $this->makeProduct();

        // одно абсолютное "на заказ" +500
        $grpAbs = OptionGroup::query()->create([
            'product_id'      => $p->id,
            'title'           => 'ABS-TOTAL',
            'type'            => OptionGroup::TYPE_SELECTOR,
            'selection_mode'  => OptionGroup::SEL_SINGLE,
            'pricing_mode'    => OptionGroup::PRICE_ABSOLUTE,
            'multiply_by_qty' => false, // 👈 не умножаем на qty — на весь заказ
        ]);
        $vAbs = OptionValue::query()->create([
            'option_group_id'   => $grpAbs->id,
            'title'             => '+5.00 once',
            'delta_cents'       => 500,
            'is_active'         => true,
            'position'          => 0,
            'price_delta_cents' => 0,
        ]);

        // одно процентное "на заказ" +10%
        $grpPerc = OptionGroup::query()->create([
            'product_id'      => $p->id,
            'title'           => 'PERC-TOTAL',
            'type'            => OptionGroup::TYPE_SELECTOR,
            'selection_mode'  => OptionGroup::SEL_SINGLE,
            'pricing_mode'    => OptionGroup::PRICE_PERCENT,
            'multiply_by_qty' => false, // 👈 проценты к итогу, не к unit
        ]);
        $vPerc = OptionValue::query()->create([
            'option_group_id'   => $grpPerc->id,
            'title'             => '+10% once',
            'delta_percent'     => 10.0,
            'is_active'         => true,
            'position'          => 0,
            'price_delta_cents' => 0,
        ]);

        $svc = new CartPricing();

        $svc->validateSelection($p->id, [$vAbs->id, $vPerc->id]);
        $qty = $svc->validateAndResolveQty($p->id, 2);
        $ranges = $svc->validateRangeSelections($p->id, []);

        $res = $svc->computeUnitAndTotalCents($p->id, [$vAbs->id, $vPerc->id], $ranges, $qty);

        // unit = базовая 10000 (модификаторов per-unit нет)
        $this->assertSame(10_000, $res['unit']);
        // subtotal = unit * qty = 20000
        // addTotalAbs = 500 → 20500
        // +10% once → 22550
        $this->assertSame(22_550, $res['line_total']);
    }
}