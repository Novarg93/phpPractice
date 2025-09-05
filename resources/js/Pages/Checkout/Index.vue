<script setup lang="ts">
import DefaultLayout from "@/Layouts/DefaultLayout.vue";
import { loadStripe } from "@stripe/stripe-js";
import GameNicknameForm from "@/Components/GameNicknameForm.vue";
import axios from "axios";
import { useCartSummary } from "@/composables/useCartSummary";
import { ref } from "vue";

const isLoading = ref(false);
const nickFormRef = ref<any>(null);

//ТЕСТ УДАЛИТЬ
const isMakingPending = ref(false)


type ItemOption = {
    id: number;
    title: string;
    calc_mode: "absolute" | "percent";
    scope: "unit" | "total";
    value_cents?: number | null;
    value_percent?: number | null;
    is_ga?: boolean;
};

type Item = {
    id: number;
    product: { id: number; name: string; image_url?: string | null };
    qty: number;
    unit_price_cents: number;
    line_total_cents: number;
    options?: ItemOption[];
    range_labels?: string[];
    has_qty_slider?: boolean;
};

const props = defineProps<{
    stripePk: string;
    items: Item[];
    totals: {
        subtotal_cents: number;
        shipping_cents: number;
        tax_cents: number;
        total_cents: number;
        currency: string;
    };
    nickname?: string | null;
}>();

const { loadSummary } = useCartSummary();

function formatPrice(cents: number) {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: props.totals.currency,
    }).format(cents / 100);
}

function showOptPrice(opt: ItemOption) {
    return opt.calc_mode === 'percent'
        ? (opt.value_percent ?? 0) !== 0
        : (opt.value_cents ?? 0) !== 0
}


//ТЕСТ УДАЛИТЬ
async function makePendingDraft() {
    if (isMakingPending.value) return
    isMakingPending.value = true
    try {
        if (nickFormRef.value?.submit) {
            await nickFormRef.value.submit()
        }
        const { data } = await axios.post('/checkout/test-pending')
        // уйдём сразу на страницу заказа
        window.location.href = data?.redirect ?? route('orders.show', data.order_id)
    } catch (e) {
        console.error('Create pending draft failed', e)
        isMakingPending.value = false
    }
}



async function goToStripe() {
    if (isLoading.value) return;
    isLoading.value = true;
    try {
        if (nickFormRef.value?.submit) {
            await nickFormRef.value.submit();
        }
        const { data } = await axios.post("/checkout/session");
        const stripe = await loadStripe(props.stripePk);
        if (!stripe) throw new Error("Stripe failed to load");
        const { error } = await stripe.redirectToCheckout({
            sessionId: data.id,
        });
        if (error) throw error;
        // если редирект не случился — дойдём до catch и вернём кнопку
    } catch (e) {
        console.error("Stripe redirect failed", e);
        isLoading.value = false; // 👈 вернём кнопку при ошибке
    }
}
</script>

<template>
    <DefaultLayout>
        <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">
            <h1 class="text-3xl font-semibold mb-6">Checkout</h1>

            <div class="grid lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-4">
                    <div class="border rounded-lg p-4">
                        <h2 class="font-semibold mb-3">Items</h2>

                        <div v-for="i in props.items" :key="i.id" class="flex gap-4 border rounded-md p-3 mb-2">
                            <img v-if="i.product.image_url" :src="i.product.image_url"
                                class="w-16 h-16 object-cover rounded" />
                            <div class="flex-1">
                                <div class="font-medium">
                                    {{ i.product.name }}
                                </div>
                                <div v-if="i.range_labels?.length" class="text-xs text-muted-foreground">
                                    {{ i.range_labels.join(", ") }}
                                </div>
                                <div v-if="i.has_qty_slider" class="text-xs text-muted-foreground">
                                    Qty: {{ i.qty }}
                                </div>
                                <div v-if="i.options?.length" class="mt-1 text-xs text-muted-foreground">
                                    <ul class="list-disc pl-5 space-y-0.5">
                                        <li v-for="opt in i.options" :key="opt.id">
                                            <span v-if="opt.is_ga"
                                                class="text-[10px] mr-2 px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-200">
                                                GA
                                            </span>
                                            <span class="font-medium">{{
                                                opt.title
                                                }}</span>

                                            <span v-if="showOptPrice(opt)" class="ml-1">
                                                (
                                                <template v-if="opt.calc_mode === 'percent'">
                                                    +{{ opt.value_percent ?? 0 }}% {{ opt.scope }}
                                                </template>
                                                <template v-else>
                                                    {{ (opt.value_cents ?? 0) >= 0 ? '+' : '' }}{{
                                                    formatPrice(opt.value_cents ?? 0) }} {{ opt.scope }}
                                                </template>
                                                )
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="text-right">
                                <div v-if="i.has_qty_slider" class="text-sm">
                                    {{ formatPrice(i.unit_price_cents) }} / each
                                </div>
                                <div class="font-semibold">
                                    {{ formatPrice(i.line_total_cents) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <GameNicknameForm ref="nickFormRef" :initial-nickname="props.nickname ?? ''" :required="false"
                        save-url="/checkout/nickname" label="Character nickname for delivery" class="mb-4" />
                    <div class="border rounded-lg p-4 sticky top-6">
                        <h2 class="font-semibold mb-3">Summary</h2>
                        <ul class="space-y-2 text-sm">
                            <li class="flex justify-between">
                                <span>Subtotal</span><span>{{
                                    formatPrice(totals.subtotal_cents)
                                    }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span>Shipping</span><span>{{
                                    formatPrice(totals.shipping_cents)
                                    }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span>Tax</span><span>{{
                                    formatPrice(totals.tax_cents)
                                    }}</span>
                            </li>
                        </ul>
                        <div class="mt-3 border-t pt-3 flex justify-between font-semibold">
                            <span>Total</span><span>{{ formatPrice(totals.total_cents) }}</span>
                        </div>

                        <button v-if="!isLoading"
                            class="w-full mt-4 px-4 py-2 bg-primary text-primary-foreground rounded-lg"
                            @click="goToStripe">
                            Pay with Stripe
                        </button>

                        <button v-else disabled aria-busy="true"
                            class="w-full mt-4 px-4 py-2 rounded-lg bg-muted text-muted-foreground flex items-center justify-center gap-2">
                            <span
                                class="inline-block h-4 w-4 rounded-full border-2 border-current border-t-transparent animate-spin"></span>
                            Redirecting…
                        </button>

                        <button v-if="!isMakingPending" class="w-full mt-2 px-4 py-2 border rounded-lg"
                            @click="makePendingDraft">
                            Create test order (pending)
                        </button>
                        <button v-else disabled class="w-full mt-2 px-4 py-2 rounded-lg bg-muted text-muted-foreground">
                            Creating…
                        </button>

                        <div class="mt-3 text-xs text-muted-foreground">
                            Use test card: <code>4242 4242 4242 4242</code>, any
                            future date, any CVC, any ZIP.
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </DefaultLayout>
</template>
