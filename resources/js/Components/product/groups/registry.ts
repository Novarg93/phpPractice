import { markRaw, defineAsyncComponent, type Component } from 'vue'
import QtySlider from './QtySlider.vue'
import DoubleRange from './DoubleRange.vue'

export const groupRegistry: Record<string, Component> = {
  selector:            defineAsyncComponent(() => import('./Selector.vue')),
  quantity_slider:     markRaw(QtySlider),
  double_range_slider: markRaw(DoubleRange),
} as const

export function resolveGroupComponent(type: string) {
  return groupRegistry[type] ?? null
}