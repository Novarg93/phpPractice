<script setup lang="ts">
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb'

const props = defineProps<{
  post?: { title: string; slug: string } | null
}>()
</script>

<template>
  <Breadcrumb>
    <BreadcrumbList>
      <BreadcrumbItem>
        <BreadcrumbLink href="/">Home</BreadcrumbLink>
      </BreadcrumbItem>

      <BreadcrumbSeparator />

      <BreadcrumbItem>
        <BreadcrumbLink :href="route?.('posts.index') ?? '/posts'">Blog</BreadcrumbLink>
      </BreadcrumbItem>

      <template v-if="post">
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbPage>{{ post.title }}</BreadcrumbPage>
        </BreadcrumbItem>
      </template>
    </BreadcrumbList>
  </Breadcrumb>
</template>