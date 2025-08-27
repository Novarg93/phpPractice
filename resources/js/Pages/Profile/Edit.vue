<script setup>

import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';


defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile" />

    <DefaultLayout>
        <section class="w-[90%] 2xl:w-[75%] mx-auto py-8 md:py-12 lg:py-16">
            
            
        <h2
                class="text-xl font-semibold leading-tight"
            >
                Profile
            </h2>

        <div class="py-12">
            
            <div class="mx-auto space-y-6 lg:">
                <div
                    class="bg-card p-4 shadow sm:rounded-lg sm:p-8"
                >
                
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div
                    class="bg-card p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div
                    class="bg-card p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
        </section>        
    </DefaultLayout>
</template>
