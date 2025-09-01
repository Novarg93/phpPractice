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

/* ───────── double-range pricing (в ноль с бэком) ───────── */
function priceRangeFlat(g: DoubleRangeGroup, sel: { min: number; max: number }) {
  const { min, max, step, Smin } = alignRange(g, sel)
  const u = unitsInclusive(min, max, step)
  const unitPrice = Number(g.unit_price_cents ?? 0)
  const base = Number(g.base_fee_cents ?? 0)
  return base + u * unitPrice
}

function priceRangeTiered(g: DoubleRangeGroup, sel: { min: number; max: number }) {
  const { min, max, step, Smin } = alignRange(g, sel)
  const fullUnits = unitsInclusive(min, max, step)

  // пересечения с тирами + выравнивание границ сегмента к сетке
  const tiers = (g.tiers ?? []).slice().sort((x, y) => x.from - y.from)

  type Seg = {
    from: number; to: number; unit_price_cents: number;
    label?: string|null; min_block?: number|null; multiplier?: number|null; cap_cents?: number|null;
  }
  const parts: Seg[] = []
  for (const t of tiers) {
    const f  = Math.max(min, t.from)
    const to = Math.min(max, t.to)
    if (to < f) continue

    // align to grid:
    const alignedFrom = f + ((step - ((f - Smin) % step)) % step)
    const alignedTo   = to - (((to - Smin) % step))
    if (alignedFrom > alignedTo) continue

    parts.push({
      from: alignedFrom,
      to: alignedTo,
      unit_price_cents: Number(t.unit_price_cents ?? 0),
      label: t.label ?? null,
      min_block: t.min_block ?? null,
      multiplier: t.multiplier ?? null,
      cap_cents: t.cap_cents ?? null,
    })
  }

  let piecewise = 0
  let maxUnit = 0
  for (const p of parts) {
    const segUnits = unitsInclusive(p.from, p.to, step)
    let subtotal = segUnits * p.unit_price_cents

    if (p.min_block && p.min_block > 1) {
      const blocks = Math.ceil(segUnits / p.min_block)
      subtotal = blocks * p.min_block * p.unit_price_cents
    }
    if (p.multiplier && p.multiplier !== 1) {
      subtotal = Math.round(subtotal * p.multiplier)
    }
    if (p.cap_cents != null) {
      subtotal = Math.min(subtotal, p.cap_cents)
    }

    piecewise += subtotal
    if (p.unit_price_cents > maxUnit) maxUnit = p.unit_price_cents
  }

  const strategy = g.tier_combine_strategy ?? 'sum_piecewise'
  const variable =
    strategy === 'highest_tier_only' ? (maxUnit * fullUnits)
  : strategy === 'weighted_average'  ? piecewise // на бэке это просто сумма
  :                                     piecewise

  const base = Number(g.base_fee_cents ?? 0)
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