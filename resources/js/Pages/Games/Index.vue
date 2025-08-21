<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-10 md:py-16 lg:py-20">
        <pre>{{ games }}</pre>

        <div class="mb-6">
          <h1 class="text-3xl font-bold">Games</h1>
          <p class="text-sm text-muted-foreground">Выбери игру, чтобы посмотреть разделы и товары.</p>
        </div>
    
        <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
          <article
            v-for="g in games"
            :key="g.id"
            class="border border-border rounded-xl p-3 hover:shadow transition"
          >
            <img
              v-if="g.image_url"
              :src="g.image_url"
              alt=""
              class="w-full h-36 object-cover rounded-lg mb-3"
            />
            <h3 class="font-medium mb-2 line-clamp-2">{{ g.name }}</h3>
    
            <Link
              :href="route('games.show', g.slug)"
              class="text-primary hover:underline text-sm"
            >
              Open
            </Link>
          </article>
        </div>
    </section>
  </DefaultLayout>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import DefaultLayout from '@/Layouts/DefaultLayout.vue'

type Game = { id: number; name: string; slug: string; image_url?: string | null }
const props = defineProps<{ games: Game[] }>()
const games = props.games
</script>