<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\OptionValue;

class CartController extends Controller
{
    private function getUserCart(Request $request): Cart
    {
        return Cart::firstOrCreate(['user_id' => $request->user()->id]);
    }

    public function index(Request $request)
    {
        $cart = $this->getUserCart($request)->load('items.product');

        return Inertia::render('Cart/Index', [
            'items' => $cart->items->map(fn($item) => [
                'id' => $item->id,
                'product' => [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'image' => $item->product->image,
                ],
                'qty' => $item->qty,
                'unit_price_cents' => $item->unit_price_cents,
                'line_total_cents' => $item->line_total_cents,
            ]),
            'total_qty' => $cart->totalQty(),
            'total_sum_cents' => $cart->totalCents(),
        ]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required','integer','exists:products,id'],
            'qty' => ['nullable','integer','min:1'],
            'option_value_ids' => ['array'],
            'option_value_ids.*' => ['integer','exists:option_values,id'],
        ]);

        $qty = max(1, (int)($data['qty'] ?? 1));
        $product = Product::findOrFail($data['product_id']);
        $chosenIds = collect($data['option_value_ids'] ?? [])->unique()->values();

        $deltaSum = OptionValue::whereIn('id', $chosenIds)->sum('price_delta_cents');
        $unitPrice = $product->price_cents + $deltaSum;

        $cart = $this->getUserCart($request);

        $item = $cart->items()->create([
            'product_id' => $product->id,
            'qty' => $qty,
            'unit_price_cents' => $unitPrice,
            'line_total_cents' => $unitPrice * $qty,
        ]);

        foreach ($chosenIds as $vid) {
            $item->options()->create(['option_value_id' => $vid]);
        }

        return response()->json(['ok' => true]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'item_id' => ['required','integer','exists:cart_items,id'],
            'qty' => ['required','integer','min:1'],
        ]);

        $item = CartItem::findOrFail($data['item_id']);
        $item->qty = $data['qty'];
        $item->line_total_cents = $item->unit_price_cents * $item->qty;
        $item->save();

        return response()->json(['ok' => true]);
    }

    public function remove(Request $request)
    {
        $data = $request->validate([
            'item_id' => ['required','integer','exists:cart_items,id'],
        ]);

        CartItem::findOrFail($data['item_id'])->delete();

        return response()->json(['ok' => true]);
    }

    public function summary(Request $request)
    {
        $cart = $this->getUserCart($request)->load('items');

        return [
            'total_qty' => $cart->totalQty(),
            'total_sum_cents' => $cart->totalCents(),
        ];
    }
}