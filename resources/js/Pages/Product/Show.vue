<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import type { Game, Category, Product } from '@/types'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { useCartSummary } from '@/composables/useCartSummary'

const { summary, loadSummary } = useCartSummary()

const props = defineProps<{ game: Game; category: Category; product: Product & {
  option_groups?: Array<{
    id:number; title:string; type:'radio_additive'|'checkbox_additive'; is_required:boolean;
    values: Array<{ id:number; title:string; price_delta_cents:number }>
  }>
} }>()

const { game, category, product } = props

function formatPrice(cents:number) {
  return new Intl.NumberFormat('en-US', { style:'currency', currency:'USD' }).format(cents / 100)
}

// локальное состояние выбора
// для radio: selectedByGroup[groupId] = valueId | null
// для checkbox: selectedMulti[groupId] = Set<valueId>
const selectedByGroup = ref<Record<number, number | null>>({})
const selectedMulti   = ref<Record<number, Set<number>>>({})

product.option_groups?.forEach(g => {
  if (g.type === 'radio_additive') {
    // ищем дефолтный
    const def = g.values.find(v => v.is_default)
    selectedByGroup.value[g.id] = def ? def.id : (g.values[0]?.id ?? null)
  } else {
    selectedMulti.value[g.id] = new Set<number>()
  }
})

const totalCents = computed(() => {
  let sum = product.price_cents
  product.option_groups?.forEach(g => {
    if (g.type === 'radio_additive') {
      const vid = selectedByGroup.value[g.id]
      if (vid != null) {
        const v = g.values.find(x => x.id === vid)
        if (v) sum += v.price_delta_cents
      }
    } else {
      const set = selectedMulti.value[g.id]
      set?.forEach(vid => {
        const v = g.values.find(x => x.id === vid)
        if (v) sum += v.price_delta_cents
      })
    }
  })
  return sum
})

// пример отправки в корзину (payload: выбранные value_ids)
async function addToCart() {
  const chosen:number[] = []
  product.option_groups?.forEach(g => {
    if (g.type === 'radio_additive') {
      const vid = selectedByGroup.value[g.id]
      if (vid != null) chosen.push(vid)
    } else {
      selectedMulti.value[g.id]?.forEach(id => chosen.push(id))
    }
  })

  await axios.post('/cart/add', {
    product_id: product.id,
    option_value_ids: chosen,
    qty: 1,
  })

  
  await loadSummary()
}

</script>

<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">
      <!-- твои хлебные крошки и картинка как было -->
      <div class="grid md:grid-cols-2 gap-6 my-6">
        <div>
          <img v-if="product.image" :src="product.image" class="w-full rounded-xl border border-border " />
          <div v-else class="aspect-video rounded-xl border grid place-items-center text-sm text-muted-foreground">No image</div>
        </div>

        <div>
          <h1 class="text-3xl font-semibold">{{ product.name }}</h1>

          <div class="mt-2 text-2xl font-bold">
            Base: {{ formatPrice(product.price_cents) }} →
            Total: <span class="text-primary">{{ formatPrice(totalCents) }}</span>
          </div>

          <!-- БЛОК ОПЦИЙ -->
          <div v-if="product.option_groups?.length" class="mt-4 space-y-6">
            <div v-for="group in product.option_groups" :key="group.id" class="border rounded-lg p-3">
              <div class="font-medium mb-2">
                {{ group.title }}
                <span v-if="group.is_required" class="text-xs text-muted-foreground">(required)</span>
              </div>

              <!-- RADIO -->
              <div v-if="group.type === 'radio_additive'" class="space-y-2">
                <label v-for="v in group.values" :key="v.id" class="flex items-center gap-2 cursor-pointer">
                  <input type="radio"
                         :name="'g-'+group.id"
                         :value="v.id"
                         :checked="selectedByGroup[group.id] === v.id"
                         @change="selectedByGroup[group.id] = v.id" />
                  <span>{{ v.title }}</span>
                  <span class="ml-auto text-sm text-muted-foreground">
                    {{ v.price_delta_cents >= 0 ? '+' : '' }}{{ formatPrice(v.price_delta_cents) }}
                  </span>
                </label>
              </div>

              <!-- CHECKBOX -->
              <div v-else class="space-y-2">
                <label v-for="v in group.values" :key="v.id" class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox"
                         :checked="selectedMulti[group.id]?.has(v.id)"
                         @change="(e:any) => {
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
            </div>
          </div>

          <button class="mt-5 px-4 py-2 rounded-lg bg-primary text-primary-foreground"
                  @click="addToCart">
            Add to cart
          </button>

          <div v-if="product.short" class="mt-6 text-base">{{ product.short }}</div>
          <div v-if="product.description" class="prose prose-invert max-w-none mt-4" v-html="product.description" />
        </div>
      </div>
    </section>
  </DefaultLayout>
</template>