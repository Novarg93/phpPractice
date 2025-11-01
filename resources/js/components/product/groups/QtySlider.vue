<!-- src/Components/product/groups/QtySlider.vue -->
<script setup lang="ts">
import type { QtyGroup } from '@/types/product-options'

const props = defineProps<{ group: QtyGroup }>()
const selected = defineModel<number>('selected')

const min = Number(props.group.qty_min ?? 1)
const max = Number(props.group.qty_max ?? 1)
const step = Number(props.group.qty_step ?? 1)

function dec() { selected.value = Math.max(min, Number(selected.value || min) - step) }
function inc() { selected.value = Math.min(max, Number(selected.value || min) + step) }
</script>

<template>
  <div class="space-y-3">
    <div class="font-medium">
      {{ group.title }}
      <span v-if="group.is_required" class="text-xs text-muted-foreground">(required)</span>
    </div>

    <div class="text-sm text-muted-foreground">
      {{ selected }} (min: {{ min }}, max: {{ max }}, step: {{ step }})
    </div>

    <input type="range" class="w-full" :min="min" :max="max" :step="step" v-model.number="selected" />

    <div class="mt-2 flex items-center gap-2">
      <input type="number" class="w-24 bg-background border rounded px-2 py-1"
             :min="min" :max="max" :step="step" v-model.number="selected" />
      <button class="px-2 py-1 border rounded" @click="dec">-</button>
      <button class="px-2 py-1 border rounded" @click="inc">+</button>
    </div>
  </div>
</template>