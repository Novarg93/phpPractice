<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import GameNicknameForm from '@/Components/GameNicknameForm.vue'


const props = defineProps<{
  order: {
    id: number
    status: string
    placed_at: string | null
    total_cents: number
    currency: string
    nickname?: string | null
    needs_nickname: boolean
    shipping_address: any
    items: Array<{
      product_name: string
      qty: number
      unit_price_cents: number
      line_total_cents: number
      image_url?: string
      options?: Array<{
        id: number
        title: string
        calc_mode: 'absolute' | 'percent'
        scope: 'unit' | 'total'
        value_cents?: number | null
        value_percent?: number | null
      }>
      ranges?: Array<{ title: string; label: string }>
      has_qty_slider?: boolean
    }>
  }
}>()

function formatPrice(cents: number) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: props.order.currency }).format(cents / 100)
}
</script>

<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8">
      <h1 class="text-3xl font-semibold mb-6">Order #{{ order.id }}</h1>




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
                      <span class="font-medium">{{ opt.title }}</span>
                      <span class="ml-1">
                        (
                        <template v-if="opt.calc_mode === 'percent'">
                          +{{ opt.value_percent ?? 0 }}% {{ opt.scope }}
                        </template>
                        <template v-else>
                          {{ (opt.value_cents ?? 0) >= 0 ? '+' : '' }}{{ formatPrice(opt.value_cents ?? 0) }} {{
                            opt.scope }}
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

                <!-- qty + price -->

                <template v-if="it.has_qty_slider">
                  Qty: {{ it.qty }} · {{ formatPrice(it.unit_price_cents) }}/each
                </template>
              </div>
            </div>
            <div class="font-semibold">{{ formatPrice(it.line_total_cents) }}</div>
          </div>
        </div>

        <!-- summary -->
        <div class="lg:col-span-1">
          <div class="border border-border rounded-lg p-4">
            <div class="text-sm text-muted-foreground">{{ order.placed_at }}</div>
            <div class="mt-2 text-lg font-semibold">Total: {{ formatPrice(order.total_cents) }}</div>
          </div>

          <GameNicknameForm class="mt-4" :initial-nickname="props.order.nickname ?? ''"
            :required=true :save-url="route('orders.nickname', props.order.id)"
            label="Character name for delivery" help="No spaces" />
        </div>
      </div>
    </section>
  </DefaultLayout>
</template>