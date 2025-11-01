<script setup lang="ts">
import axios from 'axios'
import { ref, computed, watch } from 'vue'
import { defineExpose } from 'vue'

const props = defineProps<{
  initialNickname?: string | null
  required?: boolean
  saveUrl: string
  label?: string
  help?: string
}>()

const nickname = ref(props.initialNickname ?? '')
const saving   = ref(false)
const saved    = ref(false)
const cleared  = ref(false)            // 👈 флаг "очищено"
const error    = ref<string | null>(null)

const isEmpty  = computed(() => nickname.value.trim() === '')
const isDirty  = computed(() => (props.initialNickname ?? '') !== nickname.value) // было/стало

const needsAttention = computed(() => (props.required ?? false) && isEmpty.value)

const inputClass = computed(() => [
  'block w-full text-white rounded-md border px-3 py-2 bg-input text-sm outline-none transition',
  (needsAttention.value || error.value)
    ? 'border-red-500 ring-1 ring-red-500 focus:ring-red-500'
    : 'border-border focus:ring-1 focus:ring-primary'
].join(' '))

watch(nickname, () => {
  error.value = null
  saved.value = false
  cleared.value = false
})

defineExpose({ submit })

async function submit() {
  // если обязательно — не даём отправить пустое
  if ((props.required ?? false) && isEmpty.value) {
    error.value = 'Type Nickname of your character'
    return
  }
  if (saving.value) return
  saving.value = true

  try {
    const payload = { nickname: isEmpty.value ? null : nickname.value.trim() } // 👈 пустое → null
    await axios.post(props.saveUrl, payload)

    if (isEmpty.value) {
      // если раньше ник был, значит мы его "очистили"
      if ((props.initialNickname ?? '') !== '') cleared.value = true
      saved.value = false
    } else {
      saved.value = true
      cleared.value = false
    }
    error.value = null
  } catch (e:any) {
    const msg = e?.response?.data?.message || 'Error'
    error.value = msg
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="border rounded-lg p-4">
    <label class="block text-sm font-medium mb-2">
      {{ label ?? 'Ник персонажа (в игре)' }}
      <span v-if="!required" class="text-xs text-muted-foreground ml-1">(not required)</span>
      <span v-else class="text-xs text-red-600 ml-1">*</span>
    </label>

    <input
      :class="inputClass"
      v-model.trim="nickname"     
      type="text"
      inputmode="text"
      autocapitalize="none"
      autocomplete="off"
      spellcheck="false"
      placeholder="MyNickName"
    />

    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    <p v-else-if="saved" class="mt-1 text-xs text-green-600">Saved</p>
    <p v-else-if="cleared" class="mt-1 text-xs text-green-600">Cleared</p>

    <div class="mt-3">
      <button
        :disabled="saving || !isDirty"  
        class="px-3 py-2 rounded-md bg-primary text-primary-foreground disabled:opacity-50"
        @click="submit"
      >
        {{ saving ? 'Submitting...' : 'Submit' }}
      </button>
    </div>
  </div>
</template>