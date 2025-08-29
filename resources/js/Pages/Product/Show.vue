<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import type { Game, Category, Product } from '@/types'
import axios from 'axios'
import { useCartSummary } from '@/composables/useCartSummary'
import Breadcrumbs from '@/Components/Breadcrumbs.vue'

const { loadSummary } = useCartSummary()

const props = defineProps<{
  game: Game
  category: Category
  product: Product & {
    option_groups?: Array<
      | {
          id: number
          title: string
          type: 'radio_additive'
          is_required: boolean
          values: Array<{ id: number; title: string; price_delta_cents: number; is_default?: boolean }>
        }
      | {
          id: number
          title: string
          type: 'checkbox_additive'
          is_required: boolean
          values: Array<{ id: number; title: string; price_delta_cents: number }>
        }
      | {
          id: number
          title: string
          type: 'quantity_slider'
          is_required: boolean
          qty_min: number | null
          qty_max: number | null
          qty_step: number | null
          qty_default: number | null
        }
    >
  }
}>()
const { game, category, product } = props

function formatPrice(cents: number) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100)
}
const qtyGroup = computed(() =>
  (product.option_groups ?? []).find((g): g is Extract<typeof g, { type: 'quantity_slider' }> => g.type === 'quantity_slider') ?? null
)

const qty = ref<number>(1)

function snapQty(raw: number) {
  const min = qtyGroup.value?.qty_min ?? 1
  const max = qtyGroup.value?.qty_max ?? 1
  const step = qtyGroup.value?.qty_step ?? 1
  const clamped = Math.min(max, Math.max(min, raw))
  // снап к шагу от min
  const offset = (clamped - min) % step
  return clamped - offset
}

onMounted(() => {
  if (qtyGroup.value) {
    const def = qtyGroup.value.qty_default ?? (qtyGroup.value.qty_min ?? 1)
    qty.value = snapQty(def)
  } else {
    qty.value = 1
  }
})


watch(qty, (v) => {
  if (!qtyGroup.value) return
  const snapped = snapQty(Number(v) || 1)
  if (snapped !== v) qty.value = snapped
})


// локальное состояние выбора
// для radio: selectedByGroup[groupId] = valueId | null
// для checkbox: selectedMulti[groupId] = Set<valueId>
const selectedByGroup = ref<Record<number, number | null>>({})
const selectedMulti   = ref<Record<number, Set<number>>>({})

;(product.option_groups ?? []).forEach((g) => {
  if (g.type === 'radio_additive') {
    const def = g.values.find(v => v.is_default) ?? g.values[0]
    selectedByGroup.value[g.id] = def ? def.id : null
  } else if (g.type === 'checkbox_additive') {
    selectedMulti.value[g.id] = new Set<number>()
  }
})

const optionsPerUnitCents = computed(() => {
  let sum = 0
  ;(product.option_groups ?? []).forEach((g: any) => {
    if (g.type === 'radio_additive') {
      const vid = selectedByGroup.value[g.id]
      if (vid != null) {
        const v = g.values.find((x: any) => x.id === vid)
        if (v && g.multiply_by_qty) sum += v.price_delta_cents
      }
    } else if (g.type === 'checkbox_additive') {
      selectedMulti.value[g.id]?.forEach(vid => {
        const v = g.values.find((x: any) => x.id === vid)
        if (v && g.multiply_by_qty) sum += v.price_delta_cents
      })
    }
  })
  return sum
})

const optionsPerOrderCents = computed(() => {
  let sum = 0
  ;(product.option_groups ?? []).forEach((g: any) => {
    if (g.type === 'radio_additive') {
      const vid = selectedByGroup.value[g.id]
      if (vid != null) {
        const v = g.values.find((x: any) => x.id === vid)
        if (v && !g.multiply_by_qty) sum += v.price_delta_cents
      }
    } else if (g.type === 'checkbox_additive') {
      selectedMulti.value[g.id]?.forEach(vid => {
        const v = g.values.find((x: any) => x.id === vid)
        if (v && !g.multiply_by_qty) sum += v.price_delta_cents
      })
    }
  })
  return sum
})

const unitCents = computed(() => {
  // “юнитная” цена: base + только те опции, что идут “за юнит”
  return product.price_cents + optionsPerUnitCents.value
})

const totalCents = computed(() => {
  const q = qtyGroup.value ? (qty.value || 1) : 1
  return unitCents.value * q + optionsPerOrderCents.value
})



