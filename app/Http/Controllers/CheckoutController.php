<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\OptionGroup;
use Stripe\StripeClient;
use App\Models\{Cart, OptionValue, Order, OrderItem};

class CheckoutController extends Controller
{
    public function index(Request $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id])
            ->load([
                'items.product.optionGroups',        // 👈 чтобы знать есть ли qty slider
                'items.options.optionValue.group',   // 👈 чтобы знать тип группы и %/unit/total
            ]);

        abort_if($cart->items->isEmpty(), 404, 'Your cart is empty.');

        return Inertia::render('Checkout/Index', [
            'stripePk' => config('services.stripe.key'),
            'items' => $cart->items->map(function ($i) {

                $rangeLabels = $i->options
                    ->filter(fn($o) => !is_null($o->option_group_id))
                    ->map(fn($o) => ((int)$o->selected_min) . '-' . ((int)$o->selected_max))
                    ->values()
                    ->all();


                $optionLabels = $i->options
                    ->filter(fn($o) => $o->option_value_id && $o->optionValue && $o->optionValue->group)
                    ->sortBy([fn($o) => $o->optionValue->group->position ?? 0, fn($o) => $o->optionValue->position ?? 0])
                    ->map(function ($o) {
                        $v = $o->optionValue;
                        $g = $v->group;

                        $mode = 'absolute';
                        $valueCents = null;
                        $valuePercent = null;

                        if (($g->type ?? null) === \App\Models\OptionGroup::TYPE_SELECTOR || ($g->type ?? null) === 'selector') {
                            $mode = ($g->pricing_mode === 'percent') ? 'percent' : 'absolute';
                            if ($mode === 'percent') {
                                $valuePercent = (float)($v->delta_percent ?? $v->value_percent ?? 0);
                            } else {
                                $valueCents = (int)($v->delta_cents ?? $v->price_delta_cents ?? 0);
                            }
                        } elseif (in_array($g->type ?? null, [
                            \App\Models\OptionGroup::TYPE_RADIO_PERCENT,
                            \App\Models\OptionGroup::TYPE_CHECKBOX_PERCENT,
                        ], true)) {
                            $mode = 'percent';
                            $valuePercent = (float)($v->value_percent ?? 0);
                        } else {
                            $mode = 'absolute';
                            $valueCents = (int)($v->price_delta_cents ?? 0);
                        }

                        return [
                            'id'            => $v->id,
                            'title'         => $v->title,
                            'calc_mode'     => $mode,
                            'scope'         => ($g->multiply_by_qty ?? false) ? 'unit' : 'total',
                            'value_cents'   => $valueCents,
                            'value_percent' => $valuePercent,
                        ];
                    })
                    ->values()
                    ->all();

                $hasQtySlider = (bool) $i->product->optionGroups
                    ->contains('type', \App\Models\OptionGroup::TYPE_SLIDER);

                return [
                    'id' => $i->id,
                    'product' => [
                        'id' => $i->product->id,
                        'name' => $i->product->name,
                        'image_url' => $i->product->image_url,
                    ],
                    'qty' => $i->qty,
                    'unit_price_cents' => $i->unit_price_cents,
                    'line_total_cents' => $i->line_total_cents,
                    'options' => $optionLabels,
                    'range_labels' => $rangeLabels,
                    'has_qty_slider' => $hasQtySlider,
                ];
            })->values(),
            'totals' => [
                'subtotal_cents' => $cart->items->sum('line_total_cents'),
                'shipping_cents' => 0,
                'tax_cents' => 0,
                'total_cents' => $cart->items->sum('line_total_cents'),
                'currency' => 'USD',
            ],
        ]);
    }

    /**
     * Создаёт Stripe Checkout Session и возвращает его id/url
     */
    public function createSession(Request $request)
    {
        $user = $request->user();
        $cart = Cart::firstOrCreate(['user_id' => $user->id])->load(['items.product', 'items.options']);
        abort_if($cart->items->isEmpty(), 422, 'Cart is empty');

        $optIds = $cart->items->flatMap(fn($ci) => $ci->options->pluck('option_value_id'))->filter()->unique()->values();
        $ovMap = OptionValue::whereIn('id', $optIds)->get()->keyBy('id');

        $lineItems = [];
        foreach ($cart->items as $ci) {
            $optTitles = $ci->options
                ->filter(fn($o) => !is_null($o->option_value_id))
                ->map(fn($o) => $ovMap->get($o->option_value_id)?->title)
                ->filter()
                ->values()
                ->all();

            $rangeLabels = $ci->options
                ->filter(fn($o) => !is_null($o->option_group_id))
                ->map(fn($o) => ((int)$o->selected_min) . '-' . ((int)$o->selected_max))
                ->values()
                ->all();

            $parts = [];
            if (count($optTitles))   $parts[] = implode(', ', $optTitles);
            if (count($rangeLabels)) $parts[] = implode(', ', $rangeLabels);

            $nameBase = $ci->product->name . (count($parts) ? ' (' . implode(' | ', $parts) . ')' : '');
            $name = $nameBase . ' x' . $ci->qty; // 👈 чтобы видно было кол-во

            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => ['name' => $name],
                    'unit_amount' => $ci->line_total_cents,  // 👈 ВСЯ сумма по строке
                ],
                'quantity' => 1,                              // 👈 одна строка = одна позиция
            ];
        }

        $stripe = new StripeClient(config('services.stripe.secret'));

        /** @var \Stripe\Checkout\Session $session */
        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => route('checkout.cancel'),
            'metadata'    => ['user_id' => (string) $user->id],
        ]);

        return response()->json([
            'id'  => $session->id,  // ✅ Intelephense перестанет ругаться
            'url' => $session->url,
        ]);
    }

    /**
     * Возврат со Stripe после успешной оплаты.
     * Для MVP: проверяем session.payment_status == 'paid' и создаём Order.
     * (В проде лучше через Webhook.)
     */
    public function success(Request $request)
    {
        $sessionId = (string) $request->query('session_id');
        abort_unless($sessionId, 400, 'Missing session_id');

        $stripe = new StripeClient(config('services.stripe.secret'));

        /** @var \Stripe\Checkout\Session $session */
        $session = $stripe->checkout->sessions->retrieve($sessionId, []);

        abort_unless($session && $session->payment_status === 'paid', 402, 'Payment not completed');

        $userId = (int)($session->metadata['user_id'] ?? 0);
        $currentId = (int) Auth::id();
        abort_unless($currentId === $userId, 403, 'Wrong user');

        $order = DB::transaction(function () use ($userId, $sessionId) { // 👈 добавили $sessionId
            $cart = Cart::firstOrCreate(['user_id' => $userId])
                ->load(['items.product', 'items.options.group']);
            abort_if($cart->items->isEmpty(), 422, 'Cart is empty');

            $subtotal = $cart->items->sum('line_total_cents');
            $shipping = 0;
            $tax = 0;
            $total = $subtotal + $shipping + $tax;

            $order = Order::create([
                'user_id' => $userId,
                'status' => 'paid',
                'currency' => 'USD',
                'subtotal_cents' => $subtotal,
                'shipping_cents' => $shipping,
                'tax_cents' => $tax,
                'total_cents' => $total,
                'payment_method' => 'stripe',
                'payment_id' => $sessionId, // ✅ теперь доступна
                'placed_at' => now(),
            ]);

            // снапшотим айтемы
            foreach ($cart->items as $ci) {
                $oi = $order->items()->create([
                    'product_id' => $ci->product_id,
                    'product_name' => $ci->product->name,
                    'unit_price_cents' => $ci->unit_price_cents,
                    'qty' => $ci->qty,
                    'line_total_cents' => $ci->line_total_cents,
                ]);

                foreach ($ci->options as $opt) {
                    // 1) value-опция (radio/checkbox)
                    if ($opt->option_value_id !== null) {
                        $ov = \App\Models\OptionValue::find($opt->option_value_id);
                        $oi->options()->create([
                            'option_value_id'   => $opt->option_value_id,
                            'title'             => $ov?->title ?? 'Option',
                            'price_delta_cents' => (int)($ov?->price_delta_cents ?? 0),
                        ]);
                        continue;
                    }

                    // 2) range-опция (double_range_slider) — определяем по наличию option_group_id
                    if ($opt->option_group_id !== null) {
                        $oi->options()->create([
                            'option_value_id'   => null,
                            'option_group_id'   => $opt->option_group_id,
                            'title'             => $opt->group?->title ?? 'Range', // ✅ берём group title
                            'price_delta_cents' => (int)($ov?->delta_cents ?? $ov?->price_delta_cents ?? 0),
                            'selected_min'      => (int)($opt->selected_min ?? 0),
                            'selected_max'      => (int)($opt->selected_max ?? 0),
                            'payload_json'      => $opt->payload_json ?? null,
                        ]);
                        continue;
                    }

                    // 3) Фоллбек: неизвестная форма — при желании логируем
                    // logger()->warning('Unknown cart item option shape', ['opt_id' => $opt->id]);
                }
            }

            // очистка корзины
            foreach ($cart->items as $ci) {
                $ci->delete();
            }

            return $order;
        });

        return redirect()->route('orders.show', $order)->with('success', 'Order placed!');
    }

    public function cancel()
    {
        // пользователь отменил оплату на Stripe
        return redirect()->route('checkout.index')->with('error', 'Payment was cancelled.');
    }
}
