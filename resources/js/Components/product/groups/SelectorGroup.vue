<script setup lang="ts">
import { computed } from 'vue'
import type { SelectorGroup, OptionItem } from '@/types/product-options'

const props = defineProps<{ group: SelectorGroup }>()
// number | number[] | null
const selected = defineModel<number | number[] | null>('selected')

const isRadio = computed(() => props.group.selection_mode === 'single')
const isPercent = computed(() => props.group.pricing_mode === 'percent')

function fmtCents(cents?: number | null) {
  const v = (cents ?? 0) / 100
  return v.toLocaleString('en-US', { style: 'currency', currency: 'USD' })
}

function isChecked(v: OptionItem) {
  return Array.isArray(selected.value)
    ? (selected.value as number[]).includes(v.id)
    : selected.value === v.id
}

function onRadioPick(id: number) {
  selected.value = id
}
function onCheckboxToggle(id: number, checked: boolean) {
  const arr = Array.isArray(selected.value) ? [...(selected.value as number[])] : []
  if (checked) { if (!arr.includes(id)) arr.push(id) }
  else { const i = arr.indexOf(id); if (i>=0) arr.splice(i,1) }
  selected.value = arr
}
</script>

<template>
  <div class="space-y-2">
    <div class="font-medium mb-2">
      {{ group.title }}
      <span v-if="group.is_required" class="text-xs text-muted-foreground">(required)</span>
    </div>

    <label v-for="v in group.values" :key="v.id" class="flex items-center gap-2 cursor-pointer">
      <input
        v-if="isRadio"
        type="radio"
        :name="'g-' + group.id"
        :value="v.id"
        :checked="isChecked(v)"
        @change="onRadioPick(v.id)"
      />
      <input
        v-else
        type="checkbox"
        :checked="isChecked(v)"
        @change="onCheckboxToggle(v.id, ($event.target as HTMLInputElement).checked)"
      />
      <span>{{ v.title }}</span>

      <span class="ml-auto text-sm text-muted-foreground">
        <template v-if="isPercent">
          +{{ v.delta_percent ?? 0 }}%
        </template>
        <template v-else>
          {{ (v.delta_cents ?? 0) >= 0 ? '+' : '' }}{{ fmtCents(v.delta_cents ?? 0) }}
        </template>
      </span>
    </label>
  </div>
</template>