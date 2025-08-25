<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\App;
use App\Models\{Cart, CartItem, OptionValue};

class MergeCartOnLogin
{
    public function handle(Login $event): void
    {
        $request = request();
        $guestItems = $request->session()->get('guest_cart', []);
        if (empty($guestItems)) return;

        $cart = Cart::firstOrCreate(['user_id' => $event->user->id]);

        foreach ($guestItems as $i) {
            $productId = (int)$i['product_id'];
            $qty       = (int)$i['qty'];
            $optionIds = collect($i['option_value_ids'] ?? [])->unique()->sort()->values()->all();

            // Пересчёт цены на всякий случай (могли поменяться дельты)
            $unit = $productId
                ? (int)(\App\Models\Product::find($productId)?->price_cents ?? 0) + (int)OptionValue::whereIn('id', $optionIds)->sum('price_delta_cents')
                : (int)($i['unit_price_cents'] ?? 0);

            // Сливаем одинаковые строки
            $existing = $cart->items()
                ->where('product_id', $productId)
                ->get()
                ->first(function ($item) use ($optionIds, $unit) {
                    $ids = $item->options()->pluck('option_value_id')->sort()->values()->all();
                    return $ids === $optionIds && $item->unit_price_cents === $unit;
                });

            if ($existing) {
                $existing->qty += $qty;
                $existing->line_total_cents = $existing->unit_price_cents * $existing->qty;
                $existing->save();
            } else {
                $new = $cart->items()->create([
                    'product_id' => $productId,
                    'qty' => $qty,
                    'unit_price_cents' => $unit,
                    'line_total_cents' => $unit * $qty,
                ]);
                foreach ($optionIds as $vid) {
                    $new->options()->create(['option_value_id' => $vid]);
                }
            }
        }

        // очистить гостевую корзину
        $request->session()->forget('guest_cart');
    }
}