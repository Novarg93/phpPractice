<script setup lang="ts">
import DefaultLayout from "@/Layouts/DefaultLayout.vue";
import { loadStripe } from "@stripe/stripe-js";
import GameNicknameForm from "@/Components/GameNicknameForm.vue";
import axios from "axios";
import { ref } from "vue";
import Input from "@/Components/ui/input/Input.vue";
import Button from "@/Components/ui/button/Button.vue";

const isLoading = ref(false);
const nickFormRef = ref<any>(null);

// ТЕСТ УДАЛИТЬ
const isMakingPending = ref(false);

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
    // сервер может уже прислать discount_cents
    discount_cents?: number;
  };
  // сервер может прислать текущий применённый промо
  promo?: { code: string; discount_cents?: number } | null;
  nickname?: string | null;
}>();

/** ----- STATE: promo & totals (единый источник истины) ----- */

const promoCodeInput = ref("");
const promoApplying = ref(false);
const promoMessage = ref<string | null>(null);
const promoOk = ref<boolean>(false);

// промо, если есть от сервера
const promo = ref<{ code: string } | null>((props as any).promo ?? null);

// нормализуем totals: если сервер не дал discount_cents или total_cents «сырой» — досчитаем
function normalizeTotals(t: any) {
  const d = Number.isFinite(t?.discount_cents) ? Number(t.discount_cents) : 0;
  const subtotal = Number(t?.subtotal_cents ?? 0);
  const shipping = Number(t?.shipping_cents ?? 0);
  const tax = Number(t?.tax_cents ?? 0);
  // если total_cents уже пришёл — не пересчитываем, иначе считаем
  const total =
    Number.isFinite(t?.total_cents)
      ? Number(t.total_cents)
      : Math.max(0, subtotal + shipping + tax - d);

  return {
    subtotal_cents: subtotal,
    shipping_cents: shipping,
    tax_cents: tax,
    discount_cents: d,
    total_cents: total,
    currency: String(t?.currency ?? props.totals.currency ?? "USD"),
  };
}

const totals = ref(normalizeTotals(props.totals));
function setTotals(t: any) {
  totals.value = normalizeTotals(t);
}

function formatPrice(cents: number) {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: totals.value.currency ?? props.totals.currency,
  }).format((cents || 0) / 100);
}

function showOptPrice(opt: ItemOption) {
  return opt.calc_mode === "percent"
    ? (opt.value_percent ?? 0) !== 0
    : (opt.value_cents ?? 0) !== 0;
}

/** ----- TEST: create pending draft ----- */
async function makePendingDraft() {
  if (isMakingPending.value) return;
  isMakingPending.value = true;
  try {
    if (nickFormRef.value?.submit) {
      await nickFormRef.value.submit();
    }
    const { data } = await axios.post("/checkout/test-pending");
    window.location.href = data?.redirect ?? route("orders.show", data.order_id);
  } catch (e) {
    console.error("Create pending draft failed", e);
    isMakingPending.value = false;
  }
}

/** ----- PROMO ----- */
async function applyPromo() {
  if (!promoCodeInput.value.trim()) return;
  promoApplying.value = true;
  promoMessage.value = null;

  try {
    const { data } = await axios.post("/checkout/promo/apply", {
      code: promoCodeInput.value,
    });
    if (data.ok) {
      promo.value = data.promo;     // { code, discount_cents? }
      setTotals(data.totals);       // сервер уже вернёт discount_cents и total_cents
      promoOk.value = true;
      promoMessage.value = `Applied ${data.promo.code}`;
    } else {
      promoOk.value = false;
      promoMessage.value = data.message ?? "Cannot apply code";
    }
  } catch (e: any) {
    promoOk.value = false;
    promoMessage.value = e?.response?.data?.message ?? "Cannot apply code";
  } finally {
    promoApplying.value = false;
  }
}

async function removePromo() {
  promoApplying.value = true;
  promoMessage.value = null;
  try {
    const { data } = await axios.post("/checkout/promo/remove");
    promo.value = null;
    setTotals(data.totals);
    promoOk.value = true;
    promoMessage.value = "Removed";
  } catch (e) {
    promoOk.value = false;
    promoMessage.value = "Cannot remove";
  } finally {
    promoApplying.value = false;
  }
}

/** ----- Stripe ----- */
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
    const { error } = await stripe.redirectToCheckout({ sessionId: data.id });
    if (error) throw error;
  } catch (e) {
    console.error("Stripe redirect failed", e);
    isLoading.value = false;
  }
}
</script>

