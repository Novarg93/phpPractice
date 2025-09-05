import { computed, type Ref } from 'vue'
import type {
  ProductWithGroups,
  Selection,
  DoubleRangeGroup,
  QtyGroup,
} from '@/types/product-options'

/* ───────── helpers ───────── */
const isRangeSel = (x: unknown): x is { min: number; max: number } =>
  !!x && typeof x === 'object' && 'min' in (x as any) && 'max' in (x as any)

// ⚠️ на бэке null => per-unit. Делаем так же.
function scopeIsUnit(g: any): boolean {
  return (g?.multiply_by_qty === null || g?.multiply_by_qty === undefined)
    ? true
    : (g.multiply_by_qty === true || g.multiply_by_qty === 1 || g.multiply_by_qty === '1')
}

function isPercentGroup(g: any): boolean {
  return g?.pricing_mode === 'percent'
      || g?.type === 'radio_percent'
      || g?.type === 'checkbox_percent'
}

function selectedIds(sel: unknown): number[] {
  if (Array.isArray(sel)) return sel.map(Number).filter(Number.isFinite)
  const n = Number(sel)
  return Number.isFinite(n) ? [n] : []
}

/* ───────── clamp/snap/align для double_range ───────── */
function clamp(v: number, a: number, b: number) { return Math.min(b, Math.max(a, v)) }
function snapToGrid(val: number, baseMin: number, step: number) {
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
  return { min, max }
}

/* FLAT (exclusive steps) */
function priceRangeFlat(g: DoubleRangeGroup, sel: { min: number; max: number }) {
  const { min, max } = alignRange(g, sel)
  const step = Math.max(1, g.slider_step || 1)
  const steps = Math.max(0, Math.floor((max - min) / step)) // exclusive
  const unit = Number(g.unit_price_cents ?? 0)
  return steps * unit
}

/* TIERED (как на бэке) */
function priceRangeTiered(g: DoubleRangeGroup, sel: { min: number; max: number }) {
  const { min, max } = alignRange(g, sel)
  const spanTotal = Math.max(0, max - min) // exclusive

  const tiers = (g.tiers ?? []).slice().sort((x, y) => x.from - y.from)

  let piecewise = 0
  let highestUnit = 0
  let weightedSum = 0

  for (const t of tiers) {
    const from = Math.max(t.from, min)
    const to   = Math.min(t.to,   max)
    if (to <= from) continue

    let steps = to - from // exclusive
    let unit = Number(t.unit_price_cents ?? 0)

    if (t.multiplier) unit = Math.round(unit * Number(t.multiplier))

    if (t.min_block && t.min_block > 1) {
      steps = Math.ceil(steps / Number(t.min_block)) * Number(t.min_block)
    }

    let cost = unit * steps
    if (t.cap_cents != null) cost = Math.min(cost, Number(t.cap_cents))

    piecewise += cost
    highestUnit = Math.max(highestUnit, unit)
    weightedSum += unit * (to - from)
  }

  const strategy = g.tier_combine_strategy ?? 'sum_piecewise'
  const variable =
    strategy === 'highest_tier_only' ? highestUnit * spanTotal
    : strategy === 'weighted_average' ? Math.round((spanTotal > 0 ? weightedSum / spanTotal : 0) * spanTotal)
    : piecewise

  const base = Number(g.base_fee_cents ?? 0)
  return base + variable
}

/* ───────── основной хук ───────── */
export function usePricing(
  product: ProductWithGroups,
  selectionByGroup: Ref<Record<number, Selection>>
) {
  // ABSOLUTE per-unit
  const optionsPerUnitCents = computed(() => {
    let sum = 0
    ;(product.option_groups ?? []).forEach((g: any) => {
      if (g?.type !== 'selector' || g?.pricing_mode !== 'absolute' || !scopeIsUnit(g)) return
      const ids = selectedIds(selectionByGroup.value[g.id])
      ids.forEach((id) => {
        const v = (g.values ?? []).find((x: any) => x.id === id)
        if (v) sum += Number(v.delta_cents ?? 0)
      })
    })
    return sum
  })

  // ABSOLUTE per-order
  const optionsPerOrderCents = computed(() => {
    let sum = 0
    ;(product.option_groups ?? []).forEach((g: any) => {
      if (g?.type !== 'selector' || g?.pricing_mode !== 'absolute' || scopeIsUnit(g)) return
      const ids = selectedIds(selectionByGroup.value[g.id])
      ids.forEach((id) => {
        const v = (g.values ?? []).find((x: any) => x.id === id)
        if (v) sum += Number(v.delta_cents ?? 0)
      })
    })
    return sum
  })

  // DOUBLE RANGE → всегда per-unit надбавка
  const totalRangePerUnitCents = computed(() => {
    let sum = 0
    ;(product.option_groups ?? []).forEach((g: any) => {
      if (g?.type !== 'double_range_slider') return
      const sel = selectionByGroup.value[g.id]
      if (!isRangeSel(sel)) return
      sum += (g.pricing_mode === 'tiered')
        ? priceRangeTiered(g, sel)
        : priceRangeFlat(g, sel)
    })
    return sum
  })

  // PERCENT per-unit (суммируем проценты!)
  const percentPerUnitPct = computed(() => {
    let pct = 0
    ;(product.option_groups ?? []).forEach((g: any) => {
      if (!isPercentGroup(g) || !scopeIsUnit(g)) return
      const ids = selectedIds(selectionByGroup.value[g.id])
      ids.forEach((id) => {
        const v = (g.values ?? []).find((x: any) => x.id === id)
        if (v) pct += Number((v as any).delta_percent ?? (v as any).value_percent ?? 0)
      })
    })
    return pct
  })

  // PERCENT per-order (суммируем проценты!)
  const percentPerOrderPct = computed(() => {
    let pct = 0
    ;(product.option_groups ?? []).forEach((g: any) => {
      if (!isPercentGroup(g) || scopeIsUnit(g)) return
      const ids = selectedIds(selectionByGroup.value[g.id])
      ids.forEach((id) => {
        const v = (g.values ?? []).find((x: any) => x.id === id)
        if (v) pct += Number((v as any).delta_percent ?? (v as any).value_percent ?? 0)
      })
    })
    return pct
  })

  const unitCents = computed(() => {
    const base =
      Number(product.price_cents || 0) +
      optionsPerUnitCents.value +
      totalRangePerUnitCents.value

    // применяем суммарный процент ОДИН раз
    return Math.round(base * (1 + percentPerUnitPct.value / 100))
  })

  const totalCents = computed(() => {
    const qGroup = (product.option_groups ?? []).find((g: any) => g.type === 'quantity_slider') as QtyGroup | undefined
    const qSel = qGroup ? selectionByGroup.value[qGroup.id] : undefined
    const qty = typeof qSel === 'number' && Number.isFinite(qSel) ? qSel : 1

    const preOrder = unitCents.value * qty + optionsPerOrderCents.value
    return Math.round(preOrder * (1 + percentPerOrderPct.value / 100))
  })

  return { unitCents, totalCents }
}