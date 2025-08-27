<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps<{
  page: {
    id:number
    name:string
    code:string
    text:string|null
    seo: {
      title:string|null
      description:string|null
      og_title:string|null
      og_description:string|null
      og_image:string|null
    }
  }
}>()

const title = props.page.seo.title ?? props.page.name
const desc  = props.page.seo.description ?? ''
const ogt   = props.page.seo.og_title ?? title
const ogd   = props.page.seo.og_description ?? desc
const ogimg = props.page.seo.og_image ?? ''
</script>

<template>
  <DefaultLayout>
    <Head :title="title">
      <meta v-if="desc" name="description" :content="desc" />
      <meta property="og:title" :content="ogt" />
      <meta property="og:description" :content="ogd" />
      <meta v-if="ogimg" property="og:image" :content="ogimg" />
    </Head>

    <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">
      <h1 class="text-3xl font-semibold mb-6">{{ page.name }}</h1>
      <div class="legal max-w-none" v-html="page.text || ''" />
    </section>
  </DefaultLayout>
</template>