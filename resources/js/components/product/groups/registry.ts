import { markRaw, defineAsyncComponent } from 'vue'
import SelectorGroup from './SelectorGroup.vue'
import QtySlider from './QtySlider.vue'
import DoubleRange from './DoubleRange.vue'
import BundleBuilder from './BundleBuilder.vue'

// создаём ОДИН раз
const SelectorDropdownAsync = defineAsyncComponent(() => import('./SelectorDropdown.vue'))

export const groupRegistry = markRaw({
  selector: SelectorGroup,
  quantity_slider: QtySlider,
  double_range_slider: DoubleRange,
   bundle: BundleBuilder,
} as const)

export function resolveGroupComponent(type: string, group?: any) {
  if (type === 'selector') {
    if (group?.ui_variant === 'dropdown' && group?.selection_mode === 'single') {
      return SelectorDropdownAsync               // ← возвращаем СТАТИЧНУЮ ссылку
    }
    return SelectorGroup
  }
  return (groupRegistry as any)[type] ?? null
}