<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">
      <h1 class="text-3xl font-semibold mb-6">Checkout</h1>

      <div class="grid lg:grid-cols-3 gap-6">
        <!-- Left: Items -->
        <div class="lg:col-span-2 space-y-4">
          <div class="border rounded-lg p-4">
            <h2 class="font-semibold mb-3">Items</h2>

            <div
              v-for="i in props.items"
              :key="i.id"
              class="flex gap-4 border rounded-md p-3 mb-2"
            >
              <img
                v-if="i.product.image_url"
                :src="i.product.image_url"
                class="w-16 h-16 object-cover rounded"
              />
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
                      <span
                        v-if="opt.is_ga"
                        class="text-[10px] mr-2 px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-200"
                      >
                        GA
                      </span>
                      <span class="font-medium">
                        {{ opt.title }}
                      </span>

                      <span v-if="showOptPrice(opt)" class="ml-1">
                        (
                        <template v-if="opt.calc_mode === 'percent'">
                          +{{ opt.value_percent ?? 0 }}% {{ opt.scope }}
                        </template>
                        <template v-else>
                          {{ (opt.value_cents ?? 0) >= 0 ? '+' : '' }}
                          {{ formatPrice(opt.value_cents ?? 0) }} {{ opt.scope }}
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

        <!-- Right: Summary -->
        <div class="lg:col-span-1">
          <GameNicknameForm
            ref="nickFormRef"
            :initial-nickname="props.nickname ?? ''"
            :required="false"
            save-url="/checkout/nickname"
            label="Character nickname for delivery"
            class="mb-4"
          />

          <div class="border rounded-lg p-4 sticky top-6">
            <h2 class="font-semibold mb-3">Summary</h2>

            <ul class="space-y-2 text-sm">
              <li class="flex justify-between">
                <span>Subtotal</span>
                <span>{{ formatPrice(totals.subtotal_cents) }}</span>
              </li>
              <li class="flex justify-between">
                <span>Shipping</span>
                <span>{{ formatPrice(totals.shipping_cents) }}</span>
              </li>
              <li class="flex justify-between">
                <span>Tax</span>
                <span>{{ formatPrice(totals.tax_cents) }}</span>
              </li>

              <li
                v-if="(totals.discount_cents ?? 0) > 0"
                class="flex justify-between text-green-700"
              >
                <span>Discount<span v-if="promo?.code"> ({{ promo.code }})</span></span>
                <span>-{{ formatPrice(totals.discount_cents) }}</span>
              </li>
            </ul>

            <!-- Promo form -->
            <div class="mt-3">
              <label class="block text-sm font-medium mb-1">Promo code</label>
              <div class="flex gap-2">
                <Input
                  v-model="promoCodeInput"
                  type="text"
                  placeholder="ENTER CODE"
                  class="flex-1 border rounded-lg px-3 py-2"
                />
                <Button class="px-3 py-2 border rounded-lg" @click="applyPromo" :disabled="promoApplying">
                  Apply
                </Button>
                <Button
                  v-if="promo?.code"
                  class="px-3 py-2 border rounded-lg"
                  @click="removePromo"
                  :disabled="promoApplying"
                >
                  Remove
                </Button>
              </div>
              <div v-if="promoMessage" class="text-xs mt-1" :class="promoOk ? 'text-green-600' : 'text-red-600'">
                {{ promoMessage }}
              </div>
            </div>

            <!-- ЕДИНСТВЕННЫЙ Total -->
            <div class="mt-3 border-t pt-3 flex justify-between font-semibold">
              <span>Total</span>
              <span>{{ formatPrice(totals.total_cents) }}</span>
            </div>

            <button
              v-if="!isLoading"
              class="w-full mt-4 px-4 py-2 bg-primary text-primary-foreground rounded-lg"
              @click="goToStripe"
            >
              Pay with Stripe
            </button>

            <button
              v-else
              disabled
              aria-busy="true"
              class="w-full mt-4 px-4 py-2 rounded-lg bg-muted text-muted-foreground flex items-center justify-center gap-2"
            >
              <span
                class="inline-block h-4 w-4 rounded-full border-2 border-current border-t-transparent animate-spin"
              ></span>
              Redirecting…
            </button>

            <button v-if="!isMakingPending" class="w-full mt-2 px-4 py-2 border rounded-lg" @click="makePendingDraft">
              Create test order (pending)
            </button>
            <button v-else disabled class="w-full mt-2 px-4 py-2 rounded-lg bg-muted text-muted-foreground">
              Creating…
            </button>

            <div class="mt-3 text-xs text-muted-foreground">
              Use test card: <code>4242 4242 4242 4242</code>, any future date, any CVC, any ZIP.
            </div>
          </div>
        </div>
      </div>
    </section>
  </DefaultLayout>
</template>