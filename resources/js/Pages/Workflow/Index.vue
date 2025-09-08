<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import { onMounted, onBeforeUnmount, ref, computed, watch } from 'vue'
import axios from 'axios'
import { toast } from 'vue-sonner'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/Components/ui/select'
import { Popover, PopoverTrigger, PopoverContent } from '@/Components/ui/popover'
import { Command, CommandInput, CommandList, CommandEmpty, CommandGroup, CommandItem } from '@/Components/ui/command'

type Row = {
    id: number
    order_id: number
    customer_email?: string | null
    chatnickname?: string | null
    character?: string | null
    item_text: string
    cost_price: number | null
    sale_price: number
    profit: number | null
    margin_percent: number | null
    status: 'pending' | 'paid' | 'in_progress' | 'completed' | 'refund'
    date: string | null
    delivery_time: string | null
    link_screen: string | null
    order_status?: 'pending' | 'paid' | 'in_progress' | 'completed' | 'refund' | string
     unit_price?: number;            
  has_qty_slider?: boolean;
}

const notifiedNewOrders = ref<Set<number>>(new Set())           // чтобы не спамить "New order"
const lastNickByOrder = ref<Record<number, string | null>>({})

function summarizeOrders(items: Row[]) {
  const map: Record<number, { pending: boolean; nick: string | null }> = {}
  for (const it of items) {
    const oid = it.order_id
    if (!map[oid]) map[oid] = { pending: false, nick: it.character ?? null }
    if ((it.order_status ?? it.status) === 'pending') map[oid].pending = true // ⬅️
    if (map[oid].nick == null && it.character) map[oid].nick = it.character
  }
  return map
}


const props = defineProps<{ items: Row[] }>()

/* ========= state ========= */
const rows = ref<Row[]>([...props.items])
const nextCursor = ref<{ order_id: number; id: number } | null>(null)
const isLoading = ref(false)
let fetchCtrl: AbortController | null = null
/* ========= helpers ========= */
function emailColorClass(status: Row['status']) {
    switch (status) {
        case 'paid':
        case 'in_progress': return 'text-yellow-600'
        case 'completed': return 'text-green-600'
        case 'refund': return 'text-red-600'
        default: return ''
    }
}

function markDirty(r: Row) { (r as any)._dirty = true }
const headerCountLabel = computed(() => {
    const loaded = rows.value.length
    const total = totalCount.value ?? loaded
    return total > loaded ? `${loaded} (${total})` : `${total}`
})

const totalCount = ref<number | null>(null)


async function saveRow(r?: Row) {
  const dirty = rows.value.filter(x => (x as any)._dirty || (r && x.id === r.id))
  if (!dirty.length) return

  const payload = {
    items: dirty.map(x => ({
      id: x.id,
      cost_price: x.cost_price ?? null,
      status: x.status,               // можно слать всегда
      link_screen: x.link_screen ?? null,
    })),
  }

  try {
    const { data } = await axios.patch(route('workflow.items.bulk'), payload)
    const map = new Map<number, Row>((data.items as Row[]).map(i => [i.id, i]))
    const updatedIds = (data.items as Row[]).map(i => i.id)
    if (updatedIds.length) flash(updatedIds, 'green')
    rows.value = rows.value.map(x => map.get(x.id) ?? x)
    rows.value.forEach(x => delete (x as any)._dirty)
    toast.success('Saved')
  } catch (e: any) {
    const res = e?.response
    if (res?.status === 422) {
      const errors = res.data?.errors || {}
      const flat = Object.values(errors).flat() as string[]
      const msg = flat[0] || res.data?.message || 'Validation error'
      toast.error('Save failed', { description: msg })
    } else if (e.name !== 'CanceledError' && e.code !== 'ERR_CANCELED') {
      toast.error('Save failed', { description: e?.message || 'Unknown error' })
    }
  }
}

/* ========= filters ========= */
type Status = 'pending' | 'paid' | 'in_progress' | 'completed' | 'refund'
const STATUS_LIST = ['pending', 'paid', 'in_progress', 'completed', 'refund'] as const

