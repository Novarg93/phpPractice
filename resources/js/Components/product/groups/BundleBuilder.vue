<script setup lang="ts">
import { computed, ref } from 'vue'

type BundleItem = {
  product_id: number
  name: string
  image_url?: string | null
  price_cents: number
  qty: { min: number; max: number; step: number; default: number }
}

const props = defineProps<{ group: { id: number; title: string; items: BundleItem[] } }>()

// ⬇️ КЛЮЧЕВАЯ ПРАВКА: именованный v-model 'selected'
const model = defineModel<{ product_id: number; qty: number }[]>('selected', { default: [] })

const selectedIds = computed(() => new Set(model.value.map(r => r.product_id)))
const options = computed(() => props.group.items.filter(i => !selectedIds.value.has(i.product_id)))

// делаем string | null, чтобы не биться с .number и null→0
const picker = ref<string | null>(null)

function addSelected() {
  const idNum = picker.value ? Number(picker.value) : null
  if (!idNum) return
  if (selectedIds.value.has(idNum)) return
  const it = props.group.items.find(i => i.product_id === idNum)
  if (!it) return
  model.value = [...model.value, { product_id: idNum, qty: it.qty.default ?? it.qty.min }]
  picker.value = null
}

function removeRow(product_id: number) {
  model.value = model.value.filter(r => r.product_id !== product_id)
}

function limitsFor(product_id: number) {
  const it = props.group.items.find(i => i.product_id === product_id)
  return {
    min: it?.qty.min ?? 1,
    max: it?.qty.max ?? 9999,
    step: it?.qty.step ?? 1,
  }
}

const totalCents = computed(() =>
  model.value.reduce((sum, r) => {
    const it = props.group.items.find(i => i.product_id === r.product_id)
    return sum + (it ? it.price_cents * (r.qty || 0) : 0)
  }, 0),
)

function fmt(cents: number) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format((cents || 0) / 100)
}
</script>

<template>
  <div class="space-y-3">
    <div class="font-medium">{{ group.title }}</div>

    <div class="flex gap-2 items-center">
      <!-- placeholder value только пустая строка -->
      <select v-model="picker" placeholder="Select product" class="border text-black rounded placeholder:text-black px-2 w-full py-1">
        <option  value="" selected disabled>Select product…</option>
        <option v-for="opt in options" :key="opt.product_id" :value="String(opt.product_id)">
          {{ opt.name }} — {{ fmt(opt.price_cents) }}/unit
        </option>
      </select>
      <button class="px-3 py-1 border rounded" @click="addSelected" :disabled="!picker">Add</button>
    </div>

    <div v-if="model.length" class="space-y-3">
      <div v-for="row in model" :key="row.product_id" class="flex flex-col gap-3 border rounded p-2">
        <div class="flex justify-between">
          <div>
            {{ props.group.items.find(i => i.product_id === row.product_id)?.name }}
          </div>

          <div class="flex items-center gap-4">
            <div class="flex items-center font-semibold">
              {{
                (
                  ((props.group.items.find(i => i.product_id === row.product_id)?.price_cents || 0) *
                    (row.qty || 0)) /
                  100
                ).toFixed(2)
              }}
              $
            </div>
            <button class="text-sm px-2 py-1 border rounded" @click="removeRow(row.product_id)">Remove</button>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <input
            type="number"
            class="w-full text-black border rounded px-2 py-1"
            v-model.number="row.qty"
            :min="limitsFor(row.product_id).min"
            :max="limitsFor(row.product_id).max"
            :step="limitsFor(row.product_id).step"
          />
          <input
            type="range"
            class="w-full"
            v-model.number="row.qty"
            :min="limitsFor(row.product_id).min"
            :max="limitsFor(row.product_id).max"
            :step="limitsFor(row.product_id).step"
          />
        </div>
      </div>

      <div class="text-right font-bold">Total: {{ fmt(totalCents) }}</div>
    </div>
  </div>
</template>