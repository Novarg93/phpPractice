import { computed, type Ref } from 'vue'
import type {
  ProductWithGroups,
  Selection,
  DoubleRangeGroup,
  QtyGroup,
} from '@/types/product-options'

function isPerUnitFlag(v: unknown) { return v === true || v === 1 || v === '1' }
const isRangeSel = (x: unknown): x is { min: number; max: number } =>
  !!x && typeof x === 'object' && 'min' in (x as any) && 'max' in (x as any)

/* ───────── helpers: clamp/snap/align ───────── */
function clamp(v: number, a: number, b: number) { return Math.min(b, Math.max(a, v)) }
function snapToGrid(val: number, baseMin: number, step: number) {
  // как в твоём слайдере: «вниз» к ближайшему допустимому значению
  return val - ((val - baseMin) % step)
}
function alignRange(g: DoubleRangeGroup, sel: {min: number; max: number}) {
  const Smin = g.slider_min
  const Smax = g.slider_max
  const step = Math.max(1, g.slider_step || 1)

  const a = snapToGrid(clamp(sel.min, Smin, Smax), Smin, step)
  const b = snapToGrid(clamp(sel.max, Smin, Smax), Smin, step)
  const min = Math.min(a, b)
  const max = Math.max(a, b)
  return { min, max, step, Smin }
}
function unitsInclusive(min: number, max: number, step: number) {
  // включительно
  return Math.floor((max - min) / step) + 1
}

/* ───────── FLAT: как в CartController → exclusive шаги, без base_fee ───────── */
function priceRangeFlat(g: DoubleRangeGroup, sel: { min: number; max: number }) {
  const { min, max, step } = alignRange(g, sel)
  // exclusive: сколько «ступеней» пройти от min до max
  const steps = Math.max(0, Math.floor((max - min) / step))
  const unit = Number(g.unit_price_cents ?? 0)
  return steps * unit
}

/* ───────── TIERED: полностью зеркалим CartController::priceTiered ───────── */
function priceRangeTiered(g: DoubleRangeGroup, sel: { min: number; max: number }) {
  const { min, max, step } = alignRange(g, sel)
  const spanTotal = Math.max(0, max - min) // exclusive

  const tiers = (g.tiers ?? []).slice().sort((x, y) => x.from - y.from)

  let piecewise = 0
  let highestUnit = 0
  let weightedSum = 0 // unit(after multiplier) * (to-from)

  for (const t of tiers) {
    const from = Math.max(t.from, min)
    const to   = Math.min(t.to,   max)
    if (to <= from) continue

    let steps = to - from // exclusive
    let unit = Number(t.unit_price_cents ?? 0)

    // multiplier влияет и на weightedSum (как в бэке)
    if (t.multiplier) unit = Math.round(unit * Number(t.multiplier))

    // min_block округляем вверх
    if (t.min_block && t.min_block > 1) {
      steps = Math.ceil(steps / Number(t.min_block)) * Number(t.min_block)
    }

    let cost = unit * steps
    if (t.cap_cents != null) cost = Math.min(cost, Number(t.cap_cents))

    piecewise += cost
    highestUnit = Math.max(highestUnit, unit)
    weightedSum += unit * (to - from) // без блоков/кап, но с multiplier
  }

  const strategy = g.tier_combine_strategy ?? 'sum_piecewise'
  const variable =
    strategy === 'highest_tier_only' ? highestUnit * spanTotal
    : strategy === 'weighted_average' ? Math.round((spanTotal > 0 ? weightedSum / spanTotal : 0) * spanTotal)
    : piecewise // sum_piecewise

  const base = Number(g.base_fee_cents ?? 0) // только для tiered
  return base + variable
}

