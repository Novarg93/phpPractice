<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { SelectorGroup, OptionItem } from '@/types/product-options'

const props = defineProps<{
  classGroup: SelectorGroup
  slotGroup: SelectorGroup
  affixGroup: SelectorGroup   // selection_mode: 'multi'
  currency?: string
}>()

// Внешние v-model (биндим прямо в selectionByGroup на родителе)
const classId = defineModel<number | null>('classId', { default: null })
const slotId  = defineModel<number | null>('slotId',  { default: null })
const affixIds = defineModel<number[]>('affixIds', { default: [] }) // до 3

const isPercent = (g: SelectorGroup) => g.pricing_mode === 'percent'

function priceLabel(g: SelectorGroup, v: OptionItem) {
  if (isPercent(g)) return `${v.title} (+${v.delta_percent ?? 0}%)`
  const cents = Number(v.delta_cents ?? 0) / 100
  const sign = cents >= 0 ? '+' : ''
  const money = cents.toLocaleString('en-US', { style:'currency', currency: props.currency ?? 'USD' })
  return `${v.title} (${sign}${money})`
}

// helpers for filtering
const classAllowed = (val: OptionItem, cid: number|null) =>
  !cid || !Array.isArray(val.allow_class_value_ids) || val.allow_class_value_ids.includes(cid)

const slotAllowed = (val: OptionItem, sid: number|null) =>
  !sid || !Array.isArray(val.allow_slot_value_ids) || val.allow_slot_value_ids.includes(sid)

// slots filtered by class
const slotsAvailable = computed(() =>
  (props.slotGroup.values ?? []).filter(s => classAllowed(s, classId.value))
)

// affixes filtered by class+slot and excluding already selected ids (но покажем выбранный в своей селекте)
function affixesAvailable(exceptId?: number|null) {
  const chosen = new Set(affixIds.value.filter(Boolean))
  if (exceptId) chosen.delete(exceptId)
  return (props.affixGroup.values ?? []).filter(a =>
    classAllowed(a, classId.value) &&
    slotAllowed(a,  slotId.value)  &&
    !chosen.has(a.id)
  )
}

// локальные три «слота» аффиксов – просто поверх массива
const aff1 = ref<number|null>(null)
const aff2 = ref<number|null>(null)
const aff3 = ref<number|null>(null)

// синхронизация локальных и внешних массивов
function syncFromProp() {
  const src: unknown = affixIds.value
  const arr = Array.isArray(src)
    ? (src as number[]).slice(0, 3)
    : (typeof src === 'number' && Number.isFinite(src) ? [src] : [])
  aff1.value = arr[0] ?? null
  aff2.value = arr[1] ?? null
  aff3.value = arr[2] ?? null
}
function syncToProp() {
  const arr = [aff1.value, aff2.value, aff3.value].filter((x): x is number => !!x)
  // уникальность
  affixIds.value = Array.from(new Set(arr)).slice(0,3)
}
syncFromProp()

watch([aff1, aff2, aff3], syncToProp)
watch(affixIds, syncFromProp)

// при смене класса/слота чистим несовместимые выборы
watch(classId, () => {
  // slot
  if (slotId.value && !classAllowed({ allow_class_value_ids: props.slotGroup.values
      .find(v=>v.id===slotId.value)?.allow_class_value_ids } as any, classId.value)) {
    slotId.value = null
  }
  // affixes
  for (const r of [aff1, aff2, aff3]) {
    const a = props.affixGroup.values.find(v => v.id === r.value)
    if (!a) { r.value = null; continue }
    if (!classAllowed(a, classId.value)) r.value = null
  }
})

watch(slotId, () => {
  for (const r of [aff1, aff2, aff3]) {
    const a = props.affixGroup.values.find(v => v.id === r.value)
    if (!a) { r.value = null; continue }
    if (!slotAllowed(a, slotId.value)) r.value = null
  }
})
</script>

<template>
  <div class="space-y-4">
    <!-- CLASS -->
    <div>
      <div class="font-medium mb-1">
        Class <span v-if="classGroup.is_required" class="text-xs text-muted-foreground">(required)</span>
      </div>
      <select class="w-full border rounded-md px-3 py-2 bg-background"
              :value="classId ?? ''"
              @change="classId = Number((($event.target as HTMLSelectElement).value || NaN)) || null">
        <option v-if="!classGroup.is_required" value="">—</option>
        <option v-for="v in classGroup.values" :key="v.id" :value="v.id">
          {{ priceLabel(classGroup, v) }}
        </option>
      </select>
    </div>

    <!-- SLOT -->
    <div>
      <div class="font-medium mb-1">
        Item slot <span v-if="slotGroup.is_required" class="text-xs text-muted-foreground">(required)</span>
      </div>
      <select class="w-full border rounded-md px-3 py-2 bg-background"
              :value="slotId ?? ''"
              @change="slotId = Number((($event.target as HTMLSelectElement).value || NaN)) || null">
        <option v-if="!slotGroup.is_required" value="">—</option>
        <option v-for="v in slotsAvailable" :key="v.id" :value="v.id">
          {{ priceLabel(slotGroup, v) }}
        </option>
      </select>
    </div>

    <!-- AFFIXES (3 селектора) -->
    <div>
      <div class="font-medium mb-1">Affixes <span class="text-xs text-muted-foreground">(up to 3)</span></div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
        <select class="w-full border rounded-md px-3 py-2 bg-background"
                :value="aff1 ?? ''"
                @change="aff1 = Number((($event.target as HTMLSelectElement).value || NaN)) || null">
          <option value="">—</option>
          <option v-for="v in affixesAvailable(aff1)" :key="v.id" :value="v.id">
            {{ priceLabel(affixGroup, v) }}
          </option>
        </select>

        <select class="w-full border rounded-md px-3 py-2 bg-background"
                :value="aff2 ?? ''"
                @change="aff2 = Number((($event.target as HTMLSelectElement).value || NaN)) || null">
          <option value="">—</option>
          <option v-for="v in affixesAvailable(aff2)" :key="v.id" :value="v.id">
            {{ priceLabel(affixGroup, v) }}
          </option>
        </select>

        <select class="w-full border rounded-md px-3 py-2 bg-background"
                :value="aff3 ?? ''"
                @change="aff3 = Number((($event.target as HTMLSelectElement).value || NaN)) || null">
          <option value="">—</option>
          <option v-for="v in affixesAvailable(aff3)" :key="v.id" :value="v.id">
            {{ priceLabel(affixGroup, v) }}
          </option>
        </select>
      </div>
    </div>
  </div>
</template>