<script setup lang="ts">
import { onBeforeMount, computed } from 'vue'
import type { DoubleRangeGroup } from '@/types/product-options'

const props = defineProps<{ group: DoubleRangeGroup }>()
const selected = defineModel<{ min: number; max: number }>('selected')

onBeforeMount(() => {
  if (!selected.value) {
    const a = Number(props.group.range_default_min ?? props.group.slider_min)
    const b = Number(props.group.range_default_max ?? props.group.slider_max)
    selected.value = { min: Math.min(a, b), max: Math.max(a, b) }
  }
})

const min = Number(props.group.slider_min)
const max = Number(props.group.slider_max)
const step = Number(props.group.slider_step || 1)

function clamp(v: number, a: number, b: number) { return Math.min(b, Math.max(a, v)) }
function snap(val: number, base: number, s: number) { return val - ((val - base) % s) }
function pct(value: number) { const span = max - min; return span <= 0 ? 0 : ((value - min) / span) * 100 }

function setMin(v: number) {
  const a = snap(clamp(v, min, max), min, step)
  const b = snap(clamp((selected.value?.max ?? max), min, max), min, step)
  selected.value = { min: Math.min(a, b), max: Math.max(a, b) }
}
function setMax(v: number) {
  const a = snap(clamp((selected.value?.min ?? min), min, max), min, step)
  const b = snap(clamp(v, min, max), min, step)
  selected.value = { min: Math.min(a, b), max: Math.max(a, b) }
}

const hasTiers = computed(() => props.group.pricing_mode === 'tiered' && (props.group.tiers?.length ?? 0) > 0)
</script>

<template>
  <div class="space-y-3" v-if="selected">
    <div class="flex items-center justify-between">
      <div class="font-medium">{{ group.title }}</div>
      <div class="text-sm text-muted-foreground">
        {{ selected.min }}–{{ selected.max }} (min: {{ min }}, max: {{ max }}, step: {{ step }})
      </div>
    </div>

    <div class="relative h-2 rounded bg-muted overflow-hidden">
      <div class="absolute top-0 h-2 bg-primary/50"
           :style="{ left: pct(selected.min) + '%', width: (pct(selected.max) - pct(selected.min)) + '%' }" />
      <template v-if="hasTiers">
        <div v-for="(t, i) in group.tiers" :key="'from-'+i"
             class="absolute top-[-6px] bottom-[-6px] w-px bg-foreground/40" :style="{ left: pct(t.from) + '%' }" />
        <div v-for="(t, i) in group.tiers" :key="'to-'+i"
             class="absolute top-[-6px] bottom-[-6px] w-px bg-foreground/40" :style="{ left: pct(t.to) + '%' }" />
      </template>
    </div>

    <div class="relative">
      <input type="range" class="w-full appearance-none bg-transparent absolute top-0 -translate-y-6"
             :min="min" :max="max" :step="step" :value="selected.min"
             @input="setMin(Number(($event.target as HTMLInputElement).value))" style="z-index: 20;" />
      <input type="range" class="w-full appearance-none bg-transparent absolute top-0 -translate-y-8"
             :min="min" :max="max" :step="step" :value="selected.max"
             @input="setMax(Number(($event.target as HTMLInputElement).value))" style="z-index: 20;" />
    </div>

    <div class="mt-2 flex items-center gap-2">
      <div class="flex items-center gap-2">
        <label class="text-sm text-muted-foreground">From</label>
        <input type="number" class="w-24 bg-background border rounded px-2 py-1"
               :min="min" :max="max" :step="step" :value="selected.min"
               @input="setMin(Number(($event.target as HTMLInputElement).value))" />
      </div>

      <div class="flex items-center gap-2">
        <label class="text-sm text-muted-foreground">To</label>
        <input type="number" class="w-24 bg-background border rounded px-2 py-1"
               :min="min" :max="max" :step="step" :value="selected.max"
               @input="setMax(Number(($event.target as HTMLInputElement).value))" />
      </div>
    </div>
  </div>
</template>