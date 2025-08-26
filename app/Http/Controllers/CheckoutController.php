<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\StripeClient;
use App\Models\{Cart, OptionValue, Order, OrderItem};

class CheckoutController extends Controller
{
    public function index(Request $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id])
            ->load(['items.product', 'items.options']);

        abort_if($cart->items->isEmpty(), 404, 'Your cart is empty.');

        $allOptionIds = $cart->items->flatMap(fn($i) => $i->options->pluck('option_value_id'))->unique()->values();
        $optionMap = OptionValue::whereIn('id', $allOptionIds)->get()->keyBy('id');

        return Inertia::render('Checkout/Index', [
            'stripePk' => config('services.stripe.key'),
            'items' => $cart->items->map(function ($i) use ($optionMap) {
                return [
                    'id' => $i->id,
                    'product' => [
                        'id' => $i->product->id,
                        'name' => $i->product->name,
                        'image' => $i->product->image,
                    ],
                    'qty' => $i->qty,
                    'unit_price_cents' => $i->unit_price_cents,
                    'line_total_cents' => $i->line_total_cents,
                    'options' => $i->options->map(function ($opt) use ($optionMap) {
                        $ov = $optionMap->get($opt->option_value_id);
                        return [
                            'id' => $opt->option_value_id,
                            'title' => $ov?->title ?? 'Option',
                            'price_delta_cents' => (int)($ov?->price_delta_cents ?? 0),
                        ];
                    })->values(),
                ];
            }),
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

        // Собираем подписи опций для имени позиции
        $optIds = $cart->items->flatMap(fn($ci) => $ci->options->pluck('option_value_id'))->unique()->values();
        $ovMap = OptionValue::whereIn('id', $optIds)->get()->keyBy('id');

        $lineItems = [];
        foreach ($cart->items as $ci) {
            $optTitles = $ci->options->map(fn($o) => $ovMap->get($o->option_value_id)?->title)->filter()->values()->all();
            $name = $ci->product->name . (count($optTitles) ? ' (' . implode(', ', $optTitles) . ')' : '');
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => ['name' => $name],
                    'unit_amount' => $ci->unit_price_cents, // в центах!
                ],
                'quantity' => $ci->qty,
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
            $cart = Cart::firstOrCreate(['user_id' => $userId])->load(['items.product', 'items.options']);
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
                    $ov = OptionValue::find($opt->option_value_id);
                    $oi->options()->create([
                        'option_value_id'   => $opt->option_value_id,
                        'title'             => $ov?->title ?? 'Option',
                        'price_delta_cents' => (int)($ov?->price_delta_cents ?? 0),
                    ]);
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
