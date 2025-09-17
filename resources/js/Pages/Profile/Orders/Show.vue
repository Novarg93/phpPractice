<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import OrderStatusBadge from '@/Components/OrderStatusBadge.vue'
import axios from 'axios'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from "@/Components/ui/input"

const props = defineProps<{
  order: {
    id: number
    status: 'pending' | 'paid' | 'in_progress' | 'completed' | 'refund' | string
    placed_at: string | null
    total_cents: number
    currency: string
    nickname?: string | null
    needs_nickname: boolean
    shipping_address: any
    items: Array<{
      product_name: string; qty: number; unit_price_cents: number; line_total_cents: number
      image_url?: string; options?: Array<{ id: number; title: string; calc_mode: 'absolute' | 'percent'; scope: 'unit' | 'total'; value_cents?: number | null; value_percent?: number | null; is_ga?: boolean; }>
      ranges?: Array<{ title: string; label: string }>; has_qty_slider?: boolean
    }>
  }
}>()

function formatPrice(cents: number) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: props.order.currency }).format(cents / 100)
}
function showOptPrice(opt: { calc_mode: 'absolute' | 'percent'; value_cents?: number | null; value_percent?: number | null }) {
  return opt.calc_mode === 'percent' ? (opt.value_percent ?? 0) !== 0 : (opt.value_cents ?? 0) !== 0
}
function retryPay(orderId: number) { router.post(route('orders.pay', orderId)) }

// ==== nickname inline edit ====
const isEditingNick = ref(false)
const nickVal = ref(props.order.nickname ?? '')
const isSavingNick = ref(false)
const justSavedNick = ref(false)
const nickError = ref<string | null>(null)

function startEditNick() {
  nickError.value = null
  nickVal.value = props.order.nickname ?? ''
  isEditingNick.value = true
}
function cancelEditNick() {
  isEditingNick.value = false
  nickError.value = null
}
async function saveNick() {
  const v = nickVal.value.trim()
  if (!v) { nickError.value = 'Required'; return }
  if (!/^[A-Za-z0-9_]{2,30}$/.test(v)) { nickError.value = 'Only letters, digits and "_", 2–30 chars.'; return }

  if (isSavingNick.value) return
  isSavingNick.value = true
  nickError.value = null
  try {
    const { data } = await axios.post(route('orders.nickname', props.order.id), { nickname: v })
      // локально обновим отображение
      ; (props.order as any).nickname = data.nickname
      ; (props.order as any).needs_nickname = false
    isEditingNick.value = false
    justSavedNick.value = true
    setTimeout(() => (justSavedNick.value = false), 2000)
  } catch (e: any) {
    // покажем текст ошибки валидации, если пришёл
    const msg = e?.response?.data?.message || 'Failed to save'
    nickError.value = String(msg)
  } finally {
    isSavingNick.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8">
      <div class="flex items-center gap-3 mb-6">
        <h1 class="text-3xl font-semibold">Order #{{ order.id }}</h1>
        <OrderStatusBadge :status="order.status" />
      </div>

      <div class="grid lg:grid-cols-3 gap-6">
        <!-- items -->
        <div class="lg:col-span-2 space-y-3">
          <div v-for="it in order.items" :key="it.product_name"
            class="border border-border rounded-lg p-4 flex justify-between">
            <div class="flex items-center gap-4">
              <img v-if="it.image_url" class="w-16 h-16 object-cover rounded" :src="it.image_url"
                :alt="it.product_name">
              <div>
                <div class="font-medium">{{ it.product_name }}</div>

                <div v-if="it.options?.length" class="text-sm text-muted-foreground">
                  <ul class="list-disc pl-5 space-y-0.5">
                    <li v-for="opt in it.options" :key="opt.id">
                      <span v-if="opt.is_ga"
                        class="text-[10px] mr-2 px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-200">GA</span>
                      <span class="font-medium">{{ opt.title }}</span>
                      <span v-if="showOptPrice(opt)" class="ml-1">
                        (
                        <template v-if="opt.calc_mode === 'percent'">+{{ opt.value_percent ?? 0 }}% {{ opt.scope
                          }}</template>
                        <template v-else>{{ (opt.value_cents ?? 0) >= 0 ? '+' : '' }}{{ formatPrice(opt.value_cents ??
                          0) }} {{ opt.scope }}</template>
                        )
                      </span>
                    </li>
                  </ul>
                </div>

                <div v-if="it.ranges?.length" class="text-sm text-muted-foreground">
                  <div v-for="range in it.ranges" :key="range.label">{{ range.title }}: {{ range.label }}</div>
                </div>

                <template v-if="it.has_qty_slider">
                  Qty: {{ it.qty }} · {{ formatPrice(it.unit_price_cents) }}/each
                </template>
              </div>
            </div>
            <div class="font-semibold">{{ formatPrice(it.line_total_cents) }}</div>
          </div>
        </div>

        <!-- summary + nickname edit -->
        <div class="lg:col-span-1 space-y-4">
          <div class="border border-border rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div class="text-sm text-muted-foreground">{{ order.placed_at || '—' }}</div>
              <OrderStatusBadge :status="order.status" />
            </div>
            <div class="mt-2 text-lg font-semibold">Total: {{ formatPrice(order.total_cents) }}</div>

            <!-- Кнопка повторной оплаты -->
            <div v-if="order.status === 'pending'" class="mt-4 space-y-2">
              <Button class="w-full" @click="retryPay(order.id)">Retry Payment</Button>

              <!-- Подсказка, если e-mail не верифицирован, этот пост всё равно стопнётся на бекенде -->
              <p v-if="$page.props.auth?.user && !$page.props.auth.user.email_verified_at"
                class="text-xs text-amber-600">
                Please verify your email to complete the payment.
              </p>
            </div>
          </div>


          <!-- Nickname card -->
          <div class="border border-border rounded-lg p-4"
            :class="justSavedNick ? 'bg-green-700/10 border-green-700' : ''">
            <div class="flex items-center justify-between mb-2">
              <div class="font-semibold">Character nickname</div>
              <button v-if="!isEditingNick" class="text-xs px-2 py-1 rounded border hover:bg-muted"
                @click="startEditNick">
                {{ order.nickname ? 'Edit' : 'Add' }}
              </button>
            </div>

            <template v-if="!isEditingNick">
              <div class="text-sm">
                <span v-if="order.nickname"><span class="text-muted-foreground">Current:</span> <span
                    class="font-medium">{{ order.nickname }}</span></span>
                <span v-else class="text-amber-700">Not set</span>
              </div>
              <p class="text-xs text-muted-foreground mt-1">Used for in-game delivery.</p>
            </template>

            <template v-else>
              <div class="flex items-center gap-2">
                <Input v-model="nickVal" class="h-9 px-2 rounded  border w-56" placeholder="Nickname (A–Z, 0–9, _)"
                  @keydown.enter.prevent="saveNick" @keydown.esc.prevent="cancelEditNick" autocomplete="off" />
                <Button variant="default" :disabled="isSavingNick" @click="saveNick">
                  {{ isSavingNick ? 'Saving…' : 'Save' }}
                </Button>
                <Button variant="secondary" @click="cancelEditNick">Cancel</Button>
              </div>
              <div v-if="nickError" class="mt-1 text-xs text-red-600">{{ nickError }}</div>
              <div class="mt-1 text-xs text-muted-foreground">Only letters, digits and “_”, 2–30 chars.</div>
            </template>
          </div>
        </div>
      </div>
    </section>
  </DefaultLayout>
</template>