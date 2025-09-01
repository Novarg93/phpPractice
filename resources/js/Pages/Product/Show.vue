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
import { ref, computed } from 'vue'

const props = defineProps<{
  game: Game
  category: Category
  product: ProductWithGroups
}>()

const { selectionByGroup, qtyGroup, buildAddToCartPayload } = useProductOptions(props.product)
const { unitCents, totalCents } = usePricing(props.product, selectionByGroup)
const { loadSummary } = useCartSummary()

// --- state for UX ---
const submitting = ref(false)
const errors = ref<string[]>([])

// показывать подсветку required только после попытки добавить
const triedToSubmit = ref(false)

// required-группы, которые не заполнены
const missingRequiredIds = computed<number[]>(() => {
  return (props.product.option_groups ?? [])
    .filter((g: any) => {
      if (g.type !== 'selector' || !g.is_required) return false
      const sel = selectionByGroup.value[g.id]
      if (g.selection_mode === 'single') return typeof sel !== 'number'
      return !Array.isArray(sel) || sel.length === 0
    })
    .map((g: any) => g.id)
})
const missingRequiredSet = computed(() => new Set(missingRequiredIds.value))
const canAddToCart = computed(() => missingRequiredIds.value.length === 0)

function formatPrice(cents: number) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format((cents || 0) / 100)
}

async function addToCart() {
  errors.value = []
  triedToSubmit.value = true   // ← включаем подсветку required

  if (!canAddToCart.value) {
    errors.value.push('Заполните обязательные опции перед добавлением в корзину.')
    return
  }

  if (submitting.value) return  // защита от дабл-клика
  submitting.value = true
  try {
    const payload = buildAddToCartPayload()
    const { data } = await axios.post('/cart/add', payload)
    if (data && data.summary) cartSummary.value = data.summary
    else await loadSummary()
  } catch (e: any) {
    // ... твой парсинг ошибок как было
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">
      <Breadcrumbs :game="game" :category="category" :product="product" />

      <!-- error banner -->
      <div v-if="errors.length" class="mb-4 rounded-md border border-red-300 bg-red-50 text-red-700 p-3">
        <ul class="list-disc pl-5">
          <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
        </ul>
      </div>

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
            <div v-for="group in product.option_groups" :key="group.id" class="border rounded-lg p-3"
              :class="triedToSubmit && missingRequiredSet.has(group.id) ? 'border-red-400' : 'border-border'">
              <component :is="resolveGroupComponent(group.type)" v-if="resolveGroupComponent(group.type)"
                :group="group as any" v-model:selected="(selectionByGroup as any)[group.id]" />
              <div v-else class="text-sm text-muted-foreground">
                Unsupported group type: <code>{{ group.type }}</code>
              </div>

              <p v-if="triedToSubmit && missingRequiredSet.has(group.id)" class="mt-1 text-sm text-red-600">
                Это поле обязательно для выбора
              </p>
            </div>
          </div>

          <button class="mt-5 px-4 py-2 rounded-lg bg-primary text-primary-foreground" @click.prevent="addToCart">
            {{ submitting ? 'Adding…' : 'Add to cart' }}
          </button>
        </div>
      </div>
    </section>
  </DefaultLayout>
</template>