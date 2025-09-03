<script setup lang="ts">
import DefaultLayout from '@/Layouts/DefaultLayout.vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { User as UserIcon } from "lucide-vue-next"

interface User {
  id: number
  name: string
  full_name: string | null
  email: string
  avatar_url?: string | null
  email_verified_at: string | null
}

interface PageProps {
  auth: { user: User | null }
  [key: string]: unknown
}

const page = usePage<PageProps>()
const user = page.props.auth.user

const isSending = ref(false)
const sent = ref(false)

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
          <img :src="user.avatar_url" alt="User avatar" class="h-16 w-16 place-self-start  rounded-full object-cover mt-2"
            @error="$event.target.src = ''"
          />
        </template>
        <template v-else>
          <div class="h-16 w-16 flex place-self-start items-center justify-center rounded-full bg-muted text-muted-foreground mt-2">
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
    </section>
  </DefaultLayout>
</template>