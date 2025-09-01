<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import { useCartSummary } from '@/composables/useCartSummary'
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb'
import { Link } from '@inertiajs/vue3'

const { summary, loadSummary } = useCartSummary()

type CartItem = {
    id: number
    product: {
        id: number
        name: string
        image_url?: string | null
    }
    qty: number
    unit_price_cents: number
    line_total_cents: number
    range_labels?: string[]
    options?: ItemOption[]
    has_qty_slider?: boolean
}

type ItemOption = {
    id: number
    title: string
    calc_mode: 'absolute' | 'percent'
    scope: 'unit' | 'total'
    value_cents?: number | null
    value_percent?: number | null
}

const props = defineProps<{
    items: CartItem[]
    total_qty: number
    total_sum_cents: number
}>()

const items = ref<CartItem[]>(props.items)
const totalQty = ref(props.total_qty)
const totalSum = ref(props.total_sum_cents)

function formatPrice(cents: number) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100)
}

function recalc() {
    totalQty.value = items.value.reduce((sum, i) => sum + i.qty, 0)
    totalSum.value = items.value.reduce((sum, i) => sum + i.line_total_cents, 0)
}

async function updateQty(item: CartItem, newQty: number) {
    if (newQty < 1) return
    await axios.post('/cart/update', { item_id: item.id, qty: newQty })
    item.qty = newQty
    item.line_total_cents = item.unit_price_cents * newQty
    recalc()
    await loadSummary()
}

async function removeItem(item: CartItem) {
    try {
        const { data } = await axios.post('/cart/remove', { item_id: item.id })

        items.value = items.value.filter(i => i.id !== item.id)
        recalc()

        if (data && data.summary) {
            summary.value = data.summary
        } else {
            await loadSummary()
        }
    } catch (e) {
        console.error('removeItem failed', e)
        await loadSummary()
    }
}
</script>

<template>
    <DefaultLayout>
        <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <BreadcrumbLink :href="route('home')">Home</BreadcrumbLink>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage>Cart</BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
            <h1 class="text-3xl font-semibold my-6">Your Cart</h1>

            <div v-if="items.length" class="space-y-4">
                <div v-for="item in items" :key="item.id" class="flex items-center gap-4 border rounded-lg p-4">
                    <img v-if="item.product.image_url" :src="item.product.image_url" alt=""
                        class="w-20 h-20 object-cover rounded" />
                    <div class="flex-1">
                        <div class="font-medium">{{ item.product.name }}</div>
                        <div v-if="item.options && item.options.length" class="text-sm text-muted-foreground">
                            <ul class="list-disc pl-5 space-y-0.5">
                                <li v-for="opt in item.options" :key="opt.id">
                                    <span class="font-medium">{{ opt.title }}</span>
                                    <span class="ml-1">
                                        (
                                        <template v-if="opt.calc_mode === 'percent'">
                                            +{{ opt.value_percent ?? 0 }}% {{ opt.scope }}
                                        </template>
                                        <template v-else>
                                            {{ (opt.value_cents ?? 0) >= 0 ? '+' : '' }}{{ formatPrice(opt.value_cents
                                                ?? 0) }} {{ opt.scope }}
                                        </template>
                                        )
                                    </span>
                                </li>
                            </ul>
                        </div>
                        <div v-if="item.range_labels && item.range_labels.length" class="text-sm text-muted-foreground">
                            {{ item.range_labels.join(', ') }}
                        </div>
                        <div v-if="item.has_qty_slider" class="text-sm text-muted-foreground">
                            {{ formatPrice(item.unit_price_cents) }} / each
                        </div>

                        <div v-if="item.has_qty_slider" class="flex items-center gap-2 mt-2">

                            <span>Quantity: {{ item.qty }}</span>

                        </div>
                    </div>

                    <div class="text-right">
                        <div class="font-semibold">{{ formatPrice(item.line_total_cents) }}</div>
                        <button class="text-sm text-red-500 mt-1" @click="removeItem(item)">Remove</button>
                    </div>
                </div>

                <template v-if="$page.props.auth?.user">
                    <div class="flex justify-end">
                        <Link :href="route('checkout.index')"
                            class="mt-3 px-4 py-2 bg-primary text-primary-foreground  rounded-lg">
                        Checkout
                        </Link>
                    </div>

                </template>
                <template v-else>
                    <div class="flex justify-end">
                        <Link :href="route('login')"
                            class="mt-3 inline-block px-4 py-2 bg-primary text-primary-foreground rounded-lg">
                        Login to checkout
                        </Link>
                    </div>
                </template>
            </div>
            <div v-else class="text-center text-muted-foreground">
                Your cart is empty.
            </div>


        </section>
    </DefaultLayout>
</template>