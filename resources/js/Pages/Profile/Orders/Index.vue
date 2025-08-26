<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3';

type OrderDto = { id:number; status:string; placed_at:string|null; total_cents:number; items_count:number }

const props = defineProps<{
  orders: { data: OrderDto[]; links?: any; meta?: any } // ← пагинатор
}>()

const list = computed(() => props.orders?.data ?? [])

function formatPrice(cents:number) {
  return new Intl.NumberFormat('en-US', { style:'currency', currency:'USD' }).format(cents/100)
}
</script>

<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8">
      <h1 class="text-3xl font-semibold mb-6">Your orders</h1>

      <div v-if="list.length" class="space-y-3">
        <Link v-for="o in list" :key="o.id" class="block border border-border rounded-lg p-4 hover:bg-accent"
           :href="route('orders.show', o.id)">
          <div class="flex justify-between">
            <div>
              <div class="font-medium">Order #{{ o.id }}</div>
              <div class="text-sm text-muted-foreground">{{ o.placed_at || '—' }} · {{ o.items_count }} items</div>
            </div>
            <div class="text-right">
              <div class="font-semibold">{{ formatPrice(o.total_cents) }}</div>
              <div class="text-xs uppercase tracking-wide text-muted-foreground">{{ o.status }}</div>
            </div>
          </div>
        </Link>
      </div>

      <div v-else class="text-muted-foreground">You have no orders yet.</div>

      <!-- при желании: пагинация через props.orders.links -->
    </section>
  </DefaultLayout>
</template>