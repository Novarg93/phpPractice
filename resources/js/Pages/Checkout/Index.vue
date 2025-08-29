<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import { loadStripe } from '@stripe/stripe-js'
import axios from 'axios'
import { useCartSummary } from '@/composables/useCartSummary'

type ItemOption = { id:number; title:string; price_delta_cents:number }
type Item = {
  id:number
  product:{ id:number; name:string; image_url?:string|null }
  qty:number
  unit_price_cents:number
  line_total_cents:number
  options?: ItemOption[]
}

const props = defineProps<{
  stripePk: string
  items: Item[]
  totals: { subtotal_cents:number; shipping_cents:number; tax_cents:number; total_cents:number; currency:string }
}>()

const { loadSummary } = useCartSummary()

function formatPrice(cents:number) {
  return new Intl.NumberFormat('en-US', { style:'currency', currency: props.totals.currency }).format(cents / 100)
}

async function goToStripe() {
  // создаём сессию на бэке
  const { data } = await axios.post('/checkout/session')
  // редиректим: вариант 1 — напрямую на URL
  // window.location.href = data.url

  // вариант 2 — через stripe-js (лучше, безопаснее)
  const stripe = await loadStripe(props.stripePk)
  await stripe?.redirectToCheckout({ sessionId: data.id })
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
              <img v-if="i.product.image_url" :src="i.product.image_url" class="w-16 h-16 object-cover rounded" />
              <div class="flex-1">
                <div class="font-medium">{{ i.product.name }}</div>
                <div class="text-xs text-muted-foreground">Qty: {{ i.qty }}</div>
                <div v-if="i.options?.length" class="mt-1 text-xs text-muted-foreground">
                  <div v-for="opt in i.options" :key="opt.id" class="flex justify-between">
                    <span>• {{ opt.title }}</span>
                    <span>{{ opt.price_delta_cents >= 0 ? '+' : '' }}{{ formatPrice(opt.price_delta_cents) }}</span>
                  </div>
                </div>
              </div>
              <div class="text-right">
                <div class="text-sm">{{ formatPrice(i.unit_price_cents) }} / each</div>
                <div class="font-semibold">{{ formatPrice(i.line_total_cents) }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="lg:col-span-1">
          <div class="border rounded-lg p-4 sticky top-6">
            <h2 class="font-semibold mb-3">Summary</h2>
            <ul class="space-y-2 text-sm">
              <li class="flex justify-between"><span>Subtotal</span><span>{{ formatPrice(totals.subtotal_cents) }}</span></li>
              <li class="flex justify-between"><span>Shipping</span><span>{{ formatPrice(totals.shipping_cents) }}</span></li>
              <li class="flex justify-between"><span>Tax</span><span>{{ formatPrice(totals.tax_cents) }}</span></li>
            </ul>
            <div class="mt-3 border-t pt-3 flex justify-between font-semibold">
              <span>Total</span><span>{{ formatPrice(totals.total_cents) }}</span>
            </div>

            <button class="w-full mt-4 px-4 py-2 bg-primary text-primary-foreground rounded-lg" @click="goToStripe">
              Pay with Stripe
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