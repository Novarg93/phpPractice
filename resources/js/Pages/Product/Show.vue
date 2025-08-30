<script setup lang="ts">

import { ref, computed, onMounted, watch } from 'vue'
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import type { Game, Category, Product } from '@/types'
import axios from 'axios'
import { useCartSummary, summary } from '@/composables/useCartSummary' // summary тоже импортируем
import Breadcrumbs from '@/Components/Breadcrumbs.vue'


type RangeTier = {
  from: number
  to: number
  unit_price_cents: number
  label?: string
  min_block?: number
  multiplier?: number
  cap_cents?: number
}

type DoubleRangeGroup = {
  id: number
  title: string
  type: 'double_range_slider'
  is_required: boolean
  slider_min: number
  slider_max: number
  slider_step: number
  range_default_min: number | null
  range_default_max: number | null
  pricing_mode: 'flat' | 'tiered'
  unit_price_cents?: number
  base_fee_cents?: number
  max_span?: number | null
  tier_combine_strategy?: 'sum_piecewise' | 'highest_tier_only' | 'weighted_average'
  tiers?: RangeTier[]
}

type QtyGroup = {
  id: number
  type: 'quantity_slider'
  is_required: boolean
  qty_min: number | null
  qty_max: number | null
  qty_step: number | null
  qty_default: number | null
}


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
          multiply_by_qty?: boolean
          values: Array<{ id: number; title: string; price_delta_cents: number; is_default?: boolean }>
        }
      | {
          id: number
          title: string
          type: 'checkbox_additive'
          is_required: boolean
          multiply_by_qty?: boolean
          values: Array<{ id: number; title: string; price_delta_cents: number }>
        }
      | QtyGroup
      | DoubleRangeGroup
    >
  }
}>()

/* handy refs */
const { game, category, product } = props
const { loadSummary } = useCartSummary()

/* ========================================================================== */
/* ============= Utils ====================================================== */
/* ========================================================================== */
function formatPrice(cents: number) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format((cents || 0) / 100)
}
function clamp(val: number, min: number, max: number) {
  return Math.min(max, Math.max(min, val))
}
function snapToStep(val: number, base: number, step: number) {
  const offset = (val - base) % step
  return val - offset
}

function isPerUnitFlag(v: unknown): boolean {
  // true если boolean true / 1 / "1"
  return v === true || v === 1 || v === '1'
}

/* ========================================================================== */
/* ============= Quantity slider =========================================== */
/* ========================================================================== */
const qtyGroup = computed(
  () =>
    (product.option_groups ?? []).find(
      (g): g is QtyGroup => g.type === 'quantity_slider'
    ) ?? null
)

const qty = ref<number>(1)

function snapQty(raw: number) {
  const min = Number(qtyGroup.value?.qty_min ?? 1)
  const max = Number(qtyGroup.value?.qty_max ?? 1)
  const step = Number(qtyGroup.value?.qty_step ?? 1)
  const clamped = clamp(Number(raw) || min, min, max)
  return clamped - ((clamped - min) % step)
}

/* ========================================================================== */
/* ============= Double range ============================================== */
/* ========================================================================== */
const selectedRange = ref<Record<number, { min: number; max: number }>>({})

function sanitizeRange(g: DoubleRangeGroup, minVal: number, maxVal: number) {
  const min = Number(g.slider_min)
  const max = Number(g.slider_max)
  const step = Number(g.slider_step || 1)

  let a = snapToStep(clamp(Number(minVal), min, max), min, step)
  let b = snapToStep(clamp(Number(maxVal), min, max), min, step)
  if (a > b) [a, b] = [b, a]
  return { a, b }
}

function onRangeMinChange(g: DoubleRangeGroup, raw: number) {
  const current = selectedRange.value[g.id] ?? { min: g.slider_min, max: g.slider_max }
  const { a, b } = sanitizeRange(g, raw, current.max)
  selectedRange.value[g.id] = { min: a, max: b }
}

function onRangeMaxChange(g: DoubleRangeGroup, raw: number) {
  const current = selectedRange.value[g.id] ?? { min: g.slider_min, max: g.slider_max }
  const { a, b } = sanitizeRange(g, current.min, raw)
  selectedRange.value[g.id] = { min: a, max: b }
}