/* ───────── основной хук ───────── */
export function usePricing(
  product: ProductWithGroups,
  selectionByGroup: Ref<Record<number, Selection>>
) {
  // selector: absolute + per-unit
  const optionsPerUnitCents = computed(() => {
    let sum = 0
    ;(product.option_groups ?? []).forEach((g: any) => {
      if (g.type !== 'selector' || g.pricing_mode !== 'absolute' || !isPerUnitFlag(g.multiply_by_qty)) return
      const sel = selectionByGroup.value[g.id]
      const ids = g.selection_mode === 'single'
        ? (typeof sel === 'number' ? [sel] : [])
        : (Array.isArray(sel) ? sel : [])
      ids.forEach((id: number) => {
        const v = g.values.find((x: any) => x.id === id)
        if (v) sum += Number(v.delta_cents ?? 0)
      })
    })
    return sum
  })

  // selector: absolute + per-order
  const optionsPerOrderCents = computed(() => {
    let sum = 0
    ;(product.option_groups ?? []).forEach((g: any) => {
      if (g.type !== 'selector' || g.pricing_mode !== 'absolute' || isPerUnitFlag(g.multiply_by_qty)) return
      const sel = selectionByGroup.value[g.id]
      const ids = g.selection_mode === 'single'
        ? (typeof sel === 'number' ? [sel] : [])
        : (Array.isArray(sel) ? sel : [])
      ids.forEach((id: number) => {
        const v = g.values.find((x: any) => x.id === id)
        if (v) sum += Number(v.delta_cents ?? 0)
      })
    })
    return sum
  })

  // double_range_slider → всегда считаем как per-unit надбавку
  const totalRangePerUnitCents = computed(() => {
    let sum = 0
    ;(product.option_groups ?? []).forEach((g: any) => {
      if (g.type !== 'double_range_slider') return
      const sel = selectionByGroup.value[g.id]
      if (!isRangeSel(sel)) return
      sum += (g.pricing_mode === 'tiered')
        ? priceRangeTiered(g, sel)
        : priceRangeFlat(g, sel)
    })
    return sum
  })

  // selector: percent + per-unit
  const percentPerUnitFactor = computed(() => {
    let f = 1
    ;(product.option_groups ?? []).forEach((g: any) => {
      if (g.type !== 'selector' || g.pricing_mode !== 'percent' || !isPerUnitFlag(g.multiply_by_qty)) return
      const sel = selectionByGroup.value[g.id]
      const ids = g.selection_mode === 'single'
        ? (typeof sel === 'number' ? [sel] : [])
        : (Array.isArray(sel) ? sel : [])
      ids.forEach((id: number) => {
        const v = g.values.find((x: any) => x.id === id)
        if (v) f *= (1 + Number(v.delta_percent ?? 0) / 100)
      })
    })
    return f
  })

  // selector: percent + per-order
  const percentPerOrderFactor = computed(() => {
    let f = 1
    ;(product.option_groups ?? []).forEach((g: any) => {
      if (g.type !== 'selector' || g.pricing_mode !== 'percent' || isPerUnitFlag(g.multiply_by_qty)) return
      const sel = selectionByGroup.value[g.id]
      const ids = g.selection_mode === 'single'
        ? (typeof sel === 'number' ? [sel] : [])
        : (Array.isArray(sel) ? sel : [])
      ids.forEach((id: number) => {
        const v = g.values.find((x: any) => x.id === id)
        if (v) f *= (1 + Number(v.delta_percent ?? 0) / 100)
      })
    })
    return f
  })

  const unitCents = computed(() => {
    const base = Number(product.price_cents || 0)
                + optionsPerUnitCents.value
                + totalRangePerUnitCents.value
    return Math.round(base * percentPerUnitFactor.value)
  })

  const totalCents = computed(() => {
    const qGroup = (product.option_groups ?? []).find((g: any) => g.type === 'quantity_slider') as QtyGroup | undefined
    const qSel = qGroup ? selectionByGroup.value[qGroup.id] : undefined
    const qty = typeof qSel === 'number' ? qSel : 1
    const subtotal = unitCents.value * qty
    return Math.round((subtotal + optionsPerOrderCents.value) * percentPerOrderFactor.value)
  })

  return { unitCents, totalCents }
}