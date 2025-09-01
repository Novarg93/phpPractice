import { ref, computed } from 'vue'
import type {
  ProductWithGroups, AnyGroup, SelectorGroup, QtyGroup, DoubleRangeGroup, Selection
} from '@/types/product-options'

export function useProductOptions(product: ProductWithGroups) {
  const selectionByGroup = ref<Record<number, Selection>>({})

  const isNum = (x: unknown): x is number => typeof x === 'number' && Number.isFinite(x)
  const isIdArray = (x: unknown): x is number[] => Array.isArray(x) && x.every(n => typeof n === 'number')
  const isRangeSel = (x: unknown): x is { min: number; max: number } =>
    !!x && typeof x === 'object' && 'min' in (x as any) && 'max' in (x as any)

  // defaults
  ;(product.option_groups ?? []).forEach((g: AnyGroup) => {
    if (g.type === 'selector') {
      if (g.selection_mode === 'single') {
        const def = g.values.find(v => v.is_default) ?? g.values[0]
        selectionByGroup.value[g.id] = def ? def.id : null
      } else {
        selectionByGroup.value[g.id] = []
      }
    } else if (g.type === 'quantity_slider') {
      const min = Number(g.qty_min ?? 1)
      const step = Number(g.qty_step ?? 1)
      const def = Number(g.qty_default ?? min)
      const clamped = Math.max(min, Math.min(Number(g.qty_max ?? def), def))
      selectionByGroup.value[g.id] = clamped - ((clamped - min) % step)
    } else if (g.type === 'double_range_slider') {
      const a = Number(g.range_default_min ?? g.slider_min)
      const b = Number(g.range_default_max ?? g.slider_max)
      selectionByGroup.value[g.id] = { min: Math.min(a,b), max: Math.max(a,b) }
    }
  })

  const qtyGroup = computed((): QtyGroup | null =>
    (product.option_groups ?? []).find((g): g is QtyGroup => g.type === 'quantity_slider') ?? null
  )

  function buildAddToCartPayload() {
    const option_value_ids: number[] = []
    const range_options: Array<{ option_group_id: number; selected_min: number; selected_max: number }> = []
    let qty = 1

    ;(product.option_groups ?? []).forEach((g) => {
      const sel = selectionByGroup.value[g.id]

      if (g.type === 'selector') {
        if (g.selection_mode === 'single') {
          if (isNum(sel)) option_value_ids.push(sel)
        } else {
          if (isIdArray(sel)) option_value_ids.push(...sel)
        }
      } else if (g.type === 'quantity_slider') {
        if (isNum(sel)) qty = sel
      } else if (g.type === 'double_range_slider') {
        if (isRangeSel(sel)) {
          range_options.push({ option_group_id: g.id, selected_min: sel.min, selected_max: sel.max })
        }
      }
    })

    return { product_id: product.id, option_value_ids, qty, range_options }
  }

  return { selectionByGroup, qtyGroup, buildAddToCartPayload }
}