<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\OptionGroup;
use App\Services\Cart\CartTools;


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
                    ->sortBy(function ($o) {
                        $v = $o->optionValue;
                        $g = $v->group;
                        $priority = match ($g->code ?? null) {
                            'class' => 0,
                            'slot'  => 1,
                            'affix' => 2,
                            default => 100,
                        };
                        return $priority * 1_000_000
                            + (int)($g->position ?? 0) * 1_000
                            + (int)($v->position ?? 0);
                    })
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
                            'is_ga'         => (bool) $o->is_ga,
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
            'nickname' => $request->session()->get('checkout.nickname'),
        ]);
    }


    public function saveNickname(Request $request)
    {
        $data = $request->validate([
            'nickname' => [
                'nullable',
                'string',
                'min:2',
                'max:30',
                'regex:/^[A-Za-z0-9_]+$/', // латиница/цифры/подчёркивание, без пробелов
            ],
        ], [
            'nickname.regex' => 'Ник может содержать только латинские буквы, цифры и подчёркивание (_), без пробелов.',
        ]);

        if (filled($data['nickname'])) {
            $request->session()->put('checkout.nickname', $data['nickname']);
        } else {
            $request->session()->forget('checkout.nickname');
        }

        return response()->json(['ok' => true]);
    }

    public function createTestPending(Request $request)
    {
        $user = $request->user();

        // берём текущую корзину
        $cart = \App\Models\Cart::firstOrCreate(['user_id' => $user->id])
            ->load(['items.product', 'items.options.group', 'items.options.optionValue.group']);

        abort_if($cart->items->isEmpty(), 422, 'Cart is empty');

        // считаем суммы
        $subtotal = $cart->items->sum('line_total_cents');
        $shipping = 0;
        $tax      = 0;
        $total    = $subtotal + $shipping + $tax;

        // создаём pending-заказ + снапшоты позиций
        $order = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user, $cart, $subtotal, $shipping, $tax, $total) {
            $order = \App\Models\Order::create([
                'user_id'        => $user->id,
                'status'         => \App\Models\Order::STATUS_PENDING,
                'currency'       => 'USD',
                'subtotal_cents' => $subtotal,
                'shipping_cents' => $shipping,
                'tax_cents'      => $tax,
                'total_cents'    => $total,
                'payment_method' => 'test', // помечаем, что это тестовая кнопка
                'payment_id'     => null,
                'placed_at'      => null,
                'game_payload'   => [
                    'nickname' => $request->session()->get('checkout.nickname'),
                ],
                'checkout_session_id' => null,
            ]);

            foreach ($cart->items as $ci) {
                $oi = $order->items()->create([
                    'product_id'        => $ci->product_id,
                    'product_name'      => $ci->product->name,
                    'unit_price_cents'  => $ci->unit_price_cents,
                    'qty'               => $ci->qty,
                    'line_total_cents'  => $ci->line_total_cents,

                ]);

                foreach ($ci->options as $opt) {
                    // value-опция
                    if (!is_null($opt->option_value_id)) {
                        $ov = \App\Models\OptionValue::with('group')->find($opt->option_value_id);
                        $g  = $ov?->group;

                        $delta = 0;
                        if ($g) {
                            if (($g->type ?? null) === \App\Models\OptionGroup::TYPE_SELECTOR || ($g->type ?? null) === 'selector') {
                                if (($g->pricing_mode ?? 'absolute') === 'percent') {
                                    // процент можно при желании писать в payload_json
                                } else {
                                    $delta = (int)($ov->delta_cents ?? $ov->price_delta_cents ?? 0);
                                }
                            } elseif (in_array($g->type ?? null, [
                                \App\Models\OptionGroup::TYPE_RADIO_PERCENT,
                                \App\Models\OptionGroup::TYPE_CHECKBOX_PERCENT,
                            ], true)) {
                                // проценты — можно в payload_json
                            } else {
                                $delta = (int)($ov->price_delta_cents ?? 0);
                            }
                        }

                        $oi->options()->create([
                            'option_value_id'   => $opt->option_value_id,
                            'title'             => $ov?->title ?? 'Option',
                            'price_delta_cents' => $delta,
                            'is_ga'             => (bool) $opt->is_ga,
                        ]);
                        continue;
                    }

                    // range-опция
                    if (!is_null($opt->option_group_id)) {
                        $oi->options()->create([
                            'option_value_id'   => null,
                            'option_group_id'   => $opt->option_group_id,
                            'title'             => $opt->group?->title ?? 'Range',
                            'price_delta_cents' => (int)($opt->price_delta_cents ?? 0),
                            'selected_min'      => (int)($opt->selected_min ?? 0),
                            'selected_max'      => (int)($opt->selected_max ?? 0),
                            'payload_json'      => $opt->payload_json ?? null,
                        ]);
                        continue;
                    }
                }
            }

            return $order;
        });

        // чистим корзину сразу (как и при обычном checkout)
        \App\Services\Cart\CartTools::clearUserCart($user->id);

        return response()->json([
            'ok'       => true,
            'order_id' => $order->id,
            'redirect' => route('orders.show', $order),
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

        // 1) Считаем суммы
        $subtotal = $cart->items->sum('line_total_cents');
        $shipping = 0;
        $tax      = 0;
        $total    = $subtotal + $shipping + $tax;

        // 2) Создаём заказ (PENDING) и снапшоты позиций ДО Stripe
        $order = DB::transaction(function () use ($request, $user, $cart, $subtotal, $shipping, $tax, $total) {
            $order = Order::create([
                'user_id'        => $user->id,
                'status'         => Order::STATUS_PENDING, // явное указание
                'currency'       => 'USD',
                'subtotal_cents' => $subtotal,
                'shipping_cents' => $shipping,
                'tax_cents'      => $tax,
                'total_cents'    => $total,
                'payment_method' => 'stripe',
                'payment_id'     => null,
                'placed_at'      => null,
                'game_payload'   => [
                    'nickname' => $request->session()->get('checkout.nickname'),
                ],
            ]);

            // снапшоты айтемов и опций (то, что у тебя было в success())
            foreach ($cart->items as $ci) {
                $oi = $order->items()->create([
                    'product_id'        => $ci->product_id,
                    'product_name'      => $ci->product->name,
                    'unit_price_cents'  => $ci->unit_price_cents,
                    'qty'               => $ci->qty,
                    'line_total_cents'  => $ci->line_total_cents,
                ]);

                foreach ($ci->options as $opt) {
                    // 1) value-опция
                    if ($opt->option_value_id !== null) {
                        $ov = \App\Models\OptionValue::with('group')->find($opt->option_value_id);
                        $g  = $ov?->group;

                        $delta = 0;
                        if ($g) {
                            if (($g->type ?? null) === \App\Models\OptionGroup::TYPE_SELECTOR || ($g->type ?? null) === 'selector') {
                                if (($g->pricing_mode ?? 'absolute') === 'percent') {
                                    // процент можно при желании сохранить в payload_json
                                } else {
                                    $delta = (int)($ov->delta_cents ?? $ov->price_delta_cents ?? 0);
                                }
                            } elseif (in_array($g->type ?? null, [
                                \App\Models\OptionGroup::TYPE_RADIO_PERCENT,
                                \App\Models\OptionGroup::TYPE_CHECKBOX_PERCENT,
                            ], true)) {
                                // проценты — при желании в payload_json
                            } else {
                                $delta = (int)($ov->price_delta_cents ?? 0);
                            }
                        }

                        $oi->options()->create([
                            'option_value_id'   => $opt->option_value_id,
                            'title'             => $ov?->title ?? 'Option',
                            'price_delta_cents' => $delta,
                            'is_ga'             => (bool) $opt->is_ga,   // ⬅️ добавили
                        ]);
                        continue;
                    }

                    // 2) range-опция
                    if ($opt->option_group_id !== null) {
                        $oi->options()->create([
                            'option_value_id'   => null,
                            'option_group_id'   => $opt->option_group_id,
                            'title'             => $opt->group?->title ?? 'Range',
                            'price_delta_cents' => (int)($opt->price_delta_cents ?? 0),
                            'selected_min'      => (int)($opt->selected_min ?? 0),
                            'selected_max'      => (int)($opt->selected_max ?? 0),
                            'payload_json'      => $opt->payload_json ?? null,
                        ]);
                        continue;
                    }
                }
            }

            return $order;
        });

        CartTools::clearUserCart($user->id);

        // 3) Готовим line items для Stripe (как у тебя было)
        $optIds = $cart->items->flatMap(fn($ci) => $ci->options->pluck('option_value_id'))->filter()->unique()->values();
        $ovMap  = OptionValue::with('group')->whereIn('id', $optIds)->get()->keyBy('id');

        $lineItems = [];
        foreach ($cart->items as $ci) {
            $optTitles = $ci->options
                ->filter(fn($o) => !is_null($o->option_value_id))
                ->map(function ($o) use ($ovMap) {
                    $t = $ovMap->get($o->option_value_id)?->title;
                    return $t ? $t . ($o->is_ga ? ' [GA]' : '') : null;
                })
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
            $name     = $nameBase . ' x' . $ci->qty;

            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $name,
                        // по желанию можно продублировать разметку сюда:
                        // 'description' => implode(' | ', $parts),
                    ],
                    'unit_amount' => $ci->line_total_cents,
                ],
                'quantity' => 1,
            ];
        }

        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        /** @var \Stripe\Checkout\Session $session */
        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => route('checkout.cancel'),
            'metadata'    => [
                'user_id'  => (string) $user->id,
                'order_id' => (string) $order->id, // удобно на вебхуке
            ],
        ]);

        // 4) Сохраняем связь заказа с сессией Stripe
        $order->update([
            'checkout_session_id' => $session->id,
            'payment_id'          => $session->id, // можно одинаково хранить
        ]);

        return response()->json([
            'id'  => $session->id,
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

        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
        $session = $stripe->checkout->sessions->retrieve($sessionId, []);

        // Попробуем найти заказ по session_id
        $order = Order::where('checkout_session_id', $sessionId)->first();

        // Если webhook уже успел → будет PAID, если нет — покажем страничку "обрабатывается"
        if ($order) {
            // Можно сразу на страницу заказа
            return redirect()->route('orders.show', $order)->with('success', $order->status === Order::STATUS_PAID
                ? 'Payment confirmed!'
                : 'Payment is being processed...');
        }

        // Фоллбек
        return redirect()->route('orders.index')->with('success', 'Payment is being processed...');
    }


    public function cancel()
    {
        // пользователь отменил оплату на Stripe
        return redirect()->route('checkout.index')->with('error', 'Payment was cancelled.');
    }
}
