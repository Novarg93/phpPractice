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
import { ref, computed, onMounted } from 'vue'
import RareItemBuilder from '@/Components/product/RareItemBuilder.vue'
import type { SelectorGroup } from '@/types/product-options'


const props = defineProps<{
  game: Game
  category: Category
  product: ProductWithGroups
}>()

const { selectionByGroup, qtyGroup, buildAddToCartPayload } = useProductOptions(props.product)
const { unitCents, totalCents } = usePricing(props.product, selectionByGroup)
const { loadSummary } = useCartSummary()

const groups = computed(() => (props.product.option_groups ?? []).filter(g => g.type === 'selector') as SelectorGroup[])

function byCodeOrTitle(code: string, rx: RegExp): SelectorGroup | undefined {
  return groups.value.find(g => (g as any).code === code)
    ?? groups.value.find(g => rx.test((g.title || '').toLowerCase()))
}

const gaGroup = computed(() => byCodeOrTitle('ga', /\b(ga|greater)\b/))

function clamp(n: number, min = 0, max = 3) {
  const x = Number(n)
  return Math.min(max, Math.max(min, Number.isFinite(x) ? x : 0))
}

const gaLimit = computed<number>(() => {
  const g = gaGroup.value
  if (!g) return 0
  const raw = (selectionByGroup.value as any)[g.id]
  const selectedId = Array.isArray(raw) ? raw[0] : raw
  const opt = g.values?.find(v => v.id === selectedId)

  // 1) meta.ga_count
  const fromMeta = Number((opt as any)?.meta?.ga_count)
  if (Number.isFinite(fromMeta)) return clamp(fromMeta)

  // 2) из title вида "2GA" / "2 GA" / "GA x2"
  const m = String(opt?.title ?? '').match(/(\d)/)
  return clamp(m ? Number(m[1]) : 0)
})

const classGroup = computed(() => byCodeOrTitle('class', /class|класс/))
const slotGroup = computed(() => byCodeOrTitle('slot', /slot|слот|предмет/))
const affixGroup = computed(() => byCodeOrTitle('affix', /affix|аффикс|характеристик/))

// КЛАСС — всегда number|null
const classModel = computed<number | null>({
  get: () => {
    const g = classGroup.value
    if (!g) return null
    const raw = (selectionByGroup.value as any)[g.id]
    if (Array.isArray(raw)) return raw.length ? Number(raw[0]) : null
    return typeof raw === 'number' && Number.isFinite(raw) ? raw : null
  },
  set: (v) => {
    const g = classGroup.value
    if (!g) return
      ; (selectionByGroup.value as any)[g.id] = v == null ? null : Number(v)
  },
})

const bundleCents = computed(() => {
  const groups = (props.product.option_groups ?? []).filter((g: any) => g.type === 'bundle')
  let sum = 0
  for (const g of groups) {
    const sel = (selectionByGroup.value as any)[g.id]
    if (!Array.isArray(sel) || !sel.length) continue
    for (const row of sel) {
      const it = g.items?.find((i: any) => i.product_id === row.product_id)
      if (!it) continue
      sum += (it.price_cents || 0) * (Number(row.qty) || 0)
    }
  }
  return sum
})

// СЛОТ — всегда number|null
const slotModel = computed<number | null>({
  get: () => {
    const g = slotGroup.value
    if (!g) return null
    const raw = (selectionByGroup.value as any)[g.id]
    if (Array.isArray(raw)) return raw.length ? Number(raw[0]) : null
    return typeof raw === 'number' && Number.isFinite(raw) ? raw : null
  },
  set: (v) => {
    const g = slotGroup.value
    if (!g) return
      ; (selectionByGroup.value as any)[g.id] = v == null ? null : Number(v)
  },
})


// АФФИКСЫ — всегда массив number[]
const affixModel = computed<number[]>({
  get: () => {
    const g = affixGroup.value
    if (!g) return []
    const raw = (selectionByGroup.value as any)[g.id]
    if (Array.isArray(raw)) return raw
    if (typeof raw === 'number' && Number.isFinite(raw)) return [raw]
    return []
  },
  set: (v) => {
    const g = affixGroup.value
    if (!g) return
    const next = Array.isArray(v) ? v : (v != null ? [Number(v)] : [])
      ; (selectionByGroup.value as any)[g.id] = next
  },
})


const affixGaModel = computed<number[]>({
  get: () => (selectionByGroup.value as any)._affix_ga_ids ?? [],
  set: (ids) => {
    (selectionByGroup.value as any)._affix_ga_ids = Array.isArray(ids)
      ? Array.from(new Set(ids.map(Number))).slice(0, 3)
      : []
  },
})

