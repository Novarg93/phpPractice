<script setup lang="ts">

import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import { Head,  usePage } from '@inertiajs/vue3';


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
</script>

<template>
    <Head title="Dashboard" />

    <DefaultLayout>
         <section class="w-[90%] 2xl:w-[75%] mx-auto py-10 md:py-16 lg:py-20">
            <h1 class="text-3xl font-semibold mb-6">Dashboard</h1>
            <div class="flex gap-10 items-center">
                <div class="flex flex-col gap-2">
                    <img :src="user.avatar_url" alt="User avatar" class="h-16 w-16 rounded-full object-cover mt-2" />                    
                </div>

                <div class="flex flex-col gap-2">
                    <p><span class="font-semibold">Name:</span> {{ user?.name }}</p>                    
                    <p><span class="font-semibold">Email:</span> {{ user.email }}</p>
                    <p v-if="user.full_name"><span class="font-semibold">Full name:</span> {{ user.full_name }}</p>
                </div>
            </div>
      
    </section>
    </DefaultLayout>
</template>