function pct(g: DoubleRangeGroup, value: number) {
  const span = g.slider_max - g.slider_min
  if (span <= 0) return 0
  return ((value - g.slider_min) / span) * 100
}

/* ===== range pricing (mirror backend) ==================================== */
function priceRangeFlat(g: DoubleRangeGroup, sel: { min: number; max: number }) {
  const step = g.slider_step || 1
  const baseMin = g.slider_min
  const a = snapToStep(clamp(sel.min, g.slider_min, g.slider_max), baseMin, step)
  const b = snapToStep(clamp(sel.max, g.slider_min, g.slider_max), baseMin, step)
  const min = Math.min(a, b)
  const max = Math.max(a, b)
  const span = Math.max(0, max - min) // exclusive min
  const unit = Number(g.unit_price_cents ?? 0)
  return unit * span
}

function priceRangeTiered(g: DoubleRangeGroup, sel: { min: number; max: number }) {
  const a = Math.min(sel.min, sel.max)
  const b = Math.max(sel.min, sel.max)
  const spanTotal = Math.max(0, b - a)
  if (spanTotal === 0) return Number(g.base_fee_cents ?? 0) // только base_fee

  const tiers = (g.tiers ?? []).slice().sort((x, y) => x.from - y.from)
  let piecewise = 0
  let highestUnit = 0
  let weightedSum = 0

  for (const t of tiers) {
    const from = Math.max(t.from, a)
    const to = Math.min(t.to, b)
    if (to <= from) continue

    let steps = to - from
    let unit = Number(t.unit_price_cents ?? 0)
    if (t.multiplier) unit = Math.round(unit * Number(t.multiplier))

    if (t.min_block) steps = Math.ceil(steps / Number(t.min_block)) * Number(t.min_block)

    let cost = unit * steps
    if (t.cap_cents != null) cost = Math.min(cost, Number(t.cap_cents))

    piecewise += cost
    highestUnit = Math.max(highestUnit, unit)
    weightedSum += unit * (to - from)
  }

  const strategy = g.tier_combine_strategy ?? 'sum_piecewise'
  let variablePart =
    strategy === 'highest_tier_only'
      ? highestUnit * spanTotal
      : strategy === 'weighted_average'
      ? Math.round((spanTotal > 0 ? weightedSum / spanTotal : 0) * spanTotal)
      : piecewise

  const baseFee = Number(g.base_fee_cents ?? 0)
  return baseFee + variablePart
}

/* ========================================================================== */
/* ============= Radio / Checkbox selections ================================ */
/* ========================================================================== */
const selectedByGroup = ref<Record<number, number | null>>({})
const selectedMulti = ref<Record<number, Set<number>>>({})

;(product.option_groups ?? []).forEach((g) => {
  if (g.type === 'radio_additive') {
    const def = g.values.find((v) => v.is_default) ?? g.values[0]
    selectedByGroup.value[g.id] = def ? def.id : null
  } else if (g.type === 'checkbox_additive') {
    selectedMulti.value[g.id] = new Set<number>()
  }
})

/* ========================================================================== */
/* ============= Lifecycle =================================================== */
/* ========================================================================== */
onMounted(() => {
  // qty init
  if (qtyGroup.value) {
    const def = Number(qtyGroup.value.qty_default ?? qtyGroup.value.qty_min ?? 1)
    qty.value = snapQty(def)
  } else {
    qty.value = 1
  }

  // range init
  ;(product.option_groups ?? []).forEach((g) => {
    if (g.type === 'double_range_slider') {
      const defMin = Number(g.range_default_min ?? g.slider_min)
      const defMax = Number(g.range_default_max ?? g.slider_max)
      const { a, b } = sanitizeRange(g, defMin, defMax)
      selectedRange.value[g.id] = { min: a, max: b }
    }
  })
})

watch(qty, (v) => {
  if (!qtyGroup.value) return
  const snapped = snapQty(Number(v) || 1)
  if (snapped !== v) qty.value = snapped
})