const flashMap = ref<Record<number, 'green' | 'amber' | undefined>>({})

function flash(ids: number | number[], tone: 'green' | 'amber' = 'green', ms = 3000) {
    const arr = Array.isArray(ids) ? ids : [ids]
    // выставляем метки
    flashMap.value = { ...flashMap.value, ...Object.fromEntries(arr.map(id => [id, tone])) }
    // снимаем через ms
    setTimeout(() => {
        const next = { ...flashMap.value }
        arr.forEach(id => delete next[id])
        flashMap.value = next
    }, ms)
}


/* ===== применённые фильтры (то, что реально используется при запросах) ===== */
const appliedStatuses = ref<Status[]>([...STATUS_LIST])
const appliedSearch = ref('')

/* ===== UI-черновик (то, что меняем в дропдауне/инпуте до Apply) ===== */
const open = ref(false)
const uiStatusChecked = ref<Record<Status, boolean>>({
    pending: true, paid: true, in_progress: true, completed: true, refund: true,
})
const uiSearchRaw = ref('')

function toggleStatus(s: Status) {
    uiStatusChecked.value = { ...uiStatusChecked.value, [s]: !uiStatusChecked.value[s] }
}

function setAll(v: boolean) {
    uiStatusChecked.value = STATUS_LIST.reduce((acc, s) => { acc[s] = v; return acc }, {} as Record<Status, boolean>)
}

watch(open, (v) => {
    // при открытии дропдауна синхронизируем черновик с применёнными значениями
    if (v) {
        uiStatusChecked.value = STATUS_LIST.reduce((acc, s) => {
            acc[s] = appliedStatuses.value.includes(s)
            return acc
        }, {} as Record<Status, boolean>)
    }
})

/* ===== кнопки Apply / Reset ===== */
function applyFilters() {
    appliedStatuses.value = STATUS_LIST.filter(s => uiStatusChecked.value[s])
    appliedSearch.value = uiSearchRaw.value.trim()
    localStorage.setItem('wf.filters', JSON.stringify({
        statuses: appliedStatuses.value,
        q: appliedSearch.value,
    }))
    nextCursor.value = null
    fetchPage(true)
    open.value = false
}

function resetFiltersToAll() {
    setAll(true)
    uiSearchRaw.value = ''
}

/* ===== подпись на кнопке статусов — по ПРИМЕНЁННЫМ значениям ===== */
const buttonLabel = computed(() => {
    const sel = appliedStatuses.value
    if (sel.length === STATUS_LIST.length) return 'Statuses: All'
    if (sel.length === 0) return 'Statuses: None'
    if (sel.length === 1) return `Status: ${sel[0].replace('_', ' ')}`
    return `Statuses: ${sel.length}`
})

/* ========= search (debounced → сервер) ========= */
const searchRaw = ref('')
const search = ref('')
let searchTimer: any = null
watch(searchRaw, (v) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => { search.value = v }, 1000)
})
onBeforeUnmount(() => clearTimeout(searchTimer))

