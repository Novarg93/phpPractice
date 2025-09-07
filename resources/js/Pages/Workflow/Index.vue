<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import { onMounted, onBeforeUnmount, ref } from 'vue'
import axios from 'axios'

import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/Components/ui/select'

type Row = {
    id: number
    order_id: number
    customer_email?: string | null
    chatnickname?: string | null
    character?: string | null
    item_text: string         // HTML с <br> и "· "
    cost_price: number | null // editable
    sale_price: number
    profit: number | null
    margin_percent: number | null
    status: 'pending' | 'paid' | 'in_progress' | 'completed' | 'refund'
    date: string | null
    delivery_time: string | null        // HTML с <br>
    link_screen: string | null // editable
}

const props = defineProps<{ items: Row[] }>()

let ordersChannel: any = null
async function refreshList() {
    const { data } = await axios.get(route('workflow.list'))
    rows.value = data.items
}


function emailColorClass(status: Row['status']) {
    switch (status) {
        case 'paid':
        case 'in_progress':
            return 'text-yellow-600'
        case 'completed':
            return 'text-green-600'
        case 'refund':
            return 'text-red-600'
        case 'pending':
        default:
            return '' // дефолтный цвет как просил
    }
}

const rows = ref<Row[]>([...props.items])

function markDirty(r: Row) {
    ; (r as any)._dirty = true
}

async function saveRow(r?: Row) {
    // собираем все изменённые (или хотя бы ту, которую кликнули)
    const dirty = rows.value.filter(x => (x as any)._dirty || (r && x.id === r.id))
    if (!dirty.length) return

    const payload = {
        items: dirty.map(x => ({
            id: x.id,
            cost_price: x.cost_price ?? null,
            status: x.status,
            link_screen: x.link_screen ?? null,
        }))
    }

    const { data } = await axios.patch(route('workflow.items.bulk'), payload)

    // подменим обновлённые строки
    const map = new Map<number, Row>(data.items.map((i: Row) => [i.id, i]))
    rows.value = rows.value.map(x => map.get(x.id) ?? x)
    // очистим флаг грязности
    rows.value.forEach(x => delete (x as any)._dirty)
}


onMounted(() => {
    const EchoAny = (window as any).Echo
    if (EchoAny?.private) {
        ordersChannel = EchoAny.private('orders')
            .subscribed(() => console.log('[orders] subscribed ✅'))
            .error((e: any) => console.error('[orders] subscription error ❌', e))
            .listen('.workflow.updated', (e: any) => {
                console.log('[orders] event', e)
                refreshList()
            })
    }
})

onBeforeUnmount(() => {
    try {
        (window as any).Echo?.leave('orders') // ← так правильно
    } catch { }
})






</script>

<template>
    <DefaultLayout>
        <div class="px-4 sm:px-6 lg:px-8 py-6">
            <h1 class="text-2xl font-semibold mb-4">Workflow</h1>

            <Table class="w-full text-sm">


                <!-- sticky header -->
                <TableHeader class="sticky top-0 z-10 bg-background  backdrop-blur">
                    <TableRow class="">
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

                <TableBody>
                    <TableRow v-for="r in rows" :key="r.id"
                        :class="[(r as any)._dirty ? 'bg-muted/50' : '', 'border-b']">
                        <!-- # -->
                        <TableCell class="font-medium whitespace-nowrap">
                            #{{ r.order_id }}
                        </TableCell>

                        <!-- Customer (email / chat / character) -->
                        <TableCell class="whitespace-nowrap ">
                            <div class="font-medium" :class="emailColorClass(r.status)">{{ r.customer_email }}</div>
                            <div v-if="r.chatnickname" class="text-xs text-muted-foreground">{{ r.chatnickname }}</div>
                            <div v-if="r.character" class="text-xs text-muted-foreground">{{ r.character }}</div>
                        </TableCell>

                        <!-- Item -->
                        <TableCell class="leading-5">
                            <div :class="emailColorClass(r.status)" v-html="r.item_text"></div>
                        </TableCell>

                        <!-- Cost (compact) -->
                        <TableCell>
                            <Input :model-value="r.cost_price ?? ''"
                                @update:modelValue="(val) => { const s = String(val).replace(',', '.').trim(); r.cost_price = s === '' ? null : Number(s); markDirty(r) }" @keydown.enter.prevent="saveRow(r)" />
                        </TableCell>

                        <!-- Sale -->
                        <TableCell class="whitespace-nowrap">
                            {{ r.sale_price.toFixed(2) }}
                        </TableCell>

                        <!-- Profit -->
                        <TableCell class="whitespace-nowrap">
                            {{ r.profit === null ? '—' : r.profit.toFixed(2) }}
                        </TableCell>

                        <!-- Margin -->
                        <TableCell class="whitespace-nowrap">
                            {{ r.margin_percent === null ? '—' : r.margin_percent.toFixed(2) }}
                        </TableCell>

                        <!-- Status -->
                        <TableCell>
                            <Select v-model="r.status" @update:modelValue="() => markDirty(r)" @keydown.enter.prevent="saveRow(r)">
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

                        <!-- Date (HH:MM:SS<br>DD.MM.YYYY) -->
                        <TableCell class="whitespace-nowrap">
                            <div v-if="r.date" v-html="r.date"></div>
                            <span v-else>—</span>
                        </TableCell>

                        <!-- Delivery -->
                        <TableCell class="whitespace-nowrap">
                            {{ r.delivery_time ?? '—' }}
                        </TableCell>

                        <!-- Link (compact) -->
                        <TableCell>
                            <Input v-model="r.link_screen" @update:modelValue="() => markDirty(r)" type="url"
                                class="w-[120px] h-8 text-xs" :title="r.link_screen ?? ''" placeholder="https://…" @keydown.enter.prevent="saveRow(r)" />
                        </TableCell>

                        <!-- Save -->
                        <TableCell class="text-right">
                            <Button variant="outline" size="sm" class="h-8" @click="saveRow(r)">
                                Save
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </DefaultLayout>
</template>