/* ========================================================================== */
/* ============= Pricing (UI preview) ======================================= */
/* ========================================================================== */
const optionsPerUnitCents = computed(() => {
  let sum = 0
  ;(product.option_groups ?? []).forEach((g: any) => {
    if (g.type === 'radio_additive') {
      const vid = selectedByGroup.value[g.id]
      if (vid != null) {
        const v = g.values.find((x: any) => x.id === vid)
        if (v && isPerUnitFlag(g.multiply_by_qty)) sum += Number(v.price_delta_cents || 0)
      }
    } else if (g.type === 'checkbox_additive') {
      selectedMulti.value[g.id]?.forEach((vid) => {
        const v = g.values.find((x: any) => x.id === vid)
        if (v && isPerUnitFlag(g.multiply_by_qty)) sum += Number(v.price_delta_cents || 0)
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
        if (v && !isPerUnitFlag(g.multiply_by_qty)) sum += Number(v.price_delta_cents || 0)
      }
    } else if (g.type === 'checkbox_additive') {
      selectedMulti.value[g.id]?.forEach((vid) => {
        const v = g.values.find((x: any) => x.id === vid)
        if (v && !isPerUnitFlag(g.multiply_by_qty)) sum += Number(v.price_delta_cents || 0)
      })
    }
  })
  return sum
})

/** сумма per-unit по всем double_range_slider */
const totalRangePerUnitCents = computed(() => {
  let sum = 0
  ;(product.option_groups ?? []).forEach((g: any) => {
    if (g.type !== 'double_range_slider') return
    const sel = selectedRange.value[g.id]
    if (!sel) return
    if (g.pricing_mode === 'tiered') {
      sum += priceRangeTiered(g, sel)
    } else {
      sum += priceRangeFlat(g, sel)
    }
  })
  return sum
})

const unitCents = computed(() => {
  return Number(product.price_cents || 0) + optionsPerUnitCents.value + totalRangePerUnitCents.value
})

const totalCents = computed(() => {
  const q = qtyGroup.value ? Number(qty.value || 1) : 1
  return unitCents.value * q + optionsPerOrderCents.value
})

/* ========================================================================== */
/* ============= Actions ===================================================== */
/* ========================================================================== */
async function addToCart() {
  try {
    const chosen: number[] = []
    ;(product.option_groups ?? []).forEach((g: any) => {
      if (g.type === 'radio_additive') {
        const vid = selectedByGroup.value[g.id]
        if (vid != null) chosen.push(vid)
      } else if (g.type === 'checkbox_additive') {
        selectedMulti.value[g.id]?.forEach((id) => chosen.push(id))
      }
    })

    const range_options: Array<{ option_group_id: number; selected_min: number; selected_max: number }> = []
    ;(product.option_groups ?? []).forEach((g: any) => {
      if (g.type === 'double_range_slider') {
        const sel = selectedRange.value[g.id]
        if (sel) {
          range_options.push({
            option_group_id: g.id,
            selected_min: sel.min,
            selected_max: sel.max,
          })
        }
      }
    })

    const { data } = await axios.post('/cart/add', {
      product_id: product.id,
      option_value_ids: chosen,
      qty: qtyGroup.value ? qty.value : 1,
      range_options,
    })

    if (data && data.summary) {
      summary.value = data.summary
    } else {
      await loadSummary() // на всякий
    }
  } catch (e) {
    console.error('addToCart failed', e)
    await loadSummary() // fallback при ошибке
  }
}
</script>

<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">
      <Breadcrumbs :game="game" :category="category" :product="product" />

      <div class="grid md:grid-cols-2 gap-6 my-6">
        <!-- image -->
        <div>
          <img v-if="product.image_url" :src="product.image_url" class="w-full rounded-xl border border-border" />
          <div v-else class="aspect-video rounded-xl border grid place-items-center text-sm text-muted-foreground">
            No image
          </div>
        </div>

        <!-- main -->
        <div>
          <h1 class="text-3xl font-semibold">{{ product.name }}</h1>

          <div class="mt-2 text-2xl font-bold">
            Base: <span>{{ formatPrice(unitCents) }}</span>
            <template v-if="qtyGroup">
              → Total: <span class="text-primary">{{ formatPrice(totalCents) }}</span>
            </template>
          </div>

          <!-- options -->
          <div v-if="product.option_groups?.length" class="mt-4 space-y-6">
            <div v-for="group in product.option_groups" :key="group.id" class="border border-border rounded-lg p-3">
              <div class="font-medium mb-2">
                {{ group.title }}
                <span v-if="group.is_required" class="text-xs text-muted-foreground">(required)</span>
              </div>

              <!-- radio -->
              <div v-if="group.type === 'radio_additive'" class="space-y-2">
                <label v-for="v in group.values" :key="v.id" class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    :name="'g-' + group.id"
                    :value="v.id"
                    :checked="selectedByGroup[group.id] === v.id"
                    @change="selectedByGroup[group.id] = v.id"
                  />
                  <span>{{ v.title }}</span>
                  <span class="ml-auto text-sm text-muted-foreground">
                    {{ v.price_delta_cents >= 0 ? '+' : '' }}{{ formatPrice(v.price_delta_cents) }}
                  </span>
                </label>
              </div>

              <!-- checkbox -->
              <div v-else-if="group.type === 'checkbox_additive'" class="space-y-2">
                <label v-for="v in group.values" :key="v.id" class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    :checked="selectedMulti[group.id]?.has(v.id)"
                    @change="(e: any) => {
                      const set = selectedMulti[group.id];
                      if (!set) return;
                      if (e.target.checked) set.add(v.id); else set.delete(v.id);
                    }"
                  />
                  <span>{{ v.title }}</span>
                  <span class="ml-auto text-sm text-muted-foreground">
                    {{ v.price_delta_cents >= 0 ? '+' : '' }}{{ formatPrice(v.price_delta_cents) }}
                  </span>
                </label>
              </div>

              <!-- quantity slider -->
              <div v-else-if="group.type === 'quantity_slider'" class="space-y-3">
                <div class="flex items-center justify-between">
                  <div class="font-medium">Quantity</div>
                  <div class="text-sm text-muted-foreground">
                    {{ qty }} (min: {{ group.qty_min ?? 1 }}, max: {{ group.qty_max ?? 1 }}, step:
                    {{ group.qty_step ?? 1 }})
                  </div>
                </div>

                <input
                  type="range"
                  class="w-full"
                  :min="group.qty_min ?? 1"
                  :max="group.qty_max ?? 1"
                  :step="group.qty_step ?? 1"
                  v-model.number="qty"
                />

                <div class="mt-2 flex items-center gap-2">
                  <input
                    type="number"
                    class="w-24 bg-background border rounded px-2 py-1"
                    :min="group.qty_min ?? 1"
                    :max="group.qty_max ?? 1"
                    :step="group.qty_step ?? 1"
                    v-model.number="qty"
                  />
                  <button class="px-2 py-1 border rounded" @click="qty = Math.max(group.qty_min ?? 1, qty - (group.qty_step ?? 1))">-</button>
                  <button class="px-2 py-1 border rounded" @click="qty = Math.min(group.qty_max ?? 1, qty + (group.qty_step ?? 1))">+</button>
                </div>
              </div>

              <!-- double range -->
              <div v-else-if="group.type === 'double_range_slider'" class="space-y-3">
                <div class="flex items-center justify-between">
                  <div class="font-medium">Level range</div>
                  <div class="text-sm text-muted-foreground">
                    {{ selectedRange[group.id]?.min }}–{{ selectedRange[group.id]?.max }}
                    (min: {{ group.slider_min }}, max: {{ group.slider_max }}, step: {{ group.slider_step }})
                  </div>
                </div>

                <div class="relative h-2 rounded bg-muted overflow-hidden">
                  <div
                    class="absolute top-0 h-2 bg-primary/50"
                    :style="{
                      left: pct(group, selectedRange[group.id]?.min ?? group.slider_min) + '%',
                      width:
                        (pct(group, selectedRange[group.id]?.max ?? group.slider_max) -
                          pct(group, selectedRange[group.id]?.min ?? group.slider_min)) + '%'
                    }"
                  />
                  <template v-if="group.pricing_mode === 'tiered' && group.tiers?.length">
                    <div
                      v-for="(t, i) in group.tiers"
                      :key="'from-' + i"
                      class="absolute top-[-6px] bottom-[-6px] w-px bg-foreground/40"
                      :style="{ left: pct(group, t.from) + '%' }"
                      :title="`Tier from ${t.from}`"
                    />
                    <div
                      v-for="(t, i) in group.tiers"
                      :key="'to-' + i"
                      class="absolute top-[-6px] bottom-[-6px] w-px bg-foreground/40"
                      :style="{ left: pct(group, t.to) + '%' }"
                      :title="`Tier to ${t.to}`"
                    />
                  </template>
                </div>

                <div class="relative">
                  <input
                    type="range"
                    class="w-full appearance-none bg-transparent absolute top-0 -translate-y-6  "
                    :min="group.slider_min"
                    :max="group.slider_max"
                    :step="group.slider_step || 1"
                    :value="selectedRange[group.id]?.min ?? group.slider_min"
                    @input="onRangeMinChange(group, Number(($event.target as HTMLInputElement).value))"
                    style="position: relative; z-index: 20;"
                  />
                  <input
                    type="range"
                    class="w-full appearance-none bg-transparent absolute top-0 -translate-y-8  "
                    :min="group.slider_min"
                    :max="group.slider_max"
                    :step="group.slider_step || 1"
                    :value="selectedRange[group.id]?.max ?? group.slider_max"
                    @input="onRangeMaxChange(group, Number(($event.target as HTMLInputElement).value))"
                    style="position: relative; z-index: 20;"
                  />
                </div>

                <div class="mt-2 flex items-center gap-2">
                  <div class="flex items-center gap-2">
                    <label class="text-sm text-muted-foreground">From</label>
                    <input
                      type="number"
                      class="w-24 bg-background border rounded px-2 py-1"
                      :min="group.slider_min"
                      :max="group.slider_max"
                      :step="group.slider_step || 1"
                      :value="selectedRange[group.id]?.min ?? group.slider_min"
                      @input="onRangeMinChange(group, Number(($event.target as HTMLInputElement).value))"
                    />
                  </div>

                  <div class="flex items-center gap-2">
                    <label class="text-sm text-muted-foreground">To</label>
                    <input
                      type="number"
                      class="w-24 bg-background border rounded px-2 py-1"
                      :min="group.slider_min"
                      :max="group.slider_max"
                      :step="group.slider_step || 1"
                      :value="selectedRange[group.id]?.max ?? group.slider_max"
                      @input="onRangeMaxChange(group, Number(($event.target as HTMLInputElement).value))"
                    />
                  </div>

                  <div class="ml-auto flex items-center gap-2">
                    <button
                      class="px-2 py-1 border rounded"
                      @click="onRangeMinChange(group, (selectedRange[group.id]?.min ?? group.slider_min) - (group.slider_step || 1))"
                    >–</button>
                    <button
                      class="px-2 py-1 border rounded"
                      @click="onRangeMinChange(group, (selectedRange[group.id]?.min ?? group.slider_min) + (group.slider_step || 1))"
                    >+</button>

                    <span class="opacity-30">|</span>

                    <button
                      class="px-2 py-1 border rounded"
                      @click="onRangeMaxChange(group, (selectedRange[group.id]?.max ?? group.slider_max) - (group.slider_step || 1))"
                    >–</button>
                    <button
                      class="px-2 py-1 border rounded"
                      @click="onRangeMaxChange(group, (selectedRange[group.id]?.max ?? group.slider_max) + (group.slider_step || 1))"
                    >+</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <button class="mt-5 px-4 py-2 rounded-lg bg-primary text-primary-foreground" @click.prevent="addToCart">
            Add to cart
          </button>

          <div v-if="product.short" class="mt-6 text-base">{{ product.short }}</div>
          <div v-if="product.description" class="prose prose-invert max-w-none mt-4" v-html="product.description" />
        </div>
      </div>
    </section>
  </DefaultLayout>
</template>