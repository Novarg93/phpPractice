<template>
  <div ref="root" />
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

const props = defineProps<{
  bot: string
  authUrl: string
  size?: 'large'|'medium'|'small'
  lang?: string
}>()

const root = ref<HTMLElement|null>(null)

onMounted(() => {
  if (!props.bot || !props.authUrl) return
  const s = document.createElement('script')
  s.src = 'https://telegram.org/js/telegram-widget.js?22'
  s.async = true
  s.setAttribute('data-telegram-login', props.bot)
  s.setAttribute('data-size', props.size ?? 'large')
  s.setAttribute('data-userpic', 'false')
  s.setAttribute('data-lang', props.lang ?? 'en')
  s.setAttribute('data-auth-url', props.authUrl) // редирект на наш callback
  root.value?.appendChild(s)
})
</script>