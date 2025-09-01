import { markRaw } from 'vue'
import ChoiceGroup from './ChoiceGroup.vue'
import QtySlider from './QtySlider.vue'
import DoubleRange from './DoubleRange.vue'

export const groupRegistry = markRaw({
  radio_additive: ChoiceGroup,
  checkbox_additive: ChoiceGroup,
  radio_percent: ChoiceGroup,
  checkbox_percent: ChoiceGroup,
  quantity_slider: QtySlider,
  double_range_slider: DoubleRange,
} as const)

export function resolveGroupComponent(type: string) {
  return (groupRegistry as any)[type] ?? null
}