<script setup>
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import Hero from '@/Components/Hero.vue';
import { Head, Link } from '@inertiajs/vue3';
import Sponsors from '@/Components/Sponsors.vue';




</script>

<template>
    <Head title="Welcome" />
    <DefaultLayout>
        <Hero/>
        <Sponsors/>
    </DefaultLayout>    
</template>
