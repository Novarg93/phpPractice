<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import { Head } from '@inertiajs/vue3'
import PostsBreadcrumbs from '@/Components/PostsBreadcrumbs.vue'

const props = defineProps<{
  post: {
    id: number
    title: string
    slug: string
    status: string
    image_url?: string | null
    content: string
    published_at?: string | null
    seo: {
      title?: string | null
      description?: string | null
      og_title?: string | null
      og_description?: string | null
      og_image?: string | null
    }
  }
}>()
</script>

<template>
  <Head :title="props.post.seo.title || props.post.title" />

  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">
      <!-- breadcrumbs -->
      <PostsBreadcrumbs :post="props.post" />

      <!-- header -->
      <header class="my-6">
        <h1 class="text-3xl font-semibold">{{ props.post.title }}</h1>
        <p v-if="props.post.published_at" class="mt-2 text-sm text-muted-foreground">
          {{ props.post.published_at }}
        </p>
      </header>

      <article class="">
        <img
          v-if="props.post.image_url"
          :src="props.post.image_url"
          alt=""
          class="my-6 w-full  rounded-lg"
        />

        <!-- content -->
        <div class="legal  max-w-none" v-html="props.post.content" />
      </article>
    </section>
  </DefaultLayout>
</template>