onMounted(() => {
  const cg = classGroup.value
  if (cg) {
    const raw = (selectionByGroup.value as any)[cg.id]
    if (Array.isArray(raw))
      (selectionByGroup.value as any)[cg.id] = raw.length ? Number(raw[0]) : null
  }

  const sg = slotGroup.value
  if (sg) {
    const raw = (selectionByGroup.value as any)[sg.id]
    if (Array.isArray(raw))
      (selectionByGroup.value as any)[sg.id] = raw.length ? Number(raw[0]) : null
  }

  const ag = affixGroup.value
  if (ag) {
    const raw = (selectionByGroup.value as any)[ag.id]
    if (!Array.isArray(raw))
      (selectionByGroup.value as any)[ag.id] =
        typeof raw === 'number' && Number.isFinite(raw) ? [raw] : []
  }
})


// Какие группы скрыть из “обычного” рендера
const rareIds = computed(() => new Set(
  [classGroup.value?.id, slotGroup.value?.id, affixGroup.value?.id].filter(Boolean) as number[]
))



const otherGroups = computed(() =>
  (props.product.option_groups ?? []).filter(g => !rareIds.value.has(g.id))
)




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

const displayCents = computed(() => (qtyGroup ? unitCents.value : totalCents.value) + bundleCents.value)

async function addToCart() {
  errors.value = []
  triedToSubmit.value = true

  if (!canAddToCart.value) {
    errors.value.push('Заполните обязательные опции перед добавлением в корзину.')
    return
  }

  if (submitting.value) return
  submitting.value = true
  try {
    // 1) найдём все bundle-группы
    const bundleGroups = (props.product.option_groups ?? []).filter((g: any) => g.type === 'bundle')

    // 2) соберём выбранные строки из selectionByGroup
    const bundleRows: Array<{ product_id: number; qty: number }> = []
    for (const g of bundleGroups) {
      const sel = (selectionByGroup.value as any)[g.id]
      if (Array.isArray(sel)) {
        for (const row of sel) {
          if (row?.product_id && row?.qty > 0) {
            bundleRows.push({ product_id: Number(row.product_id), qty: Number(row.qty) })
          }
        }
      }
    }

    // 3A) если есть bundle-строки — отправим их по одной (простой путь)
    if (bundleRows.length) {
      for (const row of bundleRows) {
        await axios.post('/cart/add', {
          product_id: row.product_id,
          qty: row.qty,
        })
      }
      await loadSummary()
      return
    }

    // 3B) иначе — обычное добавление текущего продукта (как было)
    const payload = buildAddToCartPayload()
    payload.affix_ga_ids = affixGaModel.value ?? []
    const { data } = await axios.post('/cart/add', payload)
    if (data && data.summary) cartSummary.value = data.summary
    else await loadSummary()
  } catch (e: any) {
    // твоя обработка ошибок
    errors.value.push(e?.response?.data?.message || 'Failed to add to cart')
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
      <div v-if="errors.length" class="my-4 rounded-md border border-red-400  text-red-700 p-3">
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
            {{ qtyGroup ? 'Unit' : 'Price' }}:
            <span>{{ formatPrice(displayCents) }}</span>
            <template v-if="qtyGroup">
              → Total: <span class="text-primary">{{ formatPrice(totalCents) }}</span>
            </template>
          </div>



          <!-- RARE BUILDER -->
          <RareItemBuilder
            v-if="classGroup && slotGroup && affixGroup"
            :class-group="classGroup"
            :slot-group="slotGroup"
            :affix-group="affixGroup"
            :currency="'USD'"
            :ga-limit="gaLimit"   
            v-model:class-id="classModel"
            v-model:slot-id="slotModel"
            v-model:affix-ids="affixModel"
            v-model:affix-ga-ids="affixGaModel"
          />

          <!-- Остальные группы (кроме class/slot/affix) -->

          <div v-if="otherGroups.length" class="mt-4 space-y-6">
            <div v-for="group in otherGroups" :key="group.id" class="border rounded-lg p-3"
              :class="triedToSubmit && missingRequiredSet.has(group.id) ? 'border-red-400' : 'border-border'">
              <component :is="resolveGroupComponent(group.type, group) || 'div'" :group="group as any"
                v-model:selected="(selectionByGroup as any)[group.id]" />
              <p v-if="triedToSubmit && missingRequiredSet.has(group.id)" class="mt-1 text-sm text-red-600">
                Это поле обязательно для выбора
              </p>
            </div>
          </div>

          <!-- Кнопка — вне условного блока конструктора -->
          <button class="mt-5 px-4 py-2 rounded-lg bg-primary text-primary-foreground" @click.prevent="addToCart">
            {{ submitting ? 'Adding…' : 'Add to cart' }}
          </button>

        </div>
      </div> <!-- правая колонка -->

    </section>
  </DefaultLayout>
</template>