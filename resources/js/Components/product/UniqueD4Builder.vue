<script setup lang="ts">
import { computed, watch } from 'vue'
import type { SelectorGroup, OptionItem } from '@/types/product-options'

const props = defineProps<{
  gaGroup: SelectorGroup           // code = 'ga' (dropdown single)
  statsGroup: SelectorGroup        // code = 'unique_d4_stats' (multi)
}>()

// наружные v-model'и (родитель хранит реальное состояние)
const gaSelected = defineModel<number | null>('gaSelected', { default: null })
const statsSelected = defineModel<number[]>('statsSelected', { default: [] })

function clamp(n: number, min = 0, max = 4) {
  const x = Number(n)
  return Math.min(max, Math.max(min, Number.isFinite(x) ? x : 0))
}

/* ========== GA (селект, single) ========== */
// безопасный proxy: если вдруг прилетел массив — берём первый id
const gaSelectedSafe = computed<number | null>({
  get: () => {
    const v = gaSelected.value as any
    return Array.isArray(v) ? (v[0] ?? null) : (typeof v === 'number' ? v : null)
  },
  set: (n) => { gaSelected.value = (n == null ? null : Number(n)) } // ← всегда число
})

// вычисляем лимит статов по выбранному GA (0..4)
const gaLimit = computed<number>(() => {
  const id = gaSelectedSafe.value
  const opt = props.gaGroup.values?.find(v => v.id === id)
  if (!opt) return 0

  const fromMeta = Number((opt as any)?.meta?.ga_count)
  if (Number.isFinite(fromMeta)) return clamp(fromMeta, 0, 4)

  const m = String(opt.title ?? '').match(/(\d)/)
  return clamp(m ? Number(m[1]) : 0, 0, 4)
})

function labelGA(v: OptionItem) {
  if (props.gaGroup.pricing_mode === 'percent') {
    return `${v.title} ${v.delta_percent ? `(+${v.delta_percent}%)` : ''}`
  }
  const cents = Number(v.delta_cents ?? 0) / 100
  const money = cents.toLocaleString('en-US', { style: 'currency', currency: 'USD' })
  const sign = cents >= 0 ? '+' : ''
  return `${v.title} ${v.title === 'Non GA' ? '' : `(${sign}${money})`}`
}

/** сразу в setup ставим дефолтный Non-GA (или первый пункт), чтобы поле не было пустым */
function computeDefaultGaId(): number | null {
  const byMeta = props.gaGroup.values?.find(v => Number((v as any)?.meta?.ga_count) === 0)
  const byTitle = props.gaGroup.values?.find(v =>
    /(^|\s)non[-\s]?ga($|\s)|\b0\s*ga\b/i.test(String(v.title ?? ''))
  )
  return (byMeta ?? byTitle ?? props.gaGroup.values?.[0])?.id ?? null
}
if (gaSelectedSafe.value == null) {
  gaSelectedSafe.value = computeDefaultGaId()
}

/* ========== Статы (чекбоксы, multi) ========== */

// когда меняется лимит — приводим массив статов к допустимому виду
watch(gaLimit, (n) => {
  if (n === 0) {
    statsSelected.value = []
  } else if (n === 4) {
    // выбираем все 4
    statsSelected.value = props.statsGroup.values?.map(v => v.id) ?? []
  } else {
    if ((statsSelected.value?.length ?? 0) > n) {
      statsSelected.value = statsSelected.value.slice(0, n)
    }
  }
})

const statsCount = computed(() => statsSelected.value?.length ?? 0)
const statsExact = computed(() => statsCount.value === gaLimit.value)

// дизейблим лишние чекбоксы, когда достигли лимита
const disabledSet = computed<Set<number>>(() => {
  const set = new Set<number>()
  const n = gaLimit.value
  const cur = statsSelected.value?.length ?? 0

  if (n === 0 || n === 4) {
    for (const v of props.statsGroup.values ?? []) set.add(v.id)
    return set
  }
  if (cur >= n) {
    for (const v of props.statsGroup.values ?? []) {
      if (!statsSelected.value.includes(v.id)) set.add(v.id)
    }
  }
  return set
})

function toggleStat(id: number, checked: boolean) {
  const n = gaLimit.value
  const set = new Set<number>(statsSelected.value)
  if (checked) set.add(id); else set.delete(id)
  statsSelected.value = Array.from(set).slice(0, n)
}
</script>

<template>
  <div class="space-y-4">
    <!-- GA select -->
    <div>
      <div class="font-medium mb-1">Greater Affixes</div>
      <select class="w-full border rounded-md px-3 py-2 bg-background" v-model.number="gaSelectedSafe">
        <option v-for="v in gaGroup.values" :key="v.id" :value="v.id">
          {{ labelGA(v) }}
        </option>
      </select>
    </div>

    <!-- 4 характеристики -->
    <div>
      <div class="font-medium mb-1 flex items-center justify-between">
        <span>Item attributes</span>
        <span class="text-xs" :class="statsExact ? 'text-muted-foreground' : 'text-red-600'">
          {{ statsCount }}/{{ gaLimit }} chosen
          <template v-if="!statsExact && gaLimit > 0"> — choose exactly {{ gaLimit }}</template>
          <template v-else-if="!statsExact && gaLimit === 0"> — do not choose GA</template>
        </span>
      </div>

      <div class="space-y-1">
        <label v-for="v in statsGroup.values" :key="v.id" class="flex items-center gap-2">
          <input type="checkbox" :checked="statsSelected.includes(v.id)" :disabled="disabledSet.has(v.id)"
            @change="toggleStat(v.id, ($event.target as HTMLInputElement).checked)" />
          <span>{{ v.title }}</span>
        </label>
      </div>
    </div>
  </div>
</template>