<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { User as UserIcon, Link as LinkIcon, Unlink as UnlinkIcon, RefreshCcw } from "lucide-vue-next"
import TelegramLogin from '@/Components/TelegramLogin.vue'



interface User {
  id: number
  name: string
  full_name: string | null
  email: string
  avatar_url?: string | null
  email_verified_at: string | null
  discord_avatar?: string | null
  // из бэка (через Inertia share)
  discord_user_id?: string | null
  discord_username?: string | null
  discord_avatar_url?: string | null

  telegram_user_id?: string | null
  telegram_username?: string | null
  telegram_chat_id?: string | null
}

interface PageProps {
  auth: { user: User | null }
  tg_code?: string | null // придёт флешем после генерации/обновления кода
  toast?: string | null
  [key: string]: unknown
}

const page = usePage<PageProps>()
const user = page.props.auth.user
const tgBot = page.props.social?.telegram_bot ?? ''
const tgAuthUrl = route ? route('social.telegram.callback') : '/auth/telegram/callback'
const hasTelegram = computed(() => !!user?.telegram_user_id)
const isSending = ref(false)
const sent = ref(false)
const discordAvatarUrl = computed(() => {
  // приоритет: то, что пришло из бэка (если ты шаришь готовый URL)
  if (user?.discord_avatar_url) return user.discord_avatar_url
  // иначе собираем из id + hash
  const id = user?.discord_user_id
  const hash = (user as any)?.discord_avatar as string | undefined
  return id && hash ? `https://cdn.discordapp.com/avatars/${id}/${hash}.png?size=128` : null
})

const copyDiscordId = async () => {
  if (!user?.discord_user_id) return
  try {
    await navigator.clipboard.writeText(user.discord_user_id)
  } catch { }
}

// Discord
const hasDiscord = computed(() => !!user?.discord_user_id)
const connectDiscord = () => {
  window.location.href = route('social.discord.redirect')
}
const unlinkDiscord = () => {
  router.delete(route('social.discord.unlink'))
}

// Telegram

const tgCode = ref<string | null>((page.props as any).tg_code ?? null)

const startTelegram = () => {
  router.post(route('social.telegram.start'), {}, {
    onSuccess: () => {
      // code приедет флешем, но чтобы сразу обновить локально:
      tgCode.value = (usePage<PageProps>().props.tg_code as any) ?? null
    }
  })
}
const refreshTelegram = () => {
  router.post(route('social.telegram.refresh'), {}, {
    onSuccess: () => {
      tgCode.value = (usePage<PageProps>().props.tg_code as any) ?? null
    }
  })
}
const unlinkTelegram = () => {
  router.delete(route('social.telegram.unlink'), {}, {
    onSuccess: () => {
      tgCode.value = null
    }
  })
}



const resendVerification = () => {
  isSending.value = true
  router.post(
    route('verification.send'),
    {},
    {
      onSuccess: () => {
        sent.value = true
        setTimeout(() => (sent.value = false), 5000)
      },
      onFinish: () => {
        isSending.value = false
      },
    }
  )
}
</script>

