<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

type LinkItem = { url: string|null; label: string; active: boolean }
const props = defineProps<{
  links: LinkItem[]
}>()

/** Laravel отдаёт HTML-сущности (&laquo;, &raquo;) в label — рисуем их через v-html */
</script>

<template>
  <nav v-if="links?.length" class="mt-6 flex items-center justify-center gap-2" aria-label="pagination">
    <template v-for="(l, i) in links" :key="i">
      <!-- разделители типа "..." -->
      <span v-if="!l.url" class="px-3 py-2 text-sm text-muted-foreground select-none" v-html="l.label" />

      <!-- активная страница -->
      <span v-else-if="l.active"
            class="px-3 py-2 text-sm rounded-md bg-primary/10 text-primary font-medium"
            v-html="l.label" />

      <!-- обычные ссылки -->
      <Link v-else :href="l.url"
            preserve-scroll
            class="px-3 py-2 text-sm rounded-md hover:bg-accent"
            v-html="l.label" />
    </template>
  </nav>
</template>