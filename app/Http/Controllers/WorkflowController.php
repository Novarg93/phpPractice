<?php

namespace App\Http\Controllers;

use Illuminate\Validation\ValidationException;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;



class WorkflowController extends Controller
{
    public function index(Request $r)
    {
        // отрисовываем Inertia-страницу + первый набор данных
        return Inertia::render('Workflow/Index', [
            'items' => $this->fetchItems(),
        ]);
    }

    private function escapeLike(string $v): string
    {
        // Экранируем спецсимволы для LIKE/ILIKE, чтобы "%", "_" работали как литералы
        return str_replace(['\\',   '%',  '_'], ['\\\\', '\\%', '\\_'], $v);
    }


    public function list(Request $r)
    {
        $statuses = array_values(array_intersect(
            (array) $r->input('statuses', []),
            ['pending', 'paid', 'in_progress', 'completed', 'refund']
        ));
        if (empty($statuses)) {
            $statuses = ['pending', 'paid', 'in_progress', 'completed', 'refund'];
        }

        $q     = trim((string) $r->input('q', ''));
        $limit = min(max((int) $r->input('limit', 50), 10), 200);

        $cOrder = $r->input('cursor.order_id');
        $cItem  = $r->input('cursor.id');

        $itemsQ = \App\Models\OrderItem::query()
            ->with(['order.user', 'order.promoCode', 'product.optionGroups', 'options'])
            ->whereIn('status', $statuses)
            ->orderByDesc('order_id')
            ->orderByDesc('id');

        if ($q !== '') {
            $tokens = array_values(array_filter(preg_split('/\s+/', $q)));
            $itemsQ->where(function ($outer) use ($tokens) {
                foreach ($tokens as $tok) {
                    $outer->where(function ($sub) use ($tok) {
                        $raw = trim($tok);

                        // "#123" → по order_id / item.id
                        if (str_starts_with($raw, '#')) {
                            $num = (int) substr($raw, 1);
                            $sub->where('order_id', $num)
                                ->orWhere('id', $num);
                            return;
                        }

                        // чисто цифры → как подстрока по order_id / id
                        if (ctype_digit($raw)) {
                            $likeNum = '%' . $this->escapeLike($raw) . '%';
                            $sub->where('order_id', 'like', $likeNum)
                                ->orWhere('id', 'like', $likeNum);
                            return;
                        }

                        // --- текстовый токен: ИЩЕМ ТАКЖЕ ПО НИКУ/CHARACTER В JSON ---
                        $nick = ltrim($raw, '@'); // можно вводить с @
                        $like = '%' . $this->escapeLike($nick) . '%';

                        // user/email/имена + product_name
                        $sub->whereHas('order.user', function ($u) use ($like) {
                            $u->where('email', 'like', $like)
                                ->orWhere('name', 'like', $like)
                                ->orWhere('full_name', 'like', $like);
                        })
                            ->orWhere('product_name', 'like', $like)
                            // JSON: orders.game_payload.nickname / character
                            ->orWhereHas('order', function ($ord) use ($like) {
                                $driver = DB::connection()->getDriverName();

                                if ($driver === 'mysql') {
                                    // регистр обычно игнорируется из-за collation, LIKE работает по JSON path
                                    $ord->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(game_payload, '$.nickname')) LIKE ?", [$like])
                                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(game_payload, '$.character')) LIKE ?", [$like]);
                                } elseif ($driver === 'pgsql') {
                                    // регистронезависимый поиск
                                    $ord->whereRaw("(game_payload->>'nickname') ILIKE ?", [$like])
                                        ->orWhereRaw("(game_payload->>'character') ILIKE ?", [$like]);
                                } else { // sqlite с JSON1
                                    $ord->whereRaw("json_extract(game_payload, '$.nickname') LIKE ?", [$like])
                                        ->orWhereRaw("json_extract(game_payload, '$.character') LIKE ?", [$like]);
                                }
                            });
                    });
                }
            });
        }

        // ← total считаем ПОСЛЕ фильтров/поиска, НО ДО курсора
        $total = (clone $itemsQ)->reorder()->count('order_items.id');

        // курсор (загружаем "старше")
        if ($cOrder && $cItem) {
            $itemsQ->where(function ($w) use ($cOrder, $cItem) {
                $w->where('order_id', '<', $cOrder)
                    ->orWhere(function ($w2) use ($cOrder, $cItem) {
                        $w2->where('order_id', '=', $cOrder)
                            ->where('id', '<', $cItem);
                    });
            });
        }

        $rows    = $itemsQ->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows    = $rows->take($limit);

        $mapped = $rows->map(fn($i) => $this->mapItem($i))->values();

        $nextCursor = null;
        if ($hasMore) {
            $last = $rows->last();
            $nextCursor = ['order_id' => $last->order_id, 'id' => $last->id];
        }

        return response()->json([
            'items'       => $mapped,
            'next_cursor' => $nextCursor,
            'total'       => $total,       // 👈 добавили
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
            $item->load('order');
            /** @var \App\Models\Order $order */
            $order = $item->order;

            // 🟡 зафиксируем «до»
            $oldCost    = $item->getOriginal('cost_cents');
            $oldStatusI = $item->getOriginal('status');

            $clientStatus = $data['status'] ?? null;
            $forcedInProgress = false;

            // 🔒 статус руками менять нельзя, если заказ pending или refund
            if (
                array_key_exists('status', $data) &&
                $clientStatus !== null &&
                $clientStatus !== $item->status &&
                in_array($order->status, [
                    \App\Models\Order::STATUS_PENDING,
                    \App\Models\Order::STATUS_REFUND,
                ], true)
            ) {
                throw ValidationException::withMessages([
                    'status' => ['Order is pending/refund; manual status changes are not allowed.'],
                ]);
            }

            // если заказ REFUND — принудительно очищаем себестоимость и игнорируем входящий cost
            if ($order->status === \App\Models\Order::STATUS_REFUND) {       // 🔒
                if ($item->cost_cents !== null) {
                    $item->cost_cents = null;
                    $item->recalcProfit();
                }
            } else {
                // можно редактировать cost, НО автоподнятие в in_progress только если заказ уже оплачен/выше
                $orderPaidish = in_array($order->status, [
                    \App\Models\Order::STATUS_PAID,
                    \App\Models\Order::STATUS_IN_PROGRESS,
                    \App\Models\Order::STATUS_COMPLETED,
                ], true);

                if (array_key_exists('cost_price', $data)) {
                    $item->cost_cents = $data['cost_price'] !== null ? (int) round($data['cost_price'] * 100) : null;
                    $item->recalcProfit();

                    $baseStatus = $clientStatus ?? $item->status;
                    if (
                        $item->cost_cents !== null &&
                        in_array($baseStatus, ['paid', 'in_progress'], true) &&
                        $orderPaidish
                    ) {
                        $item->status = \App\Models\OrderItem::STATUS_IN_PROGRESS;
                        $forcedInProgress = true;
                    }
                }
            }

            // ручной статус (если не залочено выше)
            if (array_key_exists('status', $data) && $clientStatus !== null) {
                if ($forcedInProgress && in_array($clientStatus, ['pending', 'paid'], true)) {
                    // игнор отката
                } else {
                    $item->status = $clientStatus;
                }
            }

            if (array_key_exists('link_screen', $data)) {
                $item->link_screen = $data['link_screen'] ?: null;
            }

            $item->save();

            // 🟢 аудит изменений item
            $logger = app(\App\Services\OrderAuditLogger::class);
            $actor  = Auth::user();

            if ($oldCost !== $item->cost_cents) {
                $logger->costUpdated($item, $oldCost, $item->cost_cents, $actor, 'workflow inline edit');
            }
            if ($oldStatusI !== $item->status) {
                $logger->statusChangedOnItem($item, $oldStatusI, $item->status, $actor);
            }

            $order->recalcTotals();
            $order->syncStatusFromItems();

                       

            $refreshed = $this->mapItem($item->fresh(['order.user', 'order.promoCode', 'product.optionGroups', 'options']));
            return response()->json(['item' => $refreshed]);
        });
    }

    /* ===================== helpers ===================== */

    private function fetchItems(): array
    {
        $items = \App\Models\OrderItem::query()
            ->with(['order.user', 'order.promoCode', 'product.optionGroups', 'options'])
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
        $subtotalCents = (int) ($order->subtotal_cents ?? 0);
        $orderDiscountCents = (int) ($order->promo_discount_cents ?? 0);

        $lineCents = (int) ($i->line_total_cents ?? 0);
        $allocDiscount = 0;
        if (
            $orderDiscountCents > 0 &&
            $subtotalCents > 0 &&
            $i->status !== OrderItem::STATUS_REFUND
        ) {

            $allocDiscount = intdiv($lineCents * $orderDiscountCents, $subtotalCents);
        }

        $netLineCents = max(0, $lineCents - $allocDiscount);

        $dt = $order?->paid_at ?? $order?->placed_at ?? $order?->created_at;

        $hasQtySlider = (bool) $i->product?->optionGroups
            ?->contains('type', \App\Models\OptionGroup::TYPE_SLIDER);

        return [
            'id'             => $i->id,
            'order_id'       => $i->order_id,
            'customer_email' => $user?->email,
            'chatnickname'   => $user?->full_name ?? $user?->name ?? null,
            'character'      => $nickname,
            'item_text'      => $this->buildItemText($i),

            'qty'            => (int) $i->qty,                                   // ⬅️
            'unit_price'     => round((int)($i->unit_price_cents ?? 0) / 100, 2), // ⬅️
            'has_qty_slider' => $hasQtySlider,                                   // ⬅️

            'cost_price'     => $i->cost_cents !== null ? round($i->cost_cents / 100, 2) : null,
            'sale_price_gross' => round($lineCents / 100, 2),
            'sale_price'       => round($netLineCents / 100, 2),       // нетто с учётом скидки
            'discount'         => round($allocDiscount / 100, 2),
            'profit_net'       => $i->cost_cents !== null
                ? round(($netLineCents - (int) $i->cost_cents) / 100, 2)
                : null,

            // инфо по заказу:
            'order_discount'   => round($orderDiscountCents / 100, 2),
            'promo_code'       => $order?->promoCode?->code,
            'margin_percent' => $i->margin_bp !== null ? round($i->margin_bp / 100, 2) : null,
            'status'         => $i->status,
            'order_status'   => $order?->status,

            'date'           => $dt ? $dt->format('H:i:s') . '<br>' . $dt->format('d.m.Y') : null,
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


    private function buildItemText(\App\Models\OrderItem $i): string
    {
        $product = $i->product;
        $name = $i->product_name ?: ($product?->name ?? 'Item');

        // Собираем карту value_id -> метаданные группы
        $valueToGroup = [];
        $statsGroupCodes = ['affix', 'unique_d4_stats']; // характеристики (3 шт. для legendary / до 4 для unique d4)
        $classCode = 'class';
        $slotCode  = 'slot';
        $gaCode    = 'ga';

        if ($product && $product->optionGroups) {
            foreach ($product->optionGroups as $g) {
                foreach (($g->values ?? []) as $v) {
                    $valueToGroup[(int)$v->id] = [
                        'code'  => (string)($g->code ?? ''),
                        'title' => (string)($g->title ?? ''),
                    ];
                }
            }
        }

        // Разносим опции по "корзинам"
        $classLine = null;
        $slotLine  = null;
        $statLines = [];     // характеристики (affix / unique_d4_stats)
        $otherLines = [];    // на всякий случай (range и прочее)
        $gaCount = 0;

        foreach ($i->options as $o) {
            // range (double range / qty etc.)
            if (!is_null($o->option_group_id) && is_null($o->option_value_id)) {
                // подпишем диапазон, если есть
                $range = null;
                if (!empty($o->selected_min) || !empty($o->selected_max)) {
                    $range = ' [' . (int)$o->selected_min . ' - ' . (int)$o->selected_max . ']';
                } elseif (is_array($o->payload_json ?? null)) {
                    $p = $o->payload_json;
                    if (isset($p['min']) || isset($p['max'])) {
                        $range = ' [' . ($p['min'] ?? '') . ' - ' . ($p['max'] ?? '') . ']';
                    }
                }
                $otherLines[] = e($o->title . ($range ?? ''));
                continue;
            }

            // value-опции
            if (!is_null($o->option_value_id)) {
                $vid = (int)$o->option_value_id;
                $gMeta = $valueToGroup[$vid] ?? ['code' => '', 'title' => ''];
                $gCode = $gMeta['code'];

                // пропускаем саму GA-группу (dropdown уровня GA)
                if ($gCode === $gaCode) {
                    continue;
                }

                // Class
                if ($gCode === $classCode) {
                    $classLine = e($o->title);
                    continue;
                }

                // Slot
                if ($gCode === $slotCode) {
                    $slotLine = e($o->title);
                    continue;
                }

                // Характеристики (affix / unique_d4_stats)
                if (in_array($gCode, $statsGroupCodes, true)) {
                    $isGa = (bool)($o->is_ga ?? false);
                    if ($isGa) $gaCount++;

                    // Мягкий бейдж GA перед названием
                    $gaBadge = $isGa
                        ? '<span style="display:inline-block;font-size:10px;line-height:1;margin-right:4px;padding:2px 4px;border-radius:3px;background:#fef3c7;color:#92400e;font-weight:600;">GA</span>'
                        : '';

                    $statLines[] = $gaBadge . e($o->title);
                    continue;
                }

                // Остальное — в "прочее"
                $otherLines[] = e($o->title);
            }
        }

        // Заголовок с "(N GA)" если нужно
        $titleHtml = e($name) . ($gaCount > 0 ? ' (' . $gaCount . ' GA)' : '');

        // Сборка линий в нужном порядке: Class, Slot, затем пустая строка, затем характеристики
        $lines = [];
        if ($classLine) $lines[] = $classLine;
        if ($slotLine)  $lines[] = $slotLine;

        if (!empty($statLines)) {
            // 🔽 вместо пустой строки — разделитель
            $lines[] = '<span style="display:inline-block;color:#9ca3af;font-size:11px;">────────────</span>';
            foreach ($statLines as $ln) $lines[] = $ln;
        }

        // (Опционально) добавить 'прочее' ниже характеристик
        if (!empty($otherLines)) {
            $lines[] = '';
            foreach ($otherLines as $ln) $lines[] = $ln;
        }

        // Рендер: первая строка — заголовок, дальше — маркированные пункты (· ...)
        $body = '';
        foreach ($lines as $ln) {
            if ($ln === '') {
                $body .= '<br>';
                continue;
            }

            // Разделитель — СВОЯ строка без завершающего <br>
            if (str_starts_with($ln, '<span')) {
                $body .= '<br>' . $ln;
                continue;
            }

            // Обычная строка (и GA, и не-GA) — всегда с маркером и переносом
            $body .= '<br>· ' . $ln;
        }

        return $titleHtml . $body;
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

                // 🟡 «до»
                $oldCost    = $item->getOriginal('cost_cents');
                $oldStatusI = $item->getOriginal('status');

                $clientStatus = $row['status'] ?? null;
                $forcedInProgress = false;

                // 🔒 запрет смены статусов для pending/refund (только если реально меняется)
                if (
                    array_key_exists('status', $row) &&
                    $clientStatus !== null &&
                    $clientStatus !== $item->status &&
                    in_array($item->order?->status, [
                        \App\Models\Order::STATUS_PENDING,
                        \App\Models\Order::STATUS_REFUND,
                    ], true)
                ) {
                    throw ValidationException::withMessages([
                        "items.{$row['id']}.status" => [
                            "Order #{$item->order_id} is pending/refund; manual status change for item #{$item->id} is not allowed."
                        ],
                    ]);
                }

                // REFUND: жёстко чистим cost, игнорируем входящий
                if ($item->order?->status === \App\Models\Order::STATUS_REFUND) {   // 🔒
                    if ($item->cost_cents !== null) {
                        $item->cost_cents = null;
                        $item->recalcProfit();
                    }
                } else {
                    // обычная логика cost + автоподнятие только после оплаты/выше
                    $orderPaidish = in_array($item->order->status, [
                        \App\Models\Order::STATUS_PAID,
                        \App\Models\Order::STATUS_IN_PROGRESS,
                        \App\Models\Order::STATUS_COMPLETED,
                    ], true);

                    if (array_key_exists('cost_price', $row)) {
                        $item->cost_cents = $row['cost_price'] !== null ? (int) round($row['cost_price'] * 100) : null;
                        $item->recalcProfit();

                        $baseStatus = $clientStatus ?? $item->status;
                        if (
                            $item->cost_cents !== null &&
                            in_array($baseStatus, ['paid', 'in_progress'], true) &&
                            $orderPaidish
                        ) {
                            $item->status = OrderItem::STATUS_IN_PROGRESS;
                            $forcedInProgress = true;
                        }
                    }
                }

                if (array_key_exists('status', $row) && $clientStatus !== null) {
                    if ($forcedInProgress && in_array($clientStatus, ['pending', 'paid'], true)) {
                        // игнор отката
                    } else {
                        $item->status = $clientStatus;
                    }
                }

                if (array_key_exists('link_screen', $row)) {
                    $item->link_screen = $row['link_screen'] ?: null;
                }

                $item->save();

                // 🟢 аудит
                $logger = app(\App\Services\OrderAuditLogger::class);
                $actor  = Auth::user();

                if ($oldCost !== $item->cost_cents) {
                    $logger->costUpdated($item, $oldCost, $item->cost_cents, $actor, 'workflow bulk edit');
                }
                if ($oldStatusI !== $item->status) {
                    $logger->statusChangedOnItem($item, $oldStatusI, $item->status, $actor);
                }

                $orderIds[$item->order_id] = true;
                $result[] = $item->fresh(['order.user', 'product', 'options', 'product.optionGroups']);
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
            $ids = array_values(array_unique(array_keys($orderIds)));

            Log::info('Bulk updated orders', ['count' => count($ids), 'ids' => $ids]);

            
        });

        return response()->json(['items' => $updated]);
    }
}
