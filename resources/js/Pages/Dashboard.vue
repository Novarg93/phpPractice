<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { User as UserIcon, Link as LinkIcon, Unlink as UnlinkIcon } from 'lucide-vue-next'
import TelegramLogin from '@/Components/TelegramLogin.vue'

// Если используешь Ziggy:
declare const route: any

type User = {
  id: number
  name: string
  full_name: string | null
  email: string
  avatar_url?: string | null
  email_verified_at: string | null

  // Discord
  discord_user_id?: string | null
  discord_username?: string | null
  discord_avatar_url?: string | null
  discord_avatar?: string | null

  // Telegram (без аватара)
  telegram_user_id?: string | null
  telegram_username?: string | null
  telegram_chat_id?: string | null
}

type PageProps = {
  auth: { user: User | null }
  social?: { telegram_bot?: string | null }
  toast?: string | null
  [key: string]: unknown
}

// === Inertia props ===
const page = usePage<PageProps>()
const user = computed(() => page.props.auth.user)

// === Discord ===
const isDiscordLinked = computed(() => !!user.value?.discord_user_id)
const discordAvatarUrl = computed(() => {
  const u = user.value
  if (!u) return null
  if (u.discord_avatar_url) return u.discord_avatar_url
  const id = u.discord_user_id
  const hash = u.discord_avatar || undefined
  return id && hash ? `https://cdn.discordapp.com/avatars/${id}/${hash}.png?size=128` : null
})
const connectDiscord = () => { window.location.href = route('social.discord.redirect') }
const unlinkDiscord = () => { router.delete(route('social.discord.unlink')) }


// === Telegram (без аватара) ===
const isTelegramLinked = computed(() => !!user.value?.telegram_user_id)
const tgBot = computed(() => page.props.social?.telegram_bot ?? '')
const tgAuthUrl = computed(() => (typeof route !== 'undefined'
  ? route('social.telegram.callback')
  : '/auth/telegram/callback'))
const unlinkTelegram = () => {
  router.delete(route('social.telegram.unlink'), {}, {
    onSuccess: () => router.reload({ only: ['auth'] }), // чтобы статус обновился без F5
  })
}

// === Email verification ===
const isSending = ref(false)
const sent = ref(false)
const resendVerification = () => {
  isSending.value = true
  router.post(route('verification.send'), {}, {
    onSuccess: () => {
      sent.value = true
      setTimeout(() => (sent.value = false), 5000)
    },
    onFinish: () => { isSending.value = false }
  })
}
</script>

<template>
  <Head title="Dashboard" />

  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-10 md:py-16 lg:py-20">
      <h1 class="text-3xl font-semibold mb-6">Dashboard</h1>

      <!-- Header -->
      <div class="flex gap-10 items-center">
        <template v-if="user?.avatar_url">
          <img
            :src="user.avatar_url"
            alt="User avatar"
            class="h-16 w-16 place-self-start rounded-full object-cover mt-2"
            @error="$event.target.src = ''"
          />
        </template>
        <template v-else>
          <div class="h-16 w-16 flex place-self-start items-center justify-center rounded-full bg-muted text-muted-foreground mt-2">
            <UserIcon class="h-8 w-8" />
          </div>
        </template>

        <div class="flex flex-col gap-2">
          <p><span class="font-semibold">Name:</span> {{ user?.name }}</p>
          <p><span class="font-semibold">Email:</span> {{ user?.email }}</p>
          <p v-if="user?.full_name"><span class="font-semibold">Full name:</span> {{ user.full_name }}</p>

          <!-- Email verification -->
          <p>
            <span v-if="user?.email_verified_at" class="text-green-600 font-semibold">Verified</span>
            <template v-else>
              <div class="flex flex-col gap-2">
                <span class="text-red-600 font-semibold">Not verified</span>
                <button
                  class="px-3 py-1 rounded-md text-sm bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
                  @click="resendVerification"
                  :disabled="isSending"
                >
                  {{ isSending ? 'Sending...' : 'Resend verification email' }}
                </button>
                <span v-if="sent" class="ml-2 text-green-600 text-sm">Email sent!</span>
              </div>
            </template>
          </p>
        </div>
      </div>

      <!-- Social links -->
      <div class="grid md:grid-cols-2 gap-6 mt-10">
        <!-- Discord card -->
        <div class="rounded-2xl border p-5 bg-card text-card-foreground shadow-sm border-border">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
              <img
                v-if="isDiscordLinked && discordAvatarUrl"
                :src="discordAvatarUrl"
                class="h-10 w-10 rounded-full object-cover"
                alt="Discord avatar"
              />
              <div class="font-semibold text-lg">Discord</div>
            </div>
            <span :class="isDiscordLinked ? 'text-green-600' : 'text-red-600'" class="text-sm font-medium">
              {{ isDiscordLinked ? 'Linked' : 'Not linked' }}
            </span>
          </div>

          <template v-if="isDiscordLinked">
            <div class="text-sm text-muted-foreground mb-4 space-y-1">
              <div>
                <span class="text-foreground font-medium">Name:</span>
                <span class="ml-2">{{ user?.discord_username ?? '—' }}</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-foreground font-medium">ID:</span>
                <span class="font-mono">{{ user?.discord_user_id }}</span>
                
              </div>
            </div>

            <div class="flex gap-3">
              <button class="px-3 py-2 rounded-md bg-muted hover:bg-muted/70 inline-flex items-center gap-2" @click="unlinkDiscord">
                <UnlinkIcon class="w-4 h-4" /> Unlink
              </button>
            </div>
          </template>

          <template v-else>
            <div class="flex gap-3">
              <button
                class="px-3 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 inline-flex items-center gap-2"
                @click="connectDiscord"
              >
                <LinkIcon class="w-4 h-4" /> Link Discord
              </button>
            </div>
          </template>
        </div>

        <!-- Telegram card (без аватарки) -->
        <div
          class="rounded-2xl border p-5 bg-card text-card-foreground shadow-sm border-border"
          :key="(isTelegramLinked ? 'tg-linked' : 'tg-unlinked') + ':' + (user?.telegram_user_id || '')"
        >
          <div class="flex items-center justify-between mb-4">
            <div class="font-semibold text-lg">Telegram</div>
            <span :class="isTelegramLinked ? 'text-green-600' : 'text-red-600'" class="text-sm font-medium">
              {{ isTelegramLinked ? 'Linked' : 'Not linked' }}
            </span>
          </div>

          <template v-if="isTelegramLinked">
            <div class="mb-4 text-sm">
              <div class="font-medium">
                {{ user?.telegram_username ? '@' + user.telegram_username : 'No username' }}
              </div>
              <div class="text-xs text-muted-foreground">
                ID: <span class="font-mono">{{ user?.telegram_user_id }}</span>
              </div>
            </div>

            <button
              class="px-3 py-2 rounded-md bg-muted hover:bg-muted/70 inline-flex items-center gap-2"
              @click="unlinkTelegram"
            >
              <UnlinkIcon class="w-4 h-4" /> Unlink
            </button>
          </template>

          <template v-else>
            <div class="flex items-center gap-3">
              <!-- key нужен для корректного ремоунта виджета после unlink -->
              <TelegramLogin
                :key="'tg-login-' + (user?.telegram_user_id || 'none')"
                :bot="tgBot"
                :authUrl="tgAuthUrl"
                size="large"
              />
            </div>
          </template>
        </div>
      </div>
    </section>
  </DefaultLayout>
</template>