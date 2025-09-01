import { ref, computed } from 'vue'
import type { ProductWithGroups, AnyGroup, ChoiceGroup, QtyGroup, DoubleRangeGroup, Selection } from '@/types/product-options'

export function useProductOptions(product: ProductWithGroups) {
  const selectionByGroup = ref<Record<number, Selection>>({})

  const isNum = (x: unknown): x is number => typeof x === 'number' && Number.isFinite(x)
  const isIdArray = (x: unknown): x is number[] => Array.isArray(x) && x.every(n => typeof n === 'number')
  const isRangeSel = (x: unknown): x is { min: number; max: number } =>
    !!x && typeof x === 'object' && 'min' in (x as any) && 'max' in (x as any)

  function snapQty(g: QtyGroup, raw: number) {
    const min = Number(g.qty_min ?? 1)
    const max = Number(g.qty_max ?? 1)
    const step = Number(g.qty_step ?? 1)
    const clamped = Math.min(max, Math.max(min, Number(raw) || min))
    return clamped - ((clamped - min) % step)
  }

  function sanitizeRange(g: DoubleRangeGroup, minVal: number, maxVal: number) {
    const min = Number(g.slider_min)
    const max = Number(g.slider_max)
    const step = Number(g.slider_step || 1)
    const snap = (v: number) => v - ((v - min) % step)
    let a = snap(Math.min(max, Math.max(min, Number(minVal))))
    let b = snap(Math.min(max, Math.max(min, Number(maxVal))))
    if (a > b) [a, b] = [b, a]
    return { a, b }
  }

  const qtyGroup = computed(
    (): QtyGroup | null =>
      (product.option_groups ?? []).find((g): g is QtyGroup => g.type === 'quantity_slider') ?? null
  )

  // init (сразу)
  ;(product.option_groups ?? []).forEach((g: AnyGroup) => {
    if (g.type === 'radio_additive' || g.type === 'radio_percent') {
      const def = g.values.find(v => v.is_default) ?? g.values[0]
      selectionByGroup.value[g.id] = def ? def.id : null
    } else if (g.type === 'checkbox_additive' || g.type === 'checkbox_percent') {
      selectionByGroup.value[g.id] = []
    } else if (g.type === 'quantity_slider') {
      const def = Number(g.qty_default ?? g.qty_min ?? 1)
      selectionByGroup.value[g.id] = snapQty(g, def)
    } else if (g.type === 'double_range_slider') {
      const defMin = Number(g.range_default_min ?? g.slider_min)
      const defMax = Number(g.range_default_max ?? g.slider_max)
      const { a, b } = sanitizeRange(g, defMin, defMax)
      selectionByGroup.value[g.id] = { min: a, max: b }
    }
  })

  function buildAddToCartPayload() {
    const option_value_ids: number[] = []
    const range_options: Array<{ option_group_id: number; selected_min: number; selected_max: number }> = []
    let qty = 1

    ;(product.option_groups ?? []).forEach((g) => {
      const sel = selectionByGroup.value[g.id]
      switch (g.type) {
        case 'radio_additive':
        case 'radio_percent':
          if (isNum(sel)) option_value_ids.push(sel)
          break
        case 'checkbox_additive':
        case 'checkbox_percent':
          if (isIdArray(sel)) option_value_ids.push(...sel)
          break
        case 'quantity_slider':
          if (isNum(sel)) qty = sel
          break
        case 'double_range_slider':
          if (isRangeSel(sel)) {
            range_options.push({ option_group_id: g.id, selected_min: sel.min, selected_max: sel.max })
          }
          break
      }
    })

    return { product_id: product.id, option_value_ids, qty, range_options }
  }

  return { selectionByGroup, qtyGroup, buildAddToCartPayload }
}