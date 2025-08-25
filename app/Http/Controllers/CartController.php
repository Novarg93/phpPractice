<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Models\{Cart, CartItem, CartItemOption, Product, OptionValue, OptionGroup};

class CartController extends Controller
{
    // ===== Helpers

    private function isGuest(Request $r): bool
    {
        return !$r->user();
    }

    private function getUserCart(Request $r): Cart
    {
        return Cart::firstOrCreate(['user_id' => $r->user()->id]);
    }

    // Session cart structure:
    // session('guest_cart') = [
    //   ['id' => 'uuid', 'product_id' => 1, 'qty' => 2, 'unit_price_cents' => 1234, 'line_total_cents' => 2468, 'option_value_ids' => [5,9]]
    // ]
    private function getGuestCart(Request $r): array
    {
        return $r->session()->get('guest_cart', []);
    }

    private function saveGuestCart(Request $r, array $items): void
    {
        $r->session()->put('guest_cart', array_values($items));
    }

    private function computeUnitPriceCents(int $productId, array $optionValueIds): int
    {
        $product = Product::findOrFail($productId);
        $deltaSum = OptionValue::whereIn('id', $optionValueIds)->sum('price_delta_cents');
        return $product->price_cents + $deltaSum;
    }

    private function normalizeOptionIds($ids): array
    {
        return collect($ids ?? [])->unique()->sort()->values()->all();
    }

    private function validateSelection(int $productId, array $optionValueIds): void
    {
        // Базовая защита: все option_value_ids должны существовать и принадлежать группам этого продукта,
        // а радиогруппы — не более 1 выбранного, required — соблюдены.
        $product = Product::with('optionGroups.values')->findOrFail($productId);
        $chosen = collect($optionValueIds);

        // принадлежность
        foreach ($chosen as $vid) {
            $belongs = $product->optionGroups->first(fn($g) => $g->values->contains('id', $vid));
            abort_unless($belongs, 422, 'Invalid option value selected.');
        }

        // правила групп
        foreach ($product->optionGroups as $g) {
            $selectedInGroup = $chosen->filter(fn($vid) => $g->values->contains('id', $vid));
            if ($g->type === OptionGroup::TYPE_RADIO) {
                if ($selectedInGroup->count() > 1) abort(422, 'Only one option can be selected in "'.$g->title.'".');
                if ($g->is_required && $selectedInGroup->count() !== 1) abort(422, '"'.$g->title.'" is required.');
            } else {
                if ($g->is_required && $selectedInGroup->count() < 1) abort(422, 'Select at least one in "'.$g->title.'".');
            }
        }
    }

    // ===== Pages

    public function index(Request $request)
    {
        if ($this->isGuest($request)) {
            $items = $this->getGuestCart($request);

            $totalQty = collect($items)->sum('qty');
            $totalSum = collect($items)->sum('line_total_cents');

            return Inertia::render('Cart/Index', [
                'items' => collect($items)->map(function ($i) {
                    $p = Product::find($i['product_id']);
                    return [
                        'id' => $i['id'],
                        'product' => [
                            'id' => $p?->id,
                            'name' => $p?->name ?? 'Unknown',
                            'image' => $p?->image ?? null,
                        ],
                        'qty' => $i['qty'],
                        'unit_price_cents' => $i['unit_price_cents'],
                        'line_total_cents' => $i['line_total_cents'],
                    ];
                })->values(),
                'total_qty' => $totalQty,
                'total_sum_cents' => $totalSum,
            ]);
        }

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
            'total_qty' => $cart->items->sum('qty'),
            'total_sum_cents' => $cart->items->sum('line_total_cents'),
        ]);
    }

    // ===== API

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required','integer','exists:products,id'],
            'qty' => ['nullable','integer','min:1'],
            'option_value_ids' => ['array'],
            'option_value_ids.*' => ['integer','exists:option_values,id'],
        ]);

        $qty = max(1, (int)($data['qty'] ?? 1));
        $optionIds = $this->normalizeOptionIds($data['option_value_ids'] ?? []);
        $this->validateSelection($data['product_id'], $optionIds);

        $unit = $this->computeUnitPriceCents($data['product_id'], $optionIds);

        if ($this->isGuest($request)) {
            $items = $this->getGuestCart($request);

            // Если уже есть позиция с тем же продуктом и тем же набором опций — просто увеличим qty
            $foundIndex = collect($items)->search(function ($i) use ($data, $optionIds, $unit) {
                return $i['product_id'] === $data['product_id']
                    && collect($i['option_value_ids'])->sort()->values()->all() === $optionIds
                    && $i['unit_price_cents'] === $unit;
            });

            if ($foundIndex !== false) {
                $items[$foundIndex]['qty'] += $qty;
                $items[$foundIndex]['line_total_cents'] = $items[$foundIndex]['unit_price_cents'] * $items[$foundIndex]['qty'];
            } else {
                $items[] = [
                    'id' => (string) Str::uuid(),
                    'product_id' => $data['product_id'],
                    'qty' => $qty,
                    'unit_price_cents' => $unit,
                    'line_total_cents' => $unit * $qty,
                    'option_value_ids' => $optionIds,
                ];
            }

            $this->saveGuestCart($request, $items);
            return response()->json(['ok' => true]);
        }

        // auth user → в БД
        $cart = $this->getUserCart($request);

        // пробуем слить одинаковые позиции
        $existing = $cart->items()
            ->where('product_id', $data['product_id'])
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
                'product_id' => $data['product_id'],
                'qty' => $qty,
                'unit_price_cents' => $unit,
                'line_total_cents' => $unit * $qty,
            ]);
            foreach ($optionIds as $vid) {
                $new->options()->create(['option_value_id' => $vid]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'item_id' => ['required'],
            'qty' => ['required','integer','min:1'],
        ]);

        if ($this->isGuest($request)) {
            $items = $this->getGuestCart($request);
            $i = collect($items)->firstWhere('id', $data['item_id']);
            abort_if(!$i, 404);

            $i['qty'] = (int)$data['qty'];
            $i['line_total_cents'] = $i['unit_price_cents'] * $i['qty'];

            // перезапись
            $items = collect($items)->map(fn($row) => $row['id'] === $i['id'] ? $i : $row)->values()->all();
            $this->saveGuestCart($request, $items);

            return response()->json(['ok' => true]);
        }

        $item = CartItem::findOrFail($data['item_id']);
        $item->qty = (int)$data['qty'];
        $item->line_total_cents = $item->unit_price_cents * $item->qty;
        $item->save();

        return response()->json(['ok' => true]);
    }

    public function remove(Request $request)
    {
        $data = $request->validate(['item_id' => ['required']]);

        if ($this->isGuest($request)) {
            $items = $this->getGuestCart($request);
            $items = collect($items)->reject(fn($i) => $i['id'] === $data['item_id'])->values()->all();
            $this->saveGuestCart($request, $items);
            return response()->json(['ok' => true]);
        }

        CartItem::findOrFail($data['item_id'])->delete();
        return response()->json(['ok' => true]);
    }

    public function summary(Request $request)
    {
        if ($this->isGuest($request)) {
            $items = $this->getGuestCart($request);
            return [
                'total_qty' => collect($items)->sum('qty'),
                'total_sum_cents' => collect($items)->sum('line_total_cents'),
            ];
        }

        $cart = $this->getUserCart($request)->load('items');
        return [
            'total_qty' => $cart->items->sum('qty'),
            'total_sum_cents' => $cart->items->sum('line_total_cents'),
        ];
    }
}