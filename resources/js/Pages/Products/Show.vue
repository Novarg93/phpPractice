<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-10 md:py-16 lg:py-20">

        <nav class="text-sm text-muted-foreground mb-2">
          <Link :href="route('games.index')" class="hover:underline">Games</Link>
          <span class="mx-2">/</span>
          <Link :href="route('games.show', game.slug)" class="hover:underline">{{ game.name }}</Link>
          <span class="mx-2">/</span>
          <Link :href="route('categories.show', [game.slug, category.slug])" class="hover:underline">{{ category.name }}</Link>
          <span class="mx-2">/</span>
          <span class="text-foreground">{{ product.name }}</span>
        </nav>
    
        <div class="grid md:grid-cols-2 gap-6 mt-2">
          <div>
            <img v-if="product.image" :src="product.image" class="w-full rounded-xl border" />
            <div v-else class="aspect-video rounded-xl border grid place-items-center text-sm text-muted-foreground">
              No image
            </div>
          </div>
    
          <div>
            <h1 class="text-3xl font-semibold">{{ product.name }}</h1>
            <div class="mt-2 text-2xl font-bold">{{ formatPrice(product.price_cents) }}</div>
    
            <div class="mt-3 text-sm text-muted-foreground">
              <span v-if="!product.track_inventory">Available</span>
              <span v-else>
                <template v-if="(product.stock ?? 0) > 0">In stock: {{ product.stock }}</template>
                <template v-else>Out of stock</template>
              </span>
              <span v-if="product.sku" class="ml-3">SKU: <code>{{ product.sku }}</code></span>
            </div>
    
            <button class="mt-5 px-4 py-2 rounded-lg bg-primary text-primary-foreground">
              Add to cart
            </button>
    
            <div v-if="product.short" class="mt-6 text-base">{{ product.short }}</div>
            <div v-if="product.description" class="prose prose-invert max-w-none mt-4" v-html="product.description" />
          </div>
        </div>
    </section>
  </DefaultLayout>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import DefaultLayout from '@/Layouts/DefaultLayout.vue'

type Game = { id:number; name:string; slug:string }
type Category = { id:number; name:string; slug:string; type:string }
type Product = {
  id:number; name:string; slug:string; price_cents:number;
  image?:string|null; short?:string|null; description?:string|null;
  sku?:string|null; track_inventory:boolean; stock:number|null;
}

const props = defineProps<{ game: Game; category: Category; product: Product }>()
const { game, category, product } = props

function formatPrice(cents:number) {
  return new Intl.NumberFormat('en-US', { style:'currency', currency:'USD' }).format(cents / 100)
}
</script>