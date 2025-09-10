<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\OptionGroup;
use App\Services\Cart\CartTools;
use Illuminate\Support\Facades\Log;


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
        $promo = null;
        if ($pid = $request->session()->get('checkout.promo.id')) {
            $promo = \App\Models\PromoCode::find($pid);
        }

        $totals = $this->calcTotalsWithPromo($cart, $promo, $request->user()->id);
        return Inertia::render('Checkout/Index', [
            'stripePk' => config('services.stripe.key'),
            'totals' => $totals,
            'promo'  => $promo ? [
                'code' => $promo->code,
                'discount_cents' => $totals['discount_cents'],
            ] : null,
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

        $promo = null;
        if ($pid = $request->session()->get('checkout.promo.id')) {
            $promo = \App\Models\PromoCode::find($pid);
        }
        $totals = $this->calcTotalsWithPromo($cart, $promo, $user->id);

        // создаём pending-заказ + снапшоты позиций
        $order = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user, $cart, $totals, $promo) {
            $order = \App\Models\Order::create([
                'user_id'        => $user->id,
                'status'         => \App\Models\Order::STATUS_PENDING,
                'currency'       => $totals['currency'],
                'subtotal_cents' => $totals['subtotal_cents'],
                'shipping_cents' => $totals['shipping_cents'],
                'tax_cents'      => $totals['tax_cents'],
                'promo_code_id'  => $promo?->id,
                'promo_discount_cents' => $totals['discount_cents'],
                'total_cents'    => $totals['total_cents'],
                'payment_method' => 'test',
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
                    'status'            => OrderItem::STATUS_PENDING,

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

        
        $request->session()->forget('checkout.promo');

        DB::afterCommit(function () use ($order) {
            event(new \App\Events\OrderWorkflowUpdated($order->id));
        });

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

        $promo = null;
        if ($pid = $request->session()->get('checkout.promo.id')) {
            $promo = \App\Models\PromoCode::find($pid);
        }
        $totals = $this->calcTotalsWithPromo($cart, $promo, $user->id);

        // 2) Создаём заказ (PENDING) и снапшоты позиций ДО Stripe
        $order = DB::transaction(function () use ($request, $user, $cart,  $totals, $promo) {
            $order = Order::create([
                'user_id'        => $user->id,
                'status'         => Order::STATUS_PENDING,
                'currency'       => $totals['currency'],
                'subtotal_cents' => $totals['subtotal_cents'],
                'shipping_cents' => $totals['shipping_cents'],
                'tax_cents'      => $totals['tax_cents'],
                'promo_code_id'  => $promo?->id,
                'promo_discount_cents' => $totals['discount_cents'],
                'total_cents'    => $totals['total_cents'],
                'payment_method' => 'stripe',
                'payment_id'     => null,
                'placed_at'      => null,
                'game_payload'   => [
                    'nickname' => $request->session()->get('checkout.nickname'),
                ],
            ]);


            foreach ($cart->items as $ci) {
                $oi = $order->items()->create([
                    'product_id'        => $ci->product_id,
                    'product_name'      => $ci->product->name,
                    'unit_price_cents'  => $ci->unit_price_cents,
                    'qty'               => $ci->qty,
                    'line_total_cents'  => $ci->line_total_cents,
                    'status'            => OrderItem::STATUS_PENDING,
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
        DB::afterCommit(function () use ($order) {
            Log::info('Broadcast OrderWorkflowUpdated (after create pending)', ['order_id' => $order->id]);
            event(new \App\Events\OrderWorkflowUpdated($order->id));
        });

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
            $currency = strtolower($totals['currency'] ?? 'USD');
            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
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
        $discounts = [];
        if ($promo && $totals['discount_cents'] > 0) {
            // 1) попробуем использовать уже привязанный купон
            $couponId = $promo->stripe_coupon_id;

            // 2) Если нет, создадим (duration = once)
            if (!$couponId) {
                if ($promo->type === 'percent') {
                    $coupon = $stripe->coupons->create([
                        'percent_off' => (float) $promo->value_percent,
                        'duration'    => 'once',
                        'name'        => $promo->code,
                    ]);
                } else {
                    // amount_off должен знать валюту
                    $coupon = $stripe->coupons->create([
                        'amount_off' => $promo->value_cents, // в центах
                        'currency'   => $currency,              // подставь, если нужна другая валюта
                        'duration'   => 'once',
                        'name'       => $promo->code,
                    ]);
                }
                $promo->update([
                    'stripe_coupon_id'        => $coupon->id,
                    'stripe_coupon_currency' => $promo->type === 'amount' ? $currency : null,
                ]);
                $couponId = $coupon->id;
            }

            $discounts = [['coupon' => $couponId]];
        }

        // Создание Checkout Session
        $session = $stripe->checkout->sessions->create([
            'mode'                   => 'payment',
            'payment_method_types'   => ['card'],
            'line_items'             => $lineItems, // как у тебя
            'discounts'              => $discounts, // ← Stripe сам применит скидку
            'success_url'            => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'             => route('checkout.cancel'),
            'metadata'               => [
                'user_id'  => (string) $user->id,
                'order_id' => (string) $order->id,
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
        $request->session()->forget('checkout.promo');
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        

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

    private function calcTotalsWithPromo(\App\Models\Cart $cart, ?\App\Models\PromoCode $promo, ?int $userId): array
    {
        $subtotal = (int) $cart->items->sum('line_total_cents');
        $shipping = 0;
        $tax      = 0;

        $discount = 0;
        if ($promo) {
            [$ok] = $promo->isApplicable($userId, $subtotal);
            if ($ok) {
                $discount = $promo->computeDiscountCents($subtotal);
            }
        }

        $total = max(0, $subtotal + $shipping + $tax - $discount);

        return [
            'subtotal_cents' => $subtotal,
            'shipping_cents' => $shipping,
            'tax_cents'      => $tax,
            'discount_cents' => $discount,
            'total_cents'    => $total,
            'currency'       => 'USD',
        ];
    }

    public function applyPromo(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:64']]);
        $code = (string) $request->string('code')->trim()->upper();

        $promo = \App\Models\PromoCode::where('code', $code)->first();
        if (!$promo) return response()->json(['ok' => false, 'message' => 'Promo code not found'], 404);

        $cart = \App\Models\Cart::firstOrCreate(['user_id' => $request->user()->id])->load('items');
        if ($cart->items->isEmpty()) return response()->json(['ok' => false, 'message' => 'Cart is empty'], 422);

        [$ok, $reason] = $promo->isApplicable($request->user()->id, (int) $cart->items->sum('line_total_cents'));
        if (!$ok) return response()->json(['ok' => false, 'message' => $reason ?? 'Code is not applicable'], 422);

        // Сохраняем в сессию
        $request->session()->put('checkout.promo', [
            'code'  => $promo->code,
            'id'    => $promo->id,
        ]);

        $totals = $this->calcTotalsWithPromo($cart, $promo, $request->user()->id);

        return response()->json([
            'ok' => true,
            'promo' => [
                'code' => $promo->code,
                'discount_cents' => $totals['discount_cents'],
            ],
            'totals' => $totals,
        ]);
    }

    public function removePromo(Request $request)
    {
        $request->session()->forget('checkout.promo');
        $cart = \App\Models\Cart::firstOrCreate(['user_id' => $request->user()->id])->load('items');

        $totals = $this->calcTotalsWithPromo($cart, null, $request->user()->id);

        return response()->json([
            'ok' => true,
            'promo' => null,
            'totals' => $totals,
        ]);
    }
}
