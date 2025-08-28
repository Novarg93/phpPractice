<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import PostsBreadcrumbs from '@/Components/PostsBreadcrumbs.vue'

type PostItem = {
  id: number
  title: string
  slug: string
  image_url?: string | null
  published_at?: string | null
  excerpt?: string | null
  seo?: {
    title?: string | null
  }
}

const props = defineProps<{
  posts: {
    data: PostItem[]
    links: { url: string | null; label: string; active: boolean }[]
    total: number
  }
}>()
</script>

<template>
  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">
      <!-- breadcrumbs -->
      <PostsBreadcrumbs />
      
      <!-- header -->
      <header class="my-6">
        <h1 class="text-3xl font-semibold">Blog</h1>
        <p class="text-muted-foreground mt-2">
          Latest posts and updates.
        </p>
      </header>
      
      <!-- cards -->
      <div class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="post in props.posts.data"
          :key="post.id"
          class="border border-border rounded-xl p-3 hover:shadow transition"
        >
          <Link :href="route('posts.show', post.slug)">
            <img
              v-if="post.image_url"
              :src="post.image_url"
              alt=""
              class="w-full h-40 object-cover rounded-lg mb-3"
            />
          </Link>

          <h2 class="text-lg font-semibold leading-snug">
            <Link :href="route('posts.show', post.slug)" class="hover:underline">
              {{ post.title }}
            </Link>
          </h2>

          <p v-if="post.published_at" class="mt-1 text-xs text-muted-foreground">
            {{ post.published_at }}
          </p>

          <p v-if="post.excerpt" class="mt-3 text-sm text-muted-foreground line-clamp-3">
            {{ post.excerpt }}
          </p>

          <Link
            :href="route('posts.show', post.slug)"
            class="mt-3 inline-block text-primary hover:underline text-sm"
          >
            View
          </Link>
        </article>
      </div>

      <!-- pagination -->
      <div v-if="props.posts.links?.length" class="mt-8 flex gap-2 flex-wrap">
        <Link
          v-for="l in props.posts.links"
          :key="l.url ?? l.label"
          :href="l.url || '#'"
          class="px-3 py-1 rounded border text-sm"
          :class="[
            l.active ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-accent',
            !l.url ? 'opacity-50 pointer-events-none' : ''
          ]"
          v-html="l.label"
          preserve-scroll
        />
      </div>
    </section>
  </DefaultLayout>
</template>