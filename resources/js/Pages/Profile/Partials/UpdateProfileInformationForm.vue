<script setup lang="ts">
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { useForm, usePage } from '@inertiajs/vue3'

defineProps({
  mustVerifyEmail: Boolean,
  status: String,
})

const user = usePage().props.auth.user


const form = useForm({
  name: user.name ?? '',
  full_name: user.full_name ?? '',
  email: user.email ?? '',
  avatar: null as File | null,
})

function submit() {
  form.transform((data) => {
    const d: any = { ...data, _method: 'patch' } // 👈 метод-спуфинг
    if (!d.avatar) delete d.avatar               // не шлём пустой файл
    return d
  })

  form.post(route('profile.update'), {
    forceFormData: true,           // 👈 обязательно для файла
    onSuccess: () => form.reset('avatar'),
  })
}


</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium">Profile Information</h2>
      <p class="mt-1 text-sm">Update your account's profile information and email address.</p>
    </header>

    <form
        @submit.prevent="submit"
      class="mt-6 space-y-6"
    >
      <div>
        <InputLabel for="name" value="Name" />
        <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required />
        <InputError class="mt-2" :message="form.errors.name" />
      </div>

      <div>
        <InputLabel for="full_name" value="Full Name" />
        <TextInput id="full_name" type="text" class="mt-1 block w-full" v-model="form.full_name" />
        <InputError class="mt-2" :message="form.errors.full_name" />
      </div>

      <div>
        <InputLabel for="email" value="Email" />
        <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required />
        <InputError class="mt-2" :message="form.errors.email" />
      </div>

      <div>
        <InputLabel for="avatar" value="Avatar" />
        <input id="avatar" type="file" @change="e => form.avatar = e.target.files?.[0] ?? null" />
        <InputError class="mt-2" :message="form.errors.avatar" />

        <div v-if="user.avatar" class="mt-2">
          <img :src="user.avatar" class="h-16 w-16 rounded-full object-cover" />
        </div>
      </div>

      <div class="flex items-center gap-4">
        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
        <Transition
          enter-active-class="transition ease-in-out"
          enter-from-class="opacity-0"
          leave-active-class="transition ease-in-out"
          leave-to-class="opacity-0"
        >
          <p v-if="form.recentlySuccessful" class="text-sm text-green-500">Saved.</p>
        </Transition>
      </div>
    </form>
  </section>
</template>