import { markRaw } from 'vue'
import SelectorGroup from './SelectorGroup.vue'
import QtySlider from './QtySlider.vue'
import DoubleRange from './DoubleRange.vue'

export const groupRegistry = markRaw({
  selector: SelectorGroup,
  quantity_slider: QtySlider,
  double_range_slider: DoubleRange,
} as const)

export function resolveGroupComponent(type: string) {
  return (groupRegistry as any)[type] ?? null
}