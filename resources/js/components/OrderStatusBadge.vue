<script setup lang="ts">
const props = defineProps<{ status: 'pending'|'paid'|'in_progress'|'completed'|'refund'|string }>()
const labels: Record<string,string> = {
  pending: 'Pending',
  paid: 'Paid',
  in_progress: 'In progress',
  completed: 'Completed',
  refund: 'Refund',
}
const colors: Record<string,string> = {
  pending: 'bg-amber-100 text-amber-800 ring-amber-200',
  paid: 'bg-blue-100 text-blue-800 ring-blue-200',
  in_progress: 'bg-sky-100 text-sky-800 ring-sky-200',
  completed: 'bg-green-100 text-green-800 ring-green-200',
  refund: 'bg-red-100 text-red-800 ring-red-200',
}
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
    :class="colors[props.status] ?? 'bg-gray-100 text-gray-800 ring-gray-200'"
  >
    {{ labels[props.status] ?? props.status }}
  </span>
</template>