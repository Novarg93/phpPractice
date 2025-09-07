<?php

namespace App\Http\Controllers;

use App\Events\OrderWorkflowUpdated;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function index(Request $r)
    {
        // отрисовываем Inertia-страницу + первый набор данных
        return Inertia::render('Workflow/Index', [
            'items' => $this->fetchItems(),
        ]);
    }

    public function list(Request $r)
    {
        // только JSON, чтобы фронт дергал при realtime-событиях
        return response()->json([
            'items' => $this->fetchItems(),
        ]);
    }

    public function update(Request $r, OrderItem $item)
    {
        $data = $r->validate([
            'cost_price'  => ['nullable', 'numeric', 'min:0'],
            'status'      => ['nullable', 'in:pending,paid,in_progress,completed,refund'],
            'link_screen' => ['nullable', 'string', 'max:2048'],
        ]);

        return DB::transaction(function () use ($item, $data) {
            $forcedInProgress = false;
            $clientStatus = $data['status'] ?? null;

            // 1) cost -> cents + прибыль
            if (array_key_exists('cost_price', $data)) {
                $item->cost_cents = $data['cost_price'] !== null ? (int) round($data['cost_price'] * 100) : null;
                $item->recalcProfit();

                // если есть себестоимость и (текущий или присланный) статус pending/paid → IN_PROGRESS
                $baseStatus = $clientStatus ?? $item->status;
                if ($item->cost_cents !== null && in_array($baseStatus, ['pending', 'paid'], true)) {
                    $item->status = \App\Models\OrderItem::STATUS_IN_PROGRESS;
                    $forcedInProgress = true;
                }
            }

            // 2) ручной статус — применяем, НО не затираем принудительный IN_PROGRESS на pending/paid
            if (!empty($clientStatus)) {
                if ($forcedInProgress && in_array($clientStatus, ['pending', 'paid'], true)) {
                    // игнорируем откат
                } else {
                    $item->status = $clientStatus;
                }
            }

            if (array_key_exists('link_screen', $data)) {
                $item->link_screen = $data['link_screen'] ?: null;
            }

            $item->save();

            /** @var Order $order */
            $order = $item->order()->firstOrFail();
            $order->recalcTotals();
            $order->syncStatusFromItems();

            // если конкретно этот item стал IN_PROGRESS — поднимем и заказ, если вдруг не поднялся
            if (
                $item->status === \App\Models\OrderItem::STATUS_IN_PROGRESS
                && $order->status !== \App\Models\Order::STATUS_IN_PROGRESS
            ) {
                $order->status = \App\Models\Order::STATUS_IN_PROGRESS;
                $order->save();
            }

            event(new \App\Events\OrderWorkflowUpdated($order->id));

            $refreshed = $this->mapItem($item->fresh(['order.user', 'product', 'options']));
            return response()->json(['item' => $refreshed]);
        });
    }

    /* ===================== helpers ===================== */

    private function fetchItems(): array
    {
        $items = \App\Models\OrderItem::query()
            ->with(['order.user', 'product', 'options'])
            // Вариант А (по заказам и внутри по item): порядок читается блоками по ордерам
            ->orderBy('order_id', 'desc')
            ->orderBy('id', 'asc')
            // Вариант Б (строго по item.id): если нужно — замени двумя строками ниже на одну
            // ->orderBy('id', 'asc')
            ->limit(500) // чтобы не уронить страницу, можно увеличить/убрать по желанию
            ->get();

        return $items->map(fn($i) => $this->mapItem($i))->values()->all();
    }

    private function mapItem(OrderItem $i): array
    {
        $order   = $i->order;
        $user    = $order?->user;
        $payload = $order?->game_payload ?? [];
        $nickname = $payload['nickname'] ?? $payload['character'] ?? null;

        // Показываем paid_at, если есть. Иначе — placed_at, иначе — created_at.
        $dt = $order?->paid_at ?? $order?->placed_at ?? $order?->created_at;

        return [
            'id'             => $i->id,
            'order_id'       => $i->order_id,
            'customer_email' => $user?->email,
            'chatnickname'   => $user?->full_name ?? $user?->name ?? null,
            'character'      => $nickname,
            'item_text'      => $this->buildItemText($i), // у тебя уже отдаёт HTML с <br> и "· "
            'cost_price'     => $i->cost_cents !== null ? round($i->cost_cents / 100, 2) : null,
            'sale_price'     => round(($i->line_total_cents ?? 0) / 100, 2),
            'profit'         => $i->profit_cents !== null ? round($i->profit_cents / 100, 2) : null,
            'margin_percent' => $i->margin_bp !== null ? round($i->margin_bp / 100, 2) : null,
            'status'         => $i->status,

            // Дата — в две строки
            'date'           => $dt ? $dt->format('H:i:s') . '<br>' . $dt->format('d.m.Y') : null,

            // Delivery форматируешь как раньше
            'delivery_time'  => $this->formatDuration($order?->delivery_seconds),

            'link_screen'    => $i->link_screen,
        ];
    }


    private function formatDuration($seconds): ?string
    {
        if (!$seconds || $seconds <= 0) return null;

        $s = (int) $seconds;
        $d = intdiv($s, 86400);
        $s %= 86400;
        $h = intdiv($s, 3600);
        $s %= 3600;
        $m = intdiv($s, 60);
        $s %= 60;

        return $d > 0
            ? sprintf('%dd %02d:%02d:%02d', $d, $h, $m, $s)
            : sprintf('%02d:%02d:%02d', $h, $m, $s);
    }


    private function buildItemText(OrderItem $i): string
    {
        $name = $i->product_name ?: ($i->product?->name ?? 'Item');

        $lines = [];
        foreach ($i->options as $o) {
            $title = $o->title;
            $range = null;

            if (!empty($o->selected_min) || !empty($o->selected_max)) {
                $rangeMin = $o->selected_min ?? '';
                $rangeMax = $o->selected_max ?? '';
                $range = " [{$rangeMin} - {$rangeMax}]";
            } else {
                $p = $o->payload_json ?? [];
                if (isset($p['min']) || isset($p['max'])) {
                    $rangeMin = $p['min'] ?? '';
                    $rangeMax = $p['max'] ?? '';
                    $range = " [{$rangeMin} - {$rangeMax}]";
                }
            }

            $lines[] = e($title . ($range ?? ''));
        }

        // формируем HTML: название + перенос + опции по строкам
        $html = e($name);
        if ($lines) {
            $html .= '<br>' . implode('<br>· ', $lines);
        }

        return $html;
    }

    public function bulkUpdate(Request $r)
    {
        $data = $r->validate([
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.id'              => ['required', 'integer', 'exists:order_items,id'],
            'items.*.cost_price'      => ['nullable', 'numeric', 'min:0'],
            'items.*.status'          => ['nullable', Rule::in(['pending', 'paid', 'in_progress', 'completed', 'refund'])],
            'items.*.link_screen'     => ['nullable', 'string', 'max:2048'],
        ]);

        $orderIds = [];

        $updated = DB::transaction(function () use ($data, &$orderIds) {
            $result = [];

            foreach ($data['items'] as $row) {
                /** @var OrderItem $item */
                $item = OrderItem::with('order')->findOrFail($row['id']);

                $forcedInProgress = false;
                $clientStatus = $row['status'] ?? null;

                if (array_key_exists('cost_price', $row)) {
                    $item->cost_cents = $row['cost_price'] !== null ? (int) round($row['cost_price'] * 100) : null;
                    $item->recalcProfit();

                    $baseStatus = $clientStatus ?? $item->status;
                    if ($item->cost_cents !== null && in_array($baseStatus, ['pending', 'paid'], true)) {
                        $item->status = OrderItem::STATUS_IN_PROGRESS;
                        $forcedInProgress = true;
                    }
                }

                if (!empty($clientStatus)) {
                    if ($forcedInProgress && in_array($clientStatus, ['pending', 'paid'], true)) {
                        // игнорируем откат
                    } else {
                        $item->status = $clientStatus;
                    }
                }

                if (array_key_exists('link_screen', $row)) {
                    $item->link_screen = $row['link_screen'] ?: null;
                }

                $item->save();

                $orderIds[$item->order_id] = true;
                $result[] = $item->fresh(['order.user', 'product', 'options']);
            }

            foreach (array_keys($orderIds) as $oid) {
                if ($order = Order::find($oid)) {
                    $order->recalcTotals();
                    $order->syncStatusFromItems();
                }
            }

            return array_map(fn($i) => $this->mapItem($i), $result);
        });

        DB::afterCommit(function () use ($orderIds) {
            foreach (array_keys($orderIds) as $oid) {
                event(new \App\Events\OrderWorkflowUpdated($oid));
            }
        });

        return response()->json(['items' => $updated]);
    }
}