<template>

  <Head title="Dashboard" />

  <DefaultLayout>
    <section class="w-[90%] 2xl:w-[75%] mx-auto py-10 md:py-16 lg:py-20">
      <h1 class="text-3xl font-semibold mb-6">Dashboard</h1>

      <div class="flex gap-10  items-center">
        <template v-if="user?.avatar_url">
          <img :src="user.avatar_url" alt="User avatar"
            class="h-16 w-16 place-self-start  rounded-full object-cover mt-2" @error="$event.target.src = ''" />
        </template>
        <template v-else>
          <div
            class="h-16 w-16 flex place-self-start items-center justify-center rounded-full bg-muted text-muted-foreground mt-2">
            <UserIcon class="h-8 w-8" />
          </div>
        </template>

        <div class="flex flex-col gap-2">
          <p>
            <span class="font-semibold">Name:</span> {{ user?.name }}
          </p>
          <p>
            <span class="font-semibold">Email:</span> {{ user?.email }}
          </p>
          <p v-if="user?.full_name">
            <span class="font-semibold">Full name:</span> {{ user.full_name }}
          </p>

          <!-- Верификация -->
          <p>
            <span v-if="user?.email_verified_at" class="text-green-600 font-semibold">
              Verified
            </span>

            <template v-else>
              <div class="flex flex-col gap-2">

                <span class="text-red-600 font-semibold"> Not verified</span>

                <button
                  class="px-3 py-1 rounded-md text-sm bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
                  @click="resendVerification" :disabled="isSending">
                  {{ isSending ? 'Sending...' : 'Resend verification email' }}
                </button>
                <span v-if="sent" class="ml-2 text-green-600 text-sm">
                  Email sent!
                </span>
              </div>
            </template>
          </p>
        </div>
      </div>

      <!-- Социальные привязки -->
      <div class="grid md:grid-cols-2 gap-6 mt-10">
        <!-- Discord card -->
        <!-- Discord card -->
        <div class="rounded-2xl border p-5 bg-card text-card-foreground shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
              <img v-if="hasDiscord && discordAvatarUrl" :src="discordAvatarUrl"
                class="h-10 w-10 rounded-full object-cover" alt="Discord avatar" />
              <div class="font-semibold text-lg">Discord</div>
            </div>
            <span :class="hasDiscord ? 'text-green-600' : 'text-red-600'" class="text-sm font-medium">
              {{ hasDiscord ? 'Linked' : 'Not linked' }}
            </span>
          </div>

          <template v-if="hasDiscord">
            <div class="text-sm text-muted-foreground mb-4 space-y-1">
              <div>
                <span class="text-foreground font-medium">Name:</span>
                <span class="ml-2">{{ user?.discord_username ?? '—' }}</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-foreground font-medium">ID:</span>
                <span class="font-mono">{{ user?.discord_user_id }}</span>
                <button class="px-2 py-0.5 text-xs rounded bg-muted hover:bg-muted/70" @click="copyDiscordId"
                  title="Copy ID">
                  Copy
                </button>
              </div>
            </div>

            <div class="flex gap-3">
              <button class="px-3 py-2 rounded-md bg-muted hover:bg-muted/70 inline-flex items-center gap-2"
                @click="unlinkDiscord">
                <UnlinkIcon class="w-4 h-4" /> Unlink
              </button>
            </div>
          </template>

          <template v-else>
            <div class="flex gap-3">
              <button
                class="px-3 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 inline-flex items-center gap-2"
                @click="connectDiscord">
                <LinkIcon class="w-4 h-4" /> Link Discord
              </button>
            </div>
          </template>
        </div>

        <!-- Telegram card -->
        <div class="rounded-2xl border p-5 bg-card text-card-foreground shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <div class="font-semibold text-lg">Telegram</div>
            <span :class="hasTelegram ? 'text-green-600' : 'text-red-600'" class="text-sm font-medium">
              {{ hasTelegram ? 'Linked' : 'Not linked' }}
            </span>
          </div>

          <template v-if="hasTelegram">
            <p class="text-sm text-muted-foreground mb-4">
              {{ user?.telegram_username ? '@' + user.telegram_username : 'No username' }}
              (chat_id: {{ user?.telegram_chat_id }})
            </p>
            <button class="px-3 py-2 rounded-md bg-muted hover:bg-muted/70 inline-flex items-center gap-2"
              @click="unlinkTelegram">
              <UnlinkIcon class="w-4 h-4" /> Unlink
            </button>
          </template>

          <template v-else>

            <!-- <p class="text-sm text-muted-foreground mb-3">
              Нажми «Сгенерировать код», затем в Telegram напиши нашему боту команду
              <code class="px-1 py-0.5 bg-muted rounded">/link КОД</code>.
              Бот подтвердит и привяжет чат. Код действует 30 минут.
            </p>

            <div class="flex items-center gap-3 mb-3" v-if="tgCode">
              <span class="font-mono text-lg tracking-wider">{{ tgCode }}</span>
              <button class="px-2 py-1 text-xs rounded bg-muted hover:bg-muted/70"
                @click="() => navigator.clipboard.writeText(tgCode!)">
                Copy
              </button>
              <button class="px-2 py-1 text-xs rounded bg-muted hover:bg-muted/70 inline-flex items-center gap-1"
                @click="refreshTelegram">
                <RefreshCcw class="w-3 h-3" /> Refresh
              </button>
            </div>

            <div class="flex gap-3">
              <button class="px-3 py-2 rounded-md bg-sky-600 text-white hover:bg-sky-700 inline-flex items-center gap-2"
                @click="startTelegram">
                <LinkIcon class="w-4 h-4" /> Сгенерировать код
              </button>
              <button v-if="tgCode" class="px-3 py-2 rounded-md bg-muted hover:bg-muted/70" @click="refreshTelegram">
                Обновить код
              </button>
            </div> -->
            <div v-if="!user?.telegram_user_id" class="flex items-center gap-3">
              <TelegramLogin :bot="tgBot" :authUrl="tgAuthUrl" size="large" />
            </div>

            <div v-else class="flex items-center gap-3">
              <span class="text-green-600 font-semibold">
                Linked as @{{ user.telegram_username || user.telegram_user_id }}
              </span>
              <button class="px-3 py-1 rounded-md text-sm bg-red-600 text-white hover:bg-red-700"
                @click="router.delete(route('social.telegram.unlink'))">
                Unlink
              </button>
            </div>
          </template>
        </div>
      </div>
    </section>
  </DefaultLayout>
</template>