/* ========= fetch (cursor) ========= */
async function fetchPage(reset = false, opts?: { notify?: boolean }) {
    if (isLoading.value) return
    isLoading.value = true

    const notify = !!opts?.notify
    const hadSnapshot = Object.keys(lastNickByOrder.value).length > 0
    const prevSummary = reset ? summarizeOrders(rows.value) : null

    try {
        // отменяем предыдущий
        fetchCtrl?.abort()
        fetchCtrl = new AbortController()

        const params: any = { statuses: appliedStatuses.value, q: appliedSearch.value, limit: 50 }
        if (!reset && nextCursor.value) {
            params['cursor[order_id]'] = nextCursor.value.order_id
            params['cursor[id]'] = nextCursor.value.id
        }

        const { data } = await axios.get(route('workflow.list'), {
            params,
            signal: fetchCtrl.signal,
        })

        const newItems: Row[] = data.items ?? []
        rows.value = reset ? newItems : [...rows.value, ...newItems]
        nextCursor.value = data.next_cursor ?? null
        if (typeof data.total === 'number') totalCount.value = data.total

        // --- уведомления (только при reset) ---
        if (reset) {
            const curSummary = summarizeOrders(rows.value)

            if (notify && prevSummary) {
                // 1) новый заказ: order_id появился и у него pending
                const prevIds = new Set(Object.keys(prevSummary).map(Number))
                for (const [oidStr, cur] of Object.entries(curSummary)) {
                    const oid = Number(oidStr)
                    if (!prevIds.has(oid) && cur.pending && !notifiedNewOrders.value.has(oid)) {
                        toast.success(`#${oid} New order`)
                        notifiedNewOrders.value.add(oid)
                    }
                }

                // 2) новый/изменённый ник
                for (const [oidStr, cur] of Object.entries(curSummary)) {
                    const oid = Number(oidStr)
                    const prevNick = lastNickByOrder.value[oid] // undefined — не было слепка
                    const curNick = cur.nick ?? null
                    if (prevNick !== undefined && (prevNick ?? '') !== (curNick ?? '')) {
                        toast.warning(`#${oid} New nickname`)
                    }
                    lastNickByOrder.value[oid] = curNick
                }
            } else {
                // первая инициализация слепка (без уведомлений)
                for (const [oidStr, cur] of Object.entries(curSummary)) {
                    lastNickByOrder.value[Number(oidStr)] = cur.nick ?? null
                }
            }
        }
    } catch (e: any) {
        if (e.name !== 'CanceledError' && e.code !== 'ERR_CANCELED') console.error(e)
    } finally {
        isLoading.value = false
    }
}

// первый запрос
onMounted(() => {
    const st = localStorage.getItem('wf.filters')
    if (st) {
        const parsed = JSON.parse(st)
        appliedStatuses.value = parsed.statuses ?? appliedStatuses.value
        appliedSearch.value = parsed.q ?? ''
        uiSearchRaw.value = appliedSearch.value
    }
    // первый запрос — только создаём слепок, без уведомлений
    fetchPage(true, { notify: false })
})



/* ========= realtime ========= */
let ordersChannel: any = null
onMounted(() => {
    const EchoAny = (window as any).Echo
    if (EchoAny?.private) {
        ordersChannel = EchoAny.private('orders')
            .subscribed(() => console.log('[orders] subscribed ✅'))
            .error((e: any) => console.error('[orders] subscription error ❌', e))
            .listen('.workflow.updated', (_e: any) => {
                // перезагружаем верх и показываем уведомления
                fetchPage(true, { notify: true })
            })
    }
})
onBeforeUnmount(() => { try { (window as any).Echo?.leave('orders') } catch { } })
</script>

<style scoped>
/* Вход/перестановка строк (без leave, чтобы не "сплющивало") */
.row-enter-from {
    opacity: 0;
    transform: translateY(-4px);
}

.row-enter-active {
    transition: opacity .16s ease, transform .16s ease;
}

.row-enter-to {
    opacity: 1;
    transform: translateY(0);
}

.row-move {
    transition: transform .16s ease;
}

/* Мерцание "bg-green-700/10 + border-green-700" через фон и inset box-shadow */
@keyframes flash-green {
    0% {
        background-color: rgba(21, 128, 61, .10);
        box-shadow: inset 0 0 0 1px rgba(21, 128, 61, 1);
    }

    100% {
        background-color: transparent;
        box-shadow: inset 0 0 0 1px rgba(21, 128, 61, 0);
    }
}

.flash-green {
    animation: flash-green 1.2s ease-out;
}

/* На всякий, если захочешь янтарный (amber-700/10 + border-amber-700) */
@keyframes flash-amber {
    0% {
        background-color: rgba(180, 83, 9, .10);
        box-shadow: inset 0 0 0 1px rgba(180, 83, 9, 1);
    }

    100% {
        background-color: transparent;
        box-shadow: inset 0 0 0 1px rgba(180, 83, 9, 0);
    }
}

