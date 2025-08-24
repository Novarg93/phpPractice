<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import Breadcrumbs from '@/Components/Breadcrumbs.vue'
import type { Category as BaseCategory, Product as BaseProduct, Paginator } from '@/types'


type Cat = BaseCategory
type Prod = BaseProduct & { categories?: Cat[] }



const props = defineProps<{
  game: { id:number; name:string; slug:string; image_url?:string|null; description?:string|null }
  category: (Cat & { short?:string|null; description?:string|null }) | null
  categories: Cat[]
  products: Paginator<Prod>
  seo: { short?:string|null; description?:string|null }
  totalProducts: number
}>()

const { game, category, categories, products, seo, totalProducts  } = props

function formatPrice(cents:number) {
  return new Intl.NumberFormat('en-US', { style:'currency', currency:'USD' }).format(cents / 100)
}
</script>

<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">
      <!-- breadcrumbs -->
       
       <Breadcrumbs :game="game" :category="category" />

      <!-- header -->
      <header class="my-6">
        <h1 class="text-3xl font-semibold">
          {{ category ? category.name : game.name }}
        </h1>
        <p v-if="seo.short" class="text-muted-foreground mt-2">
          {{ seo.short }}
        </p>
      </header>

      <div class="grid grid-cols-12 gap-6">
        <!-- sidebar filters -->
        <aside class="col-span-12 md:col-span-3 space-y-2">
          <Link
            :href="route('games.show', game.slug)"
            class="flex items-center px-3 py-2 rounded-lg border border-border hover:bg-accent"
            :class="!category ? 'bg-primary text-primary-foreground border-transparent' : ''"
          >
            <span class="flex-1">All</span>
            <span class="text-xs opacity-70">{{ totalProducts }}</span>
          </Link>

          <Link
            v-for="c in categories"
            :key="c.id"
            :href="route('categories.show', [game.slug, c.slug])"
            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-border hover:bg-accent"
            :class="category?.id === c.id ? 'bg-primary text-primary-foreground border-transparent' : ''"
          >
            <img v-if="c.image" :src="c.image" class="w-6 h-6 rounded object-cover text-white border-0" alt="">
            <span class="flex-1">{{ c.name }}</span>
            <span class="text-xs opacity-70">{{ c.products_count }}</span>
          </Link>
        </aside>

        <!-- products grid -->
        <main class="col-span-12 md:col-span-9">
          <div class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            <article v-for="p in products.data" :key="p.id" class="border border-border rounded-xl p-3 hover:shadow transition">
              <img v-if="p.image" :src="p.image" class="w-full h-36 object-cover object-top  rounded-lg mb-2" />
              <h3 class="font-medium line-clamp-2">{{ p.name }}</h3>
              <div class="text-sm text-muted-foreground line-clamp-2">{{ p.short }}</div>
              <div class="mt-2 font-semibold">{{ formatPrice(p.price_cents) }}</div>
              <Link
                :href="route('products.show', [game.slug, (category?.slug ?? p.categories?.[0]?.slug ?? 'items'), p.slug])"
                class="mt-2 inline-block text-primary hover:underline text-sm"
              >View</Link>
            </article>
          </div>

          <!-- SEO long description -->
          <div v-if="seo.description" class="prose prose-invert max-w-none mt-10">
            <details>
              <summary class="cursor-pointer select-none">Read more</summary>
              <div v-html="seo.description" />
            </details>
          </div>

          <!-- pagination -->
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
        </main>
      </div>
    </section>
  </DefaultLayout>
</template>

