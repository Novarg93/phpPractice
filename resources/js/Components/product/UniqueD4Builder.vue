<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import type { SelectorGroup, OptionItem } from '@/types/product-options'

const props = defineProps<{
  gaGroup: SelectorGroup           // code = 'ga' (dropdown single)
  statsGroup: SelectorGroup        // code = 'unique_d4_stats' (multi)
}>()

// v-model наружу — пишем прямо в selectionByGroup родителя
const gaSelected = defineModel<number | null>('gaSelected', { default: null })
const statsSelected = defineModel<number[]>('statsSelected', { default: [] })

function clamp(n: number, min = 0, max = 4) {
  const x = Number(n)
  return Math.min(max, Math.max(min, Number.isFinite(x) ? x : 0))
}

const gaLimit = computed<number>(() => {
  const id = gaSelected.value
  const opt = props.gaGroup.values?.find(v => v.id === id)
  if (!opt) return 0

  // 1) основной источник — meta.ga_count
  const fromMeta = Number((opt as any)?.meta?.ga_count)
  if (Number.isFinite(fromMeta)) return clamp(fromMeta, 0, 4)

  // 2) фоллбек — берём цифру из title: "1 GA", "GA x2", "2GA", ...
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

// авто-выбор/очистка по лимиту
watch(gaLimit, (n) => {
  if (n === 0) {
    statsSelected.value = []
  } else if (n === 4) {
    // выбираем все 4 статов
    statsSelected.value = props.statsGroup.values?.map(v => v.id) ?? []
  } else {
    // усечь до лимита, если перебор
    if ((statsSelected.value?.length ?? 0) > n) {
      statsSelected.value = statsSelected.value.slice(0, n)
    }
  }
})

// если пользователь выбрал n чекбоксов, остальные дизейблим
const disabledSet = computed<Set<number>>(() => {
  const set = new Set<number>()
  const n = gaLimit.value
  const cur = statsSelected.value?.length ?? 0

  if (n === 0) {
    // ничего нельзя выбрать
    for (const v of props.statsGroup.values ?? []) set.add(v.id)
    return set
  }

  if (n === 4) {
    // выбираются все 4 — оставляем задизейбленными (менять можно через смену GA)
    for (const v of props.statsGroup.values ?? []) set.add(v.id)
    return set
  }

  // если уже достигли лимита — запрещаем клики по НЕвыбранным
  if (cur >= n) {
    for (const v of props.statsGroup.values ?? []) {
      if (!statsSelected.value.includes(v.id)) set.add(v.id)
    }
  }

  return set
})

onMounted(() => {
  // Выбрать дефолт для GA: Non GA если ничего не выбрано
  if (gaSelected.value == null) {
    const def = props.gaGroup.values?.find(v => v.title === 'Non GA') ?? props.gaGroup.values?.[0]
    gaSelected.value = def?.id ?? null
  }
})
</script>

<template>
  <div class="space-y-4">
    <!-- GA select -->
    <div>
      <div class="font-medium mb-1">Greater Affixes</div>
      <select class="w-full border rounded-md px-3 py-2 bg-background"
              :value="gaSelected ?? ''"
              @change="gaSelected = Number((($event.target as HTMLSelectElement).value || NaN)) || null">
        <option v-if="!gaGroup.is_required" value="">—</option>
        <option v-for="v in gaGroup.values" :key="v.id" :value="v.id">
          {{ labelGA(v) }}
        </option>
      </select>
    </div>

    <!-- 4 характеристики -->
    <div>
      <div class="font-medium mb-1">
        Item attributes
        <span class="text-xs text-muted-foreground">(choose {{ gaLimit }})</span>
      </div>
      <div class="space-y-1">
        <label v-for="v in statsGroup.values" :key="v.id" class="flex items-center gap-2">
          <input type="checkbox"
                 :checked="statsSelected.includes(v.id)"
                 :disabled="disabledSet.has(v.id)"
                 @change="($event) => {
                    const checked = ($event.target as HTMLInputElement).checked
                    const arr = new Set(statsSelected)
                    if (checked) arr.add(v.id); else arr.delete(v.id)
                    statsSelected = Array.from(arr).slice(0, gaLimit)
                 }" />
          <span>{{ v.title }}</span>
        </label>
      </div>
    </div>
  </div>
</template>