<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import { useCartSummary } from '@/composables/useCartSummary'

const { summary, loadSummary } = useCartSummary()

type CartItem = {
  id: number
  product: {
    id: number
    name: string
    image?: string | null
  }
  qty: number
  unit_price_cents: number
  line_total_cents: number
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
  await axios.post('/cart/remove', { item_id: item.id })
  items.value = items.value.filter(i => i.id !== item.id)
  recalc()
  await loadSummary() 
}
</script>

<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">
      <h1 class="text-3xl font-semibold mb-6">Your Cart</h1>

      <div v-if="items.length" class="space-y-4">
        <div
          v-for="item in items"
          :key="item.id"
          class="flex items-center gap-4 border rounded-lg p-4"
        >
          <img
            v-if="item.product.image"
            :src="item.product.image"
            alt=""
            class="w-20 h-20 object-cover rounded"
          />
          <div class="flex-1">
            <div class="font-medium">{{ item.product.name }}</div>
            <div class="text-sm text-muted-foreground">
              {{ formatPrice(item.unit_price_cents) }} / each
            </div>

            <div class="flex items-center gap-2 mt-2">
              <button
                class="px-2 py-1 border rounded"
                @click="updateQty(item, item.qty - 1)"
              >-</button>
              <span>{{ item.qty }}</span>
              <button
                class="px-2 py-1 border rounded"
                @click="updateQty(item, item.qty + 1)"
              >+</button>
            </div>
          </div>

          <div class="text-right">
            <div class="font-semibold">{{ formatPrice(item.line_total_cents) }}</div>
            <button
              class="text-sm text-red-500 mt-1"
              @click="removeItem(item)"
            >Remove</button>
          </div>
        </div>

        <div class="text-right mt-6 border-t pt-4">
          <div class="text-lg font-semibold">
            Total ({{ totalQty }}) items: {{ formatPrice(totalSum) }}
          </div>
          <button
            class="mt-3 px-4 py-2 bg-primary text-primary-foreground rounded-lg"
          >
            Checkout
          </button>
        </div>
      </div>

      <div v-else class="text-center text-muted-foreground">
        Your cart is empty.
      </div>
    </section>
  </DefaultLayout>
</template>