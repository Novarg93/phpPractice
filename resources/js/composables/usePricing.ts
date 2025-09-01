import { computed, type Ref } from 'vue'
import type {
  ProductWithGroups,
  Selection,
  DoubleRangeGroup,
  QtyGroup,
} from '@/types/product-options'

function isPerUnitFlag(v: unknown) {
  return v === true || v === 1 || v === '1'
}

const isRangeSel = (x: unknown): x is { min: number; max: number } =>
  !!x && typeof x === 'object' && 'min' in (x as any) && 'max' in (x as any)

function priceRangeFlat(g: DoubleRangeGroup, sel: { min: number; max: number }) {
  const step = g.slider_step || 1
  const baseMin = g.slider_min
  const snap = (val: number) => val - ((val - baseMin) % step)
  const a = snap(Math.min(g.slider_max, Math.max(g.slider_min, sel.min)))
  const b = snap(Math.min(g.slider_max, Math.max(g.slider_min, sel.max)))
  const min = Math.min(a, b)
  const max = Math.max(a, b)
  const span = Math.max(0, max - min)
  return Number(g.unit_price_cents ?? 0) * span
}

function priceRangeTiered(g: DoubleRangeGroup, sel: { min: number; max: number }) {
  const a = Math.min(sel.min, sel.max)
  const b = Math.max(sel.min, sel.max)
  const spanTotal = Math.max(0, b - a)
  const tiers = (g.tiers ?? []).slice().sort((x, y) => x.from - y.from)

  let piecewise = 0, highestUnit = 0, weightedSum = 0
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
  const variable =
    strategy === 'highest_tier_only' ? highestUnit * spanTotal
    : strategy === 'weighted_average' ? Math.round((spanTotal > 0 ? weightedSum / spanTotal : 0) * spanTotal)
    : piecewise

  return Number(g.base_fee_cents ?? 0) + variable
}

export function usePricing(
  product: ProductWithGroups,
  selectionByGroup: Ref<Record<number, Selection>>
) {
  const optionsPerUnitCents = computed(() => {
    let sum = 0
    ;(product.option_groups ?? []).forEach((g) => {
      if (g.type === 'radio_additive') {
        const sel = selectionByGroup.value[g.id]
        if (typeof sel === 'number') {
          const v = g.values.find(x => x.id === sel)
          if (v && isPerUnitFlag(g.multiply_by_qty)) sum += Number(v.price_delta_cents || 0)
        }
      } else if (g.type === 'checkbox_additive') {
        const sel = selectionByGroup.value[g.id]
        if (Array.isArray(sel)) {
          sel.forEach(id => {
            const v = g.values.find(x => x.id === id)
            if (v && isPerUnitFlag(g.multiply_by_qty)) sum += Number(v.price_delta_cents || 0)
          })
        }
      }
    })
    return sum
  })

  const optionsPerOrderCents = computed(() => {
    let sum = 0
    ;(product.option_groups ?? []).forEach((g) => {
      if (g.type === 'radio_additive') {
        const sel = selectionByGroup.value[g.id]
        if (typeof sel === 'number') {
          const v = g.values.find(x => x.id === sel)
          if (v && !isPerUnitFlag(g.multiply_by_qty)) sum += Number(v.price_delta_cents || 0)
        }
      } else if (g.type === 'checkbox_additive') {
        const sel = selectionByGroup.value[g.id]
        if (Array.isArray(sel)) {
          sel.forEach(id => {
            const v = g.values.find(x => x.id === id)
            if (v && !isPerUnitFlag(g.multiply_by_qty)) sum += Number(v.price_delta_cents || 0)
          })
        }
      }
    })
    return sum
  })

  const totalRangePerUnitCents = computed(() => {
    let sum = 0
    ;(product.option_groups ?? []).forEach((g) => {
      if (g.type !== 'double_range_slider') return
      const sel = selectionByGroup.value[g.id]
      if (!isRangeSel(sel)) return
      sum += g.pricing_mode === 'tiered'
        ? priceRangeTiered(g, sel)
        : priceRangeFlat(g, sel)
    })
    return sum
  })

  const percentPerUnitFactor = computed(() => {
    let f = 1
    ;(product.option_groups ?? []).forEach((g) => {
      if (g.type !== 'radio_percent' && g.type !== 'checkbox_percent') return
      if (!isPerUnitFlag(g.multiply_by_qty)) return

      const sel = selectionByGroup.value[g.id]
      const ids = g.type === 'radio_percent'
        ? (typeof sel === 'number' ? [sel] : [])
        : (Array.isArray(sel) ? sel : [])

      ids.forEach((id) => {
        const v = g.values.find(x => x.id === id)
        if (v) f *= (1 + Number(v.value_percent ?? 0) / 100)
      })
    })
    return f
  })

  const percentPerOrderFactor = computed(() => {
    let f = 1
    ;(product.option_groups ?? []).forEach((g) => {
      if (g.type !== 'radio_percent' && g.type !== 'checkbox_percent') return
      if (isPerUnitFlag(g.multiply_by_qty)) return

      const sel = selectionByGroup.value[g.id]
      const ids = g.type === 'radio_percent'
        ? (typeof sel === 'number' ? [sel] : [])
        : (Array.isArray(sel) ? sel : [])

      ids.forEach((id) => {
        const v = g.values.find(x => x.id === id)
        if (v) f *= (1 + Number(v.value_percent ?? 0) / 100)
      })
    })
    return f
  })

  const unitCents = computed(() => {
    const base = Number(product.price_cents || 0) + optionsPerUnitCents.value + totalRangePerUnitCents.value
    return Math.round(base * percentPerUnitFactor.value)
  })

  const totalCents = computed(() => {
    const qGroup = (product.option_groups ?? []).find(g => g.type === 'quantity_slider') as QtyGroup | undefined
    const qSel = qGroup ? selectionByGroup.value[qGroup.id] : undefined
    const q = typeof qSel === 'number' ? qSel : 1
    const subtotal = unitCents.value * q
    return Math.round((subtotal + optionsPerOrderCents.value) * percentPerOrderFactor.value)
  })

  return { unitCents, totalCents }
}