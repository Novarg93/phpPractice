<script setup lang="ts">
import { computed, watchEffect } from 'vue'

type Value = {
  id: number
  title: string
  is_default?: boolean
  // одно из полей используется в зависимости от pricing_mode
  delta_cents?: number | null
  delta_percent?: number | null
}

type Group = {
  id: number
  title: string
  is_required: boolean
  selection_mode: 'single' | 'multi' | string
  pricing_mode: 'absolute' | 'percent' | string
  values: Value[]
}

const props = defineProps<{
  group: Group
  selected: number | number[] | null
}>()

const emit = defineEmits<{
  (e: 'update:selected', v: number | number[] | null): void
}>()

const isSingle = computed(() => props.group.selection_mode !== 'multi')
const nameAttr = computed(() => `g-${props.group.id}`)

watchEffect(() => {
  // проставляем default, если ничего не выбрано
  const vals = props.group.values ?? []
  const defIds = vals.filter(v => v.is_default).map(v => v.id)

  if (isSingle.value) {
    if (props.selected === undefined || props.selected === null) {
      emit('update:selected', defIds.length ? defIds[0] : null)
    }
  } else {
    const arr = Array.isArray(props.selected) ? props.selected : []
    if (arr.length === 0 && defIds.length > 0) {
      emit('update:selected', defIds)
    }
  }
})

function fmtMoney(cents: number) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' })
    .format((cents || 0) / 100)
}

function rightPrice(v: Value) {
  if (props.group.pricing_mode === 'percent') {
    const p = Number(v.delta_percent ?? 0)
    // показываем +0% тоже, чтобы сетка не «прыгала»
    return `+${p}%`
  }
  const add = Number(v.delta_cents ?? 0)
  return `+${fmtMoney(add)}`
}

function onSingleChange(id: number) {
  emit('update:selected', id)
}

function onMultiToggle(id: number, checked: boolean) {
  const current = Array.isArray(props.selected) ? [...props.selected] : []
  if (checked) {
    if (!current.includes(id)) current.push(id)
  } else {
    const i = current.indexOf(id)
    if (i >= 0) current.splice(i, 1)
  }
  emit('update:selected', current)
}

function isChecked(id: number) {
  return Array.isArray(props.selected)
    ? props.selected.includes(id)
    : props.selected === id
}
</script>

<template>
  <div>
    <!-- заголовок + (required) -->
    <div class="font-medium mb-2">
      {{ group.title }}
      <span v-if="group.is_required" class="text-xs text-muted-foreground">(required)</span>
    </div>

    <!-- SINGLE (radio) -->
    <div v-if="isSingle" class="space-y-2">
      <label
        v-for="v in group.values"
        :key="v.id"
        class="flex items-center gap-2 cursor-pointer"
      >
        <input
          type="radio"
          :name="nameAttr"
          class="accent-primary"
          :value="v.id"
          :checked="isChecked(v.id)"
          @change="onSingleChange(v.id)"
        />
        <span>{{ v.title }}</span>
        <span class="ml-auto text-sm text-muted-foreground">{{ rightPrice(v) }}</span>
      </label>
    </div>

    <!-- MULTI (checkbox) -->
    <div v-else class="space-y-2">
      <label
        v-for="v in group.values"
        :key="v.id"
        class="flex items-center gap-2 cursor-pointer"
      >
        <input
          type="checkbox"
          class="accent-primary"
          :value="v.id"
          :checked="isChecked(v.id)"
          @change="onMultiToggle(v.id, ($event.target as HTMLInputElement).checked)"
        />
        <span>{{ v.title }}</span>
        <span class="ml-auto text-sm text-muted-foreground">{{ rightPrice(v) }}</span>
      </label>
    </div>
  </div>
</template>