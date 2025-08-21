<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-10 md:py-16 lg:py-20">
        <pre>{{ game }}</pre>
        <nav class="text-sm text-muted-foreground mb-2">
          <Link :href="route('games.index')" class="hover:underline">Games</Link>
          <span class="mx-2">/</span>
          <span class="text-foreground">{{ game.name }}</span>
        </nav>
    
        <header class="mb-6">
          <h1 class="text-3xl font-semibold">{{ game.name }}</h1>
          <p v-if="game.description" class="text-muted-foreground mt-2">{{ game.description }}</p>
        </header>
    
          <section>
      <h2 class="text-xl font-semibold mb-3">Sections</h2>

      <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
        <Link
          v-for="c in game.categories"
          :key="c.id"
          :href="route('categories.show', [game.slug, c.slug])"
          class="border rounded-xl p-4 hover:shadow transition flex items-center gap-3"
        >
          <img
            v-if="c.image"
            :src="c.image"
            alt=""
            class="w-12 h-12 rounded-md object-cover border"
          />
          <div class="flex-1">
            <div class="font-medium">{{ c.name }}</div>
            <div class="text-xs text-muted-foreground">{{ c.type }}</div>
          </div>
          <svg class="w-5 h-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/></svg>
        </Link>
      </div>
    </section>
    </section>
  </DefaultLayout>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import Category from '../Catalog/Category.vue';

type Category = { id:number; name:string; slug:string; type:string; image?: string | null }
type Game = {
  id:number; name:string; slug:string;
  description?: string | null; image_url?: string | null;
  categories: Category[];
}
const props = defineProps<{ game: Game }>()
const game = props.game
</script>