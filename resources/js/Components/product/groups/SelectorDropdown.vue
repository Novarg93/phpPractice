<script setup lang="ts">
import { onMounted, computed } from 'vue'
import type { SelectorGroup } from '@/types/product-options'

const props = defineProps<{ group: SelectorGroup }>()
const selected = defineModel<number | null>('selected')

const isPercent = computed(() => props.group.pricing_mode === 'percent')
const required = computed(() => !!props.group.is_required)

// формат цены
function label(v: any) {
  if (isPercent.value) return `${v.title} (+${v.delta_percent ?? 0}%)`
  const cents = Number(v.delta_cents ?? 0) / 100
  const money = cents.toLocaleString('en-US', { style: 'currency', currency: 'USD' })
  const sign = cents >= 0 ? '+' : ''
  return `${v.title} (${sign}${money})`
}

// выбрать дефолт, если ничего не выбрано
onMounted(() => {
  if (selected.value == null) {
    const def = (props.group.values ?? []).find(v => v.is_default)
    if (def) selected.value = def.id
    else if (required.value && props.group.values?.length) selected.value = props.group.values[0].id
  }
})
</script>

<template>
  <div class="space-y-2">
    <div class="font-medium mb-1">
      {{ group.title }}
      <span v-if="required" class="text-xs text-muted-foreground">(required)</span>
    </div>

    <select
      class="w-full border rounded-md px-3 py-2 bg-background"
      :value="selected ?? ''"
      @change="selected = Number((($event.target as HTMLSelectElement).value || NaN)) || null"
    >
      <option v-if="!required" value="">—</option>
      <option v-for="v in group.values" :key="v.id" :value="v.id">
        {{ label(v) }}
      </option>
    </select>
  </div>
</template>