// пример отправки в корзину (payload: выбранные value_ids)
async function addToCart() {
  const chosen: number[] = []
  ;(product.option_groups ?? []).forEach((g) => {
    if (g.type === 'radio_additive') {
      const vid = selectedByGroup.value[g.id]
      if (vid != null) chosen.push(vid)
    } else if (g.type === 'checkbox_additive') {
      selectedMulti.value[g.id]?.forEach(id => chosen.push(id))
    }
  })

  await axios.post('/cart/add', {
    product_id: product.id,
    option_value_ids: chosen,
    qty: qtyGroup.value ? qty.value : 1,
  })

  await loadSummary()
}
</script>

<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">

      <Breadcrumbs :game="game" :category="category" :product="product" />
      
      <!-- твои хлебные крошки и картинка как было -->
      <div class="grid md:grid-cols-2 gap-6 my-6">
        <div>
          <img v-if="product.image_url" :src="product.image_url" class="w-full rounded-xl border border-border " />
          <div v-else class="aspect-video rounded-xl border grid place-items-center text-sm text-muted-foreground">No
            image</div>
        </div>
        
        <div>
          <h1 class="text-3xl font-semibold">{{ product.name }}</h1>

        <div class="mt-2 text-2xl font-bold">
          Base: <span class="">{{ formatPrice(unitCents) }}</span>
          <template v-if="qtyGroup">
            → Total: <span class="text-primary">{{ formatPrice(totalCents) }}</span>
          </template>
        </div>

          <!-- БЛОК ОПЦИЙ -->
          <div v-if="product.option_groups?.length" class="mt-4 space-y-6">
            <div v-for="group in product.option_groups" :key="group.id" class="border border-border rounded-lg p-3">
              <div class="font-medium mb-2">
                {{ group.title }}
                <span v-if="group.is_required" class="text-xs text-muted-foreground">(required)</span>
              </div>

              <!-- RADIO -->
              <div v-if="group.type === 'radio_additive'" class="space-y-2">
                <label v-for="v in group.values" :key="v.id" class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" :name="'g-' + group.id" :value="v.id"
                    :checked="selectedByGroup[group.id] === v.id" @change="selectedByGroup[group.id] = v.id" />
                  <span>{{ v.title }}</span>
                  <span class="ml-auto text-sm text-muted-foreground">
                    {{ v.price_delta_cents >= 0 ? '+' : '' }}{{ formatPrice(v.price_delta_cents) }}
                  </span>
                </label>
              </div>

              <!-- CHECKBOX -->
              <div v-else-if="group.type === 'checkbox_additive'" class="space-y-2">
                <label v-for="v in group.values" :key="v.id" class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" :checked="selectedMulti[group.id]?.has(v.id)" @change="(e: any) => {
                    const set = selectedMulti[group.id];
                    if (!set) return;
                    if (e.target.checked) set.add(v.id); else set.delete(v.id);
                  }" />
                  <span>{{ v.title }}</span>
                  <span class="ml-auto text-sm text-muted-foreground">
                    {{ v.price_delta_cents >= 0 ? '+' : '' }}{{ formatPrice(v.price_delta_cents) }}
                  </span>
                </label>
              </div>

              <div v-else-if="group.type === 'quantity_slider'" class="space-y-3">
                <div class="flex items-center justify-between">
                  <div class="font-medium">Quantity</div>
                  <div class="text-sm text-muted-foreground">
                    {{ qty }} (min: {{ group.qty_min ?? 1 }}, max: {{ group.qty_max ?? 1 }}, step: {{ group.qty_step ??
                    1 }})
                  </div>
                </div>

                <input type="range" class="w-full" :min="group.qty_min ?? 1" :max="group.qty_max ?? 1"
                  :step="group.qty_step ?? 1" v-model.number="qty" />

                <div class="mt-2 flex items-center gap-2">
                  <input type="number" class="w-24 bg-background border rounded px-2 py-1" :min="group.qty_min ?? 1"
                    :max="group.qty_max ?? 1" :step="group.qty_step ?? 1" v-model.number="qty" />
                  <button class="px-2 py-1 border rounded"
                    @click="qty = Math.max(group.qty_min ?? 1, qty - (group.qty_step ?? 1))">-</button>
                  <button class="px-2 py-1 border rounded"
                    @click="qty = Math.min(group.qty_max ?? 1, qty + (group.qty_step ?? 1))">+</button>
                </div>
              </div>


            </div>
          </div>

          <button class="mt-5 px-4 py-2 rounded-lg bg-primary text-primary-foreground" @click="addToCart">
            Add to cart
          </button>

          <div v-if="product.short" class="mt-6 text-base">{{ product.short }}</div>
          <div v-if="product.description" class="prose prose-invert max-w-none mt-4" v-html="product.description" />
        </div>
      </div>
    </section>
  </DefaultLayout>
</template>