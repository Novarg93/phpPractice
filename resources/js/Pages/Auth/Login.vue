<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

// если используешь Ziggy:
declare const route: (name: string, params?: any) => string

defineProps<{
  canResetPassword?: boolean
  status?: string
}>()

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  })
}

const connectDiscord = () => {
  window.location.href = route('social.discord.redirect')
}
</script>

<template>
  <GuestLayout>
    <Head title="Log in" />

    <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
      {{ status }}
    </div>

    <!-- Social auth -->
    <div class="mb-6">
      <button
        type="button"
        @click="connectDiscord"
        class="w-full inline-flex items-center justify-center gap-2 rounded-md border px-4 py-2 text-sm font-medium transition hover:bg-muted"
      >
        <!-- простой логотип Discord (inline SVG) -->
        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor"
            d="M20.317 4.369A19.791 19.791 0 0 0 16.558 3c-.197.353-.42.83-.575 1.205a18.27 18.27 0 0 0-7.966 0C7.862 3.83 7.64 3.353 7.442 3a19.736 19.736 0 0 0-3.76 1.369C1.392 8.046.675 11.6.956 15.106c1.58 1.175 3.118 1.888 4.622 2.356c.373-.51.705-1.053.992-1.625c-.547-.207-1.07-.462-1.565-.758c.131-.095.26-.195.384-.297c3.004 1.406 6.266 1.406 9.23 0c.125.102.254.202.384.297c-.495.296-1.018.551-1.565.758c.287.572.619 1.115.992 1.625c1.504-.468 3.042-1.181 4.622-2.356c.38-4.716-.648-8.24-2.731-10.737ZM8.747 12.834c-.9 0-1.637-.83-1.637-1.852c0-1.022.725-1.852 1.637-1.852c.913 0 1.648.83 1.637 1.852c0 1.022-.724 1.852-1.637 1.852Zm6.506 0c-.9 0-1.637-.83-1.637-1.852c0-1.022.725-1.852 1.637-1.852c.913 0 1.637.83 1.637 1.852c0 1.022-.724 1.852-1.637 1.852Z" />
        </svg>
        Continue with Discord
      </button>

      <div class="my-4 flex items-center gap-3 text-xs text-muted-foreground">
        <div class="h-px w-full bg-border"></div>
        <span>or use email</span>
        <div class="h-px w-full bg-border"></div>
      </div>
    </div>

    <!-- Email form -->
    <form @submit.prevent="submit">
      <div>
        <InputLabel for="email" value="Email" />
        <TextInput
          id="email"
          type="email"
          class="mt-1 block w-full"
          v-model="form.email"
          required
          autofocus
          autocomplete="username"
        />
        <InputError class="mt-2" :message="form.errors.email" />
      </div>

      <div class="mt-4">
        <InputLabel for="password" value="Password" />
        <TextInput
          id="password"
          type="password"
          class="mt-1 block w-full"
          v-model="form.password"
          required
          autocomplete="current-password"
        />
        <InputError class="mt-2" :message="form.errors.password" />
      </div>

      <div class="mt-4 block">
        <label class="flex items-center">
          <Checkbox name="remember" v-model:checked="form.remember" />
          <span class="ms-2 text-sm text-gray-600">Remember me</span>
        </label>
      </div>

      <div class="mt-4 flex items-center justify-end">
        <Link
          v-if="canResetPassword"
          :href="route('password.request')"
          class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        >
          Forgot your password?
        </Link>

        <PrimaryButton
          class="ms-4"
          :class="{ 'opacity-25': form.processing }"
          :disabled="form.processing"
        >
          Log in
        </PrimaryButton>
      </div>
    </form>
  </GuestLayout>
</template>