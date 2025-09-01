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
        $order->load(
            'items.product.optionGroups',
            'items.options.optionValue.group',
            'items.options.group'
        );

        return Inertia::render('Profile/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'placed_at' => optional($order->placed_at)->toDateTimeString(),
                'total_cents' => $order->total_cents,
                'currency' => $order->currency,
                'shipping_address' => $order->shipping_address,

                'items' => $order->items->map(function ($i) {
                    // value-опции (radio/checkbox): как в корзине/чекауте
                    $valueOptions = $i->options
                        ->filter(fn($o) => $o->option_value_id && $o->optionValue && $o->optionValue->group)
                        ->sortBy([
                            fn($o) => $o->optionValue->group->position ?? 0,
                            fn($o) => $o->optionValue->position ?? 0,
                        ])
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
                        ->values();

                    // range-опции (double_range_slider)
                    $rangeOptions = $i->options
                        ->filter(fn($o) => !is_null($o->option_group_id))
                        ->map(fn($o) => [
                            'title' => $o->group?->title ?? 'Range',
                            'label' => ((int)$o->selected_min) . '-' . ((int)$o->selected_max),
                        ])
                        ->values();

                    $hasQtySlider = (bool) $i->product?->optionGroups
                        ->contains('type', \App\Models\OptionGroup::TYPE_SLIDER);

                    return [
                        'product_name' => $i->product_name,
                        'image_url' => $i->product?->image_url,
                        'qty' => $i->qty,
                        'unit_price_cents' => $i->unit_price_cents,
                        'line_total_cents' => $i->line_total_cents,


                        'options' => $valueOptions,
                        'ranges' => $rangeOptions,
                        'has_qty_slider' => $hasQtySlider,
                    ];
                })->values(),
            ],
        ]);
    }
}