.flash-amber {
    animation: flash-amber 1.2s ease-out;
}

/* Уважение reduced-motion */
@media (prefers-reduced-motion: reduce) {

    .row-enter-active,
    .row-move,
    .flash-green,
    .flash-amber {
        transition: none !important;
        animation: none !important;
    }
}
</style>



<template>
    <DefaultLayout>
        <div class="px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <h1 class="text-2xl font-semibold">
                    Workflow
                    <span class="ml-2 text-sm text-muted-foreground align-middle">
                        {{ headerCountLabel }}
                    </span>
                </h1>

                <div class="flex items-center gap-2">
                    <!-- поиск -->
                    <Input v-model="uiSearchRaw" class="w-64" type="search" autocomplete="off"
                        placeholder="Search (#id, email, nickname, item…)" @keydown.enter.prevent="applyFilters" />

                    <Button size="sm" class="h-8" :disabled="isLoading" @click="applyFilters">Apply</Button>

                    <!-- мульти-статусы -->
                    <Popover v-model:open="open">
                        <PopoverTrigger as-child>
                            <Button variant="outline" size="sm">{{ buttonLabel }}</Button>
                        </PopoverTrigger>

                        <PopoverContent class="w-64 p-0">
                            <Command>
                                <CommandInput placeholder="Filter status..." />
                                <CommandList>
                                    <CommandEmpty>No results</CommandEmpty>
                                    <CommandGroup heading="Statuses">
                                        <CommandItem v-for="s in STATUS_LIST" :key="s" :value="s"
                                            @select="(ev: any) => { ev?.preventDefault?.(); toggleStatus(s) }"
                                            class="flex items-center gap-2 cursor-pointer">
                                            <span class="w-2 h-2 rounded-full"
                                                :class="uiStatusChecked[s] ? 'bg-primary' : 'bg-muted-foreground/30'"></span>
                                            <span class="capitalize select-none">{{ s.replace('_', ' ') }}</span>
                                        </CommandItem>
                                    </CommandGroup>
                                </CommandList>

                                <div class="flex items-center justify-between gap-2 p-2 border-t">
                                    <div class="flex gap-2">
                                        <Button size="xs" variant="ghost" @click.stop="setAll(true)">Select all</Button>
                                        <Button size="xs" variant="ghost" @click.stop="setAll(false)">Clear</Button>
                                    </div>
                                    <!-- Применить из дропдауна -->
                                    <Button size="xs" @click.stop="applyFilters">Apply</Button>
                                </div>
                            </Command>
                        </PopoverContent>
                    </Popover>
                </div>
            </div>

            <Table class="w-full text-sm">
                <TableHeader class="sticky top-0 z-10 bg-background backdrop-blur">
                    <TableRow>
                        <TableHead class="w-[72px] py-2">#</TableHead>
                        <TableHead class="min-w-[220px] py-2">Customer</TableHead>
                        <TableHead class="min-w-[380px] py-2">Item</TableHead>
                        <TableHead class="w-[100px] py-2">Cost</TableHead>
                        <TableHead class="w-[84px] py-2">Sale</TableHead>
                        <TableHead class="w-[90px] py-2">Profit</TableHead>
                        <TableHead class="w-[90px] py-2">Margin %</TableHead>
                        <TableHead class="w-[150px] py-2">Status</TableHead>
                        <TableHead class="w-[110px] py-2">Date</TableHead>
                        <TableHead class="w-[120px] py-2">Delivery</TableHead>
                        <TableHead class="w-[140px] py-2">Link</TableHead>
                        <TableHead class="w-[90px] text-right py-2">Action</TableHead>
                    </TableRow>
                </TableHeader>

                <TransitionGroup tag="tbody" name="row" class="" appear>
                    <TableRow v-for="r in rows" :key="r.id" :class="[
                        (r as any)._dirty ? 'bg-muted/50' : '',
                        flashMap[r.id] ? ('flash-' + flashMap[r.id]) : '',
                        'border-b'
                    ]">
                        <!-- # -->
                        <TableCell class="font-medium whitespace-nowrap">#{{ r.order_id }}</TableCell>

                        <!-- Customer -->
                        <TableCell class="whitespace-nowrap">
                            <div class="font-medium" :class="emailColorClass(r.status)">{{ r.customer_email }}</div>
                            <div v-if="r.chatnickname" class="text-xs text-muted-foreground">{{ r.chatnickname }}</div>
                            <div v-if="r.character" class="text-xs text-muted-foreground">{{ r.character }}</div>
                        </TableCell>

                        <!-- Item -->
                        <TableCell class="leading-5">
  <div :class="emailColorClass(r.status)" v-html="r.item_text"></div>
  <div v-if="r.has_qty_slider" class="text-xs text-muted-foreground mt-0.5">
    Qty: {{ r.qty }}
    <template v-if="r.unit_price !== undefined">
      · {{ r.unit_price.toFixed(2) }} / each
    </template>
  </div>
