<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import GameNicknameForm from '@/Components/GameNicknameForm.vue'
import OrderStatusBadge from '@/Components/OrderStatusBadge.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
  order: {
    id: number; status: 'pending' | 'paid' | 'in_progress' | 'completed' | 'refund' | string
    placed_at: string | null; total_cents: number; currency: string
    nickname?: string | null; needs_nickname: boolean; shipping_address: any
    items: Array<{
      product_name: string; qty: number; unit_price_cents: number; line_total_cents: number
      image_url?: string; options?: Array<{ id: number; title: string; calc_mode: 'absolute' | 'percent'; scope: 'unit' | 'total'; value_cents?: number | null; value_percent?: number | null; is_ga?: boolean;  }>
      ranges?: Array<{ title: string; label: string }>; has_qty_slider?: boolean
    }>
    
  }
}>()

function formatPrice(cents: number) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: props.order.currency }).format(cents / 100)
}

function retryPay(orderId: number) {
  router.post(route('orders.pay', orderId))
}

function showOptPrice(opt: { calc_mode: 'absolute' | 'percent'; value_cents?: number | null; value_percent?: number | null }) {
  return opt.calc_mode === 'percent'
    ? (opt.value_percent ?? 0) !== 0
    : (opt.value_cents ?? 0) !== 0
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

                <!-- value options -->
                <div v-if="it.options?.length" class="text-sm text-muted-foreground">
                  <ul class="list-disc pl-5 space-y-0.5">
                    <li v-for="opt in it.options" :key="opt.id">
                      <span v-if="opt.is_ga"
                        class="text-[10px] mr-2 px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-200">
                        GA
                      </span>
                      <span class="font-medium">{{ opt.title }}</span>
                      
                      <span v-if="showOptPrice(opt)" class="ml-1">
    (
    <template v-if="opt.calc_mode === 'percent'">
      +{{ opt.value_percent ?? 0 }}% {{ opt.scope }}
    </template>
    <template v-else>
      {{ (opt.value_cents ?? 0) >= 0 ? '+' : '' }}{{ formatPrice(opt.value_cents ?? 0) }} {{ opt.scope }}
    </template>
    )
  </span>
                    </li>
                  </ul>
                </div>

                <!-- range options -->
                <div v-if="it.ranges?.length" class="text-sm text-muted-foreground">
                  <div v-for="range in it.ranges" :key="range.label">
                    {{ range.title }}: {{ range.label }}
                  </div>
                </div>

                <template v-if="it.has_qty_slider">
                  Qty: {{ it.qty }} · {{ formatPrice(it.unit_price_cents) }}/each
                </template>
              </div>
            </div>
            <div class="font-semibold">{{ formatPrice(it.line_total_cents) }}</div>
          </div>
        </div>

        <!-- summary -->
        <div class="lg:col-span-1 space-y-4">
          <div class="border border-border rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div class="text-sm text-muted-foreground">{{ order.placed_at || '—' }}</div>
              <OrderStatusBadge :status="order.status" />
            </div>
            <div class="mt-2 text-lg font-semibold">Total: {{ formatPrice(order.total_cents) }}</div>
            <div v-if="order.status === 'pending'" class="mt-4">
              <button type="button" class="px-4 py-2 rounded-md bg-primary text-primary-foreground hover:opacity-90"
                @click="retryPay(order.id)">
                Оплатить сейчас
              </button>
              <p class="text-xs text-muted-foreground mt-1">
                Если платёж не прошёл, вы можете повторить оплату.
              </p>
            </div>
          </div>


          <GameNicknameForm v-if="order.needs_nickname" :initial-nickname="props.order.nickname ?? ''" :required="true"
            :save-url="route('orders.nickname', props.order.id)" label="Character name for delivery" help="No spaces" />
        </div>
      </div>
    </section>
  </DefaultLayout>
</template>