<?php

namespace App\Services\Cart;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\{Cart};

class CartMerger
{
    public function __construct(private CartPricing $pricing) {}

    public function mergeGuestCartIntoUser(Request $request, int $userId): void
    {
        $guestItems = $request->session()->get('guest_cart', []);
        if (empty($guestItems)) return;

        DB::transaction(function () use ($request, $guestItems, $userId) {
            $cart = Cart::firstOrCreate(['user_id' => $userId]);

            foreach ($guestItems as $gi) {
                $productId      = (int)($gi['product_id'] ?? 0);
                $qty            = max(1, (int)($gi['qty'] ?? 1));
                $optionValueIds = collect($gi['option_value_ids'] ?? [])->map(fn($v)=>(int)$v)->unique()->sort()->values()->all();
                $rangeOptions   = collect($gi['range_options'] ?? [])->map(fn($r) => [
                    'option_group_id' => (int)($r['option_group_id'] ?? 0),
                    'selected_min'    => (int)($r['selected_min'] ?? 0),
                    'selected_max'    => (int)($r['selected_max'] ?? 0),
                ])->values()->all();

                $pricing = $this->pricing->computeUnitAndTotalCents(
                    $productId,
                    $optionValueIds,
                    $rangeOptions,
                    $qty
                );

                $item = $cart->items()->create([
                    'product_id'        => $productId,
                    'qty'               => $qty,
                    'unit_price_cents'  => $pricing['unit'],
                    'line_total_cents'  => $pricing['line_total'],
                ]);

                foreach ($optionValueIds as $vid) {
                    $item->options()->create(['option_value_id' => $vid]);
                }

                $productLoaded = $this->pricing->productWithGroups($productId);
                foreach ($rangeOptions as $row) {
                    $deltaForOne = $this->pricing->computeRangePerUnitDelta($productLoaded, [$row]);
                    $group       = $productLoaded->optionGroups->firstWhere('id', $row['option_group_id']);

                    $item->options()->create([
                        'option_value_id'   => null,
                        'option_group_id'   => $row['option_group_id'],
                        'selected_min'      => $row['selected_min'],
                        'selected_max'      => $row['selected_max'],
                        'price_delta_cents' => $deltaForOne,
                        'payload_json'      => [
                            'pricing_mode'          => $group->pricing_mode ?? null,
                            'tier_combine_strategy' => $group->tier_combine_strategy ?? null,
                            'tiers'                 => is_array($group->tiers_json)
                                ? $group->tiers_json
                                : (json_decode((string)$group->tiers_json, true) ?: []),
                        ],
                    ]);
                }
            }

            $request->session()->forget('guest_cart');
        });
    }
}