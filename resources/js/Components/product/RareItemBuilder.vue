<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { SelectorGroup, OptionItem } from '@/types/product-options'

const props = defineProps<{
  classGroup: SelectorGroup
  slotGroup: SelectorGroup
  affixGroup: SelectorGroup   // selection_mode: 'multi'
  currency?: string
  gaLimit?: number
}>()

// Внешние v-model (биндим прямо в selectionByGroup на родителе)
const classId = defineModel<number | null>('classId', { default: null })
const slotId = defineModel<number | null>('slotId', { default: null })
const affixIds = defineModel<number[]>('affixIds', { default: [] }) // до 3
const affixGaIds = defineModel<number[]>('affixGaIds', { default: [] })
const gaCheckedCount = computed(() => Number(ga1.value) + Number(ga2.value) + Number(ga3.value))


const gaLimitSafe = computed(() => Math.min(3, Math.max(0, Number(props.gaLimit ?? 0))))
const ga1 = ref(false), ga2 = ref(false), ga3 = ref(false)

// если аффикс сняли — снимаем и его GA
watch([affixIds], () => {
  const [a1, a2, a3] = affixIds.value
  if (!a1) ga1.value = false
  if (!a2) ga2.value = false
  if (!a3) ga3.value = false
}, { deep: true })

// жёсткий лимит: если превысили — отщёлкиваем последний включённый
watch([ga1, ga2, ga3, gaLimitSafe], () => {
  const flags = [ga1, ga2, ga3]
  const onIdx = flags.map((r, i) => r.value ? i : -1).filter(i => i >= 0)
  while (onIdx.length > gaLimitSafe.value) {
    const last = onIdx.pop()!
    flags[last].value = false
  }
})

// реакция на смену лимита
watch(gaLimitSafe, (n) => {
  const [a1, a2, a3] = affixIds.value
  if (n === 0) {
    ga1.value = ga2.value = ga3.value = false
  } else if (n === 3) {
    // все выбранные аффиксы должны быть GA
    ga1.value = !!a1
    ga2.value = !!a2
    ga3.value = !!a3
  }
})

// наружу отдаём только ids, помеченные как GA
watch([affixIds, ga1, ga2, ga3], () => {
  const [a1, a2, a3] = affixIds.value
  const ids = [
    (a1 && ga1.value) ? a1 : null,
    (a2 && ga2.value) ? a2 : null,
    (a3 && ga3.value) ? a3 : null,
  ].filter(Boolean) as number[]
  affixGaIds.value = ids
})


const isPercent = (g: SelectorGroup) => g.pricing_mode === 'percent'

function priceLabel(g: SelectorGroup, v: OptionItem) {
  if (isPercent(g)) return `${v.title} (+${v.delta_percent ?? 0}%)`
  const cents = Number(v.delta_cents ?? 0) / 100
  const sign = cents >= 0 ? '+' : ''
  const money = cents.toLocaleString('en-US', { style: 'currency', currency: props.currency ?? 'USD' })
  return `${v.title} (${sign}${money})`
}

// helpers for filtering
const classAllowed = (val: OptionItem, cid: number | null) =>
  !cid || !Array.isArray(val.allow_class_value_ids) || val.allow_class_value_ids.includes(cid)

const slotAllowed = (val: OptionItem, sid: number | null) =>
  !sid || !Array.isArray(val.allow_slot_value_ids) || val.allow_slot_value_ids.includes(sid)

// slots filtered by class
const slotsAvailable = computed(() =>
  (props.slotGroup.values ?? []).filter(s => classAllowed(s, classId.value))
)

// affixes filtered by class+slot and excluding already selected ids (но покажем выбранный в своей селекте)
function affixesAvailable(exceptId?: number | null) {
  const chosen = new Set(affixIds.value.filter(Boolean))
  if (exceptId) chosen.delete(exceptId)
  return (props.affixGroup.values ?? []).filter(a =>
    classAllowed(a, classId.value) &&
    slotAllowed(a, slotId.value) &&
    !chosen.has(a.id)
  )
}

// локальные три «слота» аффиксов – просто поверх массива
const aff1 = ref<number | null>(null)
const aff2 = ref<number | null>(null)
const aff3 = ref<number | null>(null)

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
  affixIds.value = Array.from(new Set(arr)).slice(0, 3)
}
syncFromProp()

watch([aff1, aff2, aff3], syncToProp)
watch(affixIds, syncFromProp)

// при смене класса/слота чистим несовместимые выборы
watch(classId, () => {
  // slot
  if (slotId.value && !classAllowed({
    allow_class_value_ids: props.slotGroup.values
      .find(v => v.id === slotId.value)?.allow_class_value_ids
  } as any, classId.value)) {
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
      <select class="w-full border rounded-md px-3 py-2 bg-background" :value="classId ?? ''"
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
      <select class="w-full border rounded-md px-3 py-2 bg-background" :value="slotId ?? ''"
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
        <div class="flex gap-2 items-center">

          <select class="w-full border rounded-md px-3 py-2 bg-background" :value="aff1 ?? ''"
            @change="aff1 = Number((($event.target as HTMLSelectElement).value || NaN)) || null">
            <option value="">—</option>
            <option v-for="v in affixesAvailable(aff1)" :key="v.id" :value="v.id">
              {{ priceLabel(affixGroup, v) }}
            </option>
          </select>
          <label class="flex items-center gap-1 text-xs">
            <input type="checkbox" :checked="ga1" :disabled="gaLimitSafe === 0
              || gaLimitSafe === 3        // как и раньше: при 3 всё авто-GA и залочено — ты писал, что так ок
              || !aff1
              || (gaCheckedCount >= gaLimitSafe && !ga1)  // если лимит добит и этот чекбокс сейчас OFF — не даём включать
              " @change="ga1 = !ga1" />
            Greater
          </label>
        </div>

        <div class="flex gap-2 items-center">

          <select class="w-full border rounded-md px-3 py-2 bg-background" :value="aff2 ?? ''"
            @change="aff2 = Number((($event.target as HTMLSelectElement).value || NaN)) || null">
            <option value="">—</option>
            <option v-for="v in affixesAvailable(aff2)" :key="v.id" :value="v.id">
              {{ priceLabel(affixGroup, v) }}
            </option>
          </select>
          <label class="flex items-center gap-1 text-xs">
            <input type="checkbox" :checked="ga2" :disabled="gaLimitSafe === 0
              || gaLimitSafe === 3
              || !aff2
              || (gaCheckedCount >= gaLimitSafe && !ga2)
              " @change="ga2 = !ga2" />
            Greater
          </label>
        </div>

        <div class="flex gap-2 items-center">

          <select class="w-full border rounded-md px-3 py-2 bg-background" :value="aff3 ?? ''"
            @change="aff3 = Number((($event.target as HTMLSelectElement).value || NaN)) || null">
            <option value="">—</option>
            <option v-for="v in affixesAvailable(aff3)" :key="v.id" :value="v.id">
              {{ priceLabel(affixGroup, v) }}
            </option>
          </select>
          <label class="flex items-center gap-1 text-xs">
            <input type="checkbox" :checked="ga3" :disabled="gaLimitSafe === 0
              || gaLimitSafe === 3
              || !aff3
              || (gaCheckedCount >= gaLimitSafe && !ga3)
              " @change="ga3 = !ga3" />
            Greater
          </label>
        </div>
      </div>
    </div>
  </div>
</template>