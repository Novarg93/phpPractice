<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-10 md:py-16 lg:py-20">

        <nav class="text-sm text-muted-foreground mb-2">
          <Link :href="route('games.index')" class="hover:underline">Games</Link>
          <span class="mx-2">/</span>
          <Link :href="route('games.show', game.slug)" class="hover:underline">{{ game.name }}</Link>
          <span class="mx-2">/</span>
          <span class="text-foreground">{{ category.name }}</span>
        </nav>
    
        <header class="mb-4">
          <h1 class="text-3xl font-semibold">{{ category.name }}</h1>
          <p class="text-sm text-muted-foreground capitalize">{{ category.type }}</p>
        </header>
    
        <!-- Фильтры -->
        <div class="mb-6 flex flex-wrap gap-2">
          <input v-model="local.q" placeholder="Search…" class="px-3 py-2 border rounded-lg w-64 bg-background" />
          <select v-model="local.sort" class="px-3 py-2 border rounded-lg bg-background">
            <option value="name">Name</option>
            <option value="price_cents">Price</option>
            <option value="created_at">Newest</option>
          </select>
          <select v-model="local.order" class="px-3 py-2 border rounded-lg bg-background">
            <option value="asc">Asc</option>
            <option value="desc">Desc</option>
          </select>
          <button @click="apply" class="px-4 py-2 rounded-lg bg-primary text-primary-foreground">Apply</button>
          <button @click="reset" class="px-4 py-2 rounded-lg border">Reset</button>
        </div>
    
        <!-- Товары -->
        <div class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
          <article v-for="p in products.data" :key="p.id" class="border rounded-xl p-3 hover:shadow transition">
            <img v-if="p.image" :src="p.image" class="w-full h-36 object-cover rounded-lg mb-2" />
            <h3 class="font-medium line-clamp-2">{{ p.name }}</h3>
            <div class="text-sm text-muted-foreground line-clamp-2">{{ p.short }}</div>
            <div class="mt-2 font-semibold">{{ formatPrice(p.price_cents) }}</div>
            <Link
              :href="route('products.show', [game.slug, category.slug, p.slug])"
              class="mt-2 inline-block text-primary hover:underline text-sm"
            >View</Link>
          </article>
        </div>
    
        <!-- Пагинация -->
        <div v-if="products.links?.length" class="mt-8 flex gap-2 flex-wrap">
          <Link
            v-for="l in products.links"
            :key="l.url ?? l.label"
            :href="l.url || '#'"
            class="px-3 py-1 rounded border text-sm"
            :class="[
              l.active ? 'bg-primary text-primary-foreground' : 'hover:bg-accent',
              !l.url ? 'opacity-50 pointer-events-none' : ''
            ]"
            v-html="l.label"
            preserve-scroll
          />
        </div>
    </section>
  </DefaultLayout>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import DefaultLayout from '@/Layouts/DefaultLayout.vue'

type Game = { id:number; name:string; slug:string }
type Category = { id:number; name:string; slug:string; type:string }
type ProductCard = {
  id:number; category_id:number; name:string; slug:string;
  price_cents:number; image?:string|null; short?:string|null
}
type Paginator<T> = { data:T[]; links:Array<{ url:string|null; label:string; active:boolean }> }

const props = defineProps<{
  game: Game
  category: Category
  products: Paginator<ProductCard>
  filters: Record<string, any>
}>()

const { game, category, products } = props

const local = reactive({
  q: props.filters.q ?? '',
  sort: props.filters.sort ?? 'name',
  order: props.filters.order ?? 'asc',
})

function apply() {
  router.get(
    route('categories.show', [game.slug, category.slug]),
    { q: local.q || undefined, sort: local.sort, order: local.order },
    { preserveScroll: true, preserveState: true }
  )
}
function reset() {
  local.q = ''; local.sort = 'name'; local.order = 'asc'; apply()
}
function formatPrice(cents:number) {
  return new Intl.NumberFormat('en-US', { style:'currency', currency:'USD' }).format(cents / 100)
}
</script>