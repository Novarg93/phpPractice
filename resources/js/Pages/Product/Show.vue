<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import Breadcrumbs from '@/Components/Breadcrumbs.vue'
import axios from 'axios'
import { useCartSummary, summary as cartSummary } from '@/composables/useCartSummary'
import { resolveGroupComponent } from '@/Components/product/groups/registry'
import { useProductOptions } from '@/composables/useProductOptions'
import { usePricing } from '@/composables/usePricing'
import type { Game, Category } from '@/types'
import type { ProductWithGroups } from '@/types/product-options'
import { computed } from 'vue'
import type { Selection } from '@/types/product-options'


const props = defineProps<{
  game: Game
  category: Category
  product: ProductWithGroups
}>()



const { selectionByGroup, qtyGroup, buildAddToCartPayload } = useProductOptions(props.product)
const { unitCents, totalCents } = usePricing(props.product, selectionByGroup)
const { loadSummary } = useCartSummary()

function formatPrice(cents: number) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format((cents || 0) / 100)
}

async function addToCart() {
  try {
    const payload = buildAddToCartPayload()
    const { data } = await axios.post('/cart/add', payload)
    if (data && data.summary) cartSummary.value = data.summary
    else await loadSummary()
  } catch (e) {
    console.error('addToCart failed', e)
    await loadSummary()
  }
}
</script>

<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">
      <Breadcrumbs :game="game" :category="category" :product="product" />

      <div class="grid md:grid-cols-2 gap-6 my-6">
        <div>
          <img v-if="product.image_url" :src="product.image_url" class="w-full rounded-xl border border-border" />
          <div v-else class="aspect-video rounded-xl border grid place-items-center text-sm text-muted-foreground">
            No image
          </div>
        </div>

        <div>
          <h1 class="text-3xl font-semibold">{{ product.name }}</h1>

          <div class="mt-2 text-2xl font-bold">
            Unit: <span>{{ formatPrice(unitCents) }}</span>
            <template v-if="qtyGroup">
              → Total: <span class="text-primary">{{ formatPrice(totalCents) }}</span>
            </template>
          </div>

          <div v-if="product.option_groups?.length" class="mt-4 space-y-6">
            <div v-for="group in product.option_groups" :key="group.id" class="border border-border rounded-lg p-3">
              <component :is="resolveGroupComponent(group.type)" v-if="resolveGroupComponent(group.type)"
                :group="group as any" v-model:selected="(selectionByGroup as any)[group.id]" />
              <div v-else class="text-sm text-muted-foreground">
                Unsupported group type: <code>{{ group.type }}</code>
              </div>
            </div>
          </div>

          <button class="mt-5 px-4 py-2 rounded-lg bg-primary text-primary-foreground" @click.prevent="addToCart">
            Add to cart
          </button>
        </div>
      </div>
    </section>
  </DefaultLayout>
</template>