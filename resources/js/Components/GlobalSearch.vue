<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import { Search, Loader2 } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/Components/ui/dialog'

// Если используешь Ziggy:
declare const route: (name: string, params?: any) => string

type Product = {
  id: number
  title: string
  human_price: string
  stored_image: string
  url_code: string
  game?: {
    title: string
    url_code: string
    stored_logo_image: string
  } | null
}

const open = ref(false)
const query = ref('')
const loading = ref(false)
const results = ref<Product[]>([])
const activeIndex = ref(-1)
const inputRef = ref<HTMLInputElement | null>(null)
let tId: number | null = null

const hasQuery = computed(() => query.value.trim().length > 0)
const hasResults = computed(() => results.value.length > 0)

function focusInputSoon() {
  nextTick(() => inputRef.value?.focus())
}

function resetState() {
  query.value = ''
  results.value = []
  loading.value = false
  activeIndex.value = -1
  if (tId) { clearTimeout(tId); tId = null }
}

function openDialog() {
  if (!open.value) {
    open.value = true
    focusInputSoon()
  }
}
function closeDialog() {
  open.value = false
  resetState()
}

function debounceSearch() {
  if (tId) clearTimeout(tId)
  if (!hasQuery.value) {
    results.value = []
    activeIndex.value = -1
    return
  }
  tId = window.setTimeout(runSearch, 300)
}

async function runSearch() {
  loading.value = true
  try {
    const { data } = await axios.post<{ products: Product[] }>(route('search'), {
      query: query.value.trim(),
    })
    results.value = data?.products ?? []
    activeIndex.value = results.value.length ? 0 : -1
  } catch {
    results.value = []
    activeIndex.value = -1
  } finally {
    loading.value = false
  }
}

function productUrl(p: Product): string {
  // старый проект: route('product.view', url_code)
  try {
    return route('product.view', p.url_code)
  } catch {
    // запасной вариант, если Ziggy-роут не объявлен
    return `/products/${encodeURIComponent(p.url_code)}`
  }
}

function selectActive() {
  const p = results.value[activeIndex.value]
  if (p) {
    closeDialog()
    router.visit(productUrl(p))
  }
}

function onArrowDown() {
  if (!results.value.length) return
  activeIndex.value = (activeIndex.value + 1) % results.value.length
  scrollActiveIntoView()
}
function onArrowUp() {
  if (!results.value.length) return
  activeIndex.value = (activeIndex.value - 1 + results.value.length) % results.value.length
  scrollActiveIntoView()
}
function scrollActiveIntoView() {
  nextTick(() => {
    const el = document.getElementById(`gs-item-${activeIndex.value}`)
    el?.scrollIntoView({ block: 'nearest' })
  })
}

function onGlobalKey(e: KeyboardEvent) {
  const target = e.target as HTMLElement | null
  const isTypingEl =
    target &&
    (target.tagName === 'INPUT' ||
      target.tagName === 'TEXTAREA' ||
      (target as any).isContentEditable)

  // Ctrl/Cmd + K — открыть
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault()
    openDialog()
    return
  }

  // "/" — открыть (если не в поле ввода)
  if (!open.value && !isTypingEl && e.key === '/') {
    e.preventDefault()
    openDialog()
    return
  }

  // Esc — закрыть
  if (open.value && e.key === 'Escape') {
    e.preventDefault()
    closeDialog()
  }
}

onMounted(() => {
  window.addEventListener('keydown', onGlobalKey)
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onGlobalKey)
  if (tId) clearTimeout(tId)
})

watch(query, debounceSearch)
</script>

<template>
  <!-- Trigger button (встраивай куда угодно в хедер) -->
  <Button
    variant="ghost"
    class="h-9 px-3 flex items-center gap-2 rounded-lg border border-border text-sm"
    @click="openDialog"
  >
    <Search class="w-4 h-4 hidden md:block" />
    <span class="inline">Search</span>
    <span class="ml-2 sm:flex items-center gap-1 text-xs hidden text-muted-foreground">
      <kbd class="px-1.5 py-0.5 rounded bg-muted border border-border">Ctrl</kbd>
      <kbd class="px-1.5 py-0.5 rounded bg-muted border border-border">K</kbd>
    </span>
  </Button>

  <!-- Dialog -->
  <Dialog v-model:open="open">
    <DialogContent class="max-w-2xl top-[25%]  p-0 overflow-hidden border-border">
      <DialogHeader class="px-4 pt-4 pb-2">
        <DialogTitle class="sr-only">Search</DialogTitle>
        <DialogDescription class="sr-only">Find products across all games and categories</DialogDescription>
      </DialogHeader>

      <!-- Search input -->
      <div class="px-4 pb-2">
        <div class="relative">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
          <Input
            ref="inputRef"
            v-model="query"
            type="search"
            placeholder="Search products…"
            class="pl-9"
            @keydown.down.prevent.stop="onArrowDown"
            @keydown.up.prevent.stop="onArrowUp"
            @keydown.enter.prevent.stop="selectActive"
          />
          <div v-if="loading" class="absolute right-3 top-1/2 -translate-y-1/2">
            <Loader2 class="w-4 h-4 animate-spin text-muted-foreground" />
          </div>
        </div>
      </div>

      <!-- Results -->
      <div class="px-2 pb-2">
        <div
          v-if="hasResults"
          class="max-h-96 overflow-auto rounded-md border border-border"
          role="listbox"
          :aria-activedescendant="activeIndex >= 0 ? `gs-item-${activeIndex}` : undefined"
        >
          <button
            v-for="(p, i) in results"
            :key="p.id"
            :id="`gs-item-${i}`"
            role="option"
            :aria-selected="i === activeIndex"
            class="w-full text-left px-3 py-2 flex items-center gap-3 hover:bg-muted focus:bg-muted focus:outline-none"
            :class="i === activeIndex ? 'bg-muted' : ''"
            @mouseenter="activeIndex = i"
            @click="() => { closeDialog(); router.visit(productUrl(p)) }"
          >
            <div class="relative shrink-0">
              <img
                :src="p.stored_image"
                :alt="p.title"
                class="h-10 w-10 rounded object-cover border border-border"
              />
              <img
                v-if="p.game?.stored_logo_image"
                :src="p.game.stored_logo_image"
                :alt="p.game.title"
                class="absolute -right-1 -bottom-1 h-5 w-5 rounded bg-background p-0.5 object-contain border border-border"
              />
            </div>
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-medium">{{ p.title }}</div>
              <div class="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                <span>{{ p.human_price }}</span>
                <span v-if="p.game?.title" class="inline-flex items-center gap-1">
                  • <span class="truncate max-w-[12rem]">{{ p.game.title }}</span>
                </span>
              </div>
            </div>
          </button>
        </div>

        <div v-else class="px-3 py-8 text-center text-sm text-muted-foreground">
          <template v-if="!hasQuery">
            Start typing to search across all products
          </template>
          <template v-else>
            No results
          </template>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>