</TableCell>

                        <!-- Cost -->
                        <TableCell>
                            <Input :model-value="r.cost_price ?? ''"
                                :disabled="r.order_status === 'refund'"
                                @update:modelValue="(val) => {
                                    const s = String(val).replace(',', '.').trim();
                                    r.cost_price = s === '' ? null : Number(s);
                                    markDirty(r)
                                }" @keydown.enter.prevent="saveRow(r)" />
                        </TableCell>

                        <!-- Sale/Profit/Margin -->
                        <TableCell class="whitespace-nowrap">{{ r.sale_price.toFixed(2) }}</TableCell>
                        <TableCell class="whitespace-nowrap">{{ r.profit === null ? '—' : r.profit.toFixed(2) }}
                        </TableCell>
                        <TableCell class="whitespace-nowrap">{{ r.margin_percent === null ? '—' :
                            r.margin_percent.toFixed(2) }}</TableCell>

                        <!-- Status -->
                        <TableCell>
                            <Select v-model="r.status"
                                :disabled="r.order_status === 'pending' || r.order_status === 'refund'"
                                @update:modelValue="() => markDirty(r)" @keydown.enter.prevent="saveRow(r)">
                                <SelectTrigger class="h-8 w-[150px]">
                                    <SelectValue placeholder="status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pending">pending</SelectItem>
                                    <SelectItem value="paid">paid</SelectItem>
                                    <SelectItem value="in_progress">in_progress</SelectItem>
                                    <SelectItem value="completed">completed</SelectItem>
                                    <SelectItem value="refund">refund</SelectItem>
                                </SelectContent>
                            </Select>
                        </TableCell>

                        <!-- Date -->
                        <TableCell class="whitespace-nowrap">
                            <div v-if="r.date" v-html="r.date"></div>
                            <span v-else>—</span>
                        </TableCell>

                        <!-- Delivery -->
                        <TableCell class="whitespace-nowrap">{{ r.delivery_time ?? '—' }}</TableCell>

                        <!-- Link -->
                        <TableCell>
                            <Input v-model="r.link_screen" @update:modelValue="() => markDirty(r)" type="url"
                                class="w-[120px] h-8 text-xs" :title="r.link_screen ?? ''" placeholder="https://…"
                                @keydown.enter.prevent="saveRow(r)" />
                        </TableCell>

                        <!-- Save -->
                        <TableCell class="text-right">
                            <Button variant="outline" size="sm" class="h-8" @click="saveRow(r)">Save</Button>
                        </TableCell>
                    </TableRow>
                </TransitionGroup>
            </Table>

            <!-- bottom controls -->
            <div class="flex justify-center py-4">
                <Button v-if="nextCursor" :disabled="isLoading" variant="outline" @click="fetchPage()">
                    {{ isLoading ? 'Loading…' : 'Load more' }}
                </Button>
                <div v-else class="text-xs text-muted-foreground">No more items</div>
            </div>
        </div>
    </DefaultLayout>
</template>