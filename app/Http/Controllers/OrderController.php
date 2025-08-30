<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Order;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OrderController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->latest('placed_at')
            ->withCount('items')
            ->paginate(20);

        return Inertia::render('Profile/Orders/Index', [
            'orders' => $orders->through(fn($o) => [
                'id' => $o->id,
                'status' => $o->status,
                'placed_at' => optional($o->placed_at)->toDateTimeString(),
                'total_cents' => $o->total_cents,
                'items_count' => $o->items_count,
            ]),
        ]);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        // Загружаем вместе с options и group (чтобы был доступ к title)
        $order->load('items.product', 'items.options.group');

        return Inertia::render('Profile/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'placed_at' => optional($order->placed_at)->toDateTimeString(),
                'total_cents' => $order->total_cents,
                'currency' => $order->currency,
                'shipping_address' => $order->shipping_address,

                'items' => $order->items->map(function ($i) {
                    // value-опции (radio/checkbox)
                    $valueOptions = $i->options
                        ->filter(fn($o) => !is_null($o->option_value_id))
                        ->map(fn($o) => [
                            'title' => $o->title,
                            'price_delta_cents' => $o->price_delta_cents,
                        ])
                        ->values();

                    // range-опции (double_range_slider)
                    $rangeOptions = $i->options
                        ->filter(fn($o) => !is_null($o->option_group_id))
                        ->map(fn($o) => [
                            'title' => $o->group?->title ?? 'Range',
                            'label' => ((int)$o->selected_min) . '-' . ((int)$o->selected_max),
                        ])
                        ->values();

                    return [
                        'product_name' => $i->product_name,
                        'image_url' => $i->product?->image_url,
                        'qty' => $i->qty,
                        'unit_price_cents' => $i->unit_price_cents,
                        'line_total_cents' => $i->line_total_cents,
                        'options' => $valueOptions,
                        'ranges' => $rangeOptions, // ⬅️ теперь это массив {title,label}
                    ];
                })->values(),
            ],
        ]);
    }
}
