<script setup>
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import Hero from '@/Components/Hero.vue';
import { Head, Link } from '@inertiajs/vue3';
import Sponsors from '@/Components/Sponsors.vue';
import Benefits from '@/Components/Benefits.vue';
import Features from '@/Components/Features.vue';
import Services from '@/Components/Services.vue';
import HowItWorks from '@/Components/HowItWorks.vue';
import Testimonials from '@/Components/Testimonials.vue';
import Team from '@/Components/Team.vue';
import Community from '@/Components/Community.vue';
import Pricing from '@/Components/Pricing.vue';
import Contact from '@/Components/Contact.vue';
import FAQ from '@/Components/FAQ.vue'





</script>

<template>
    <Head title="Welcome" />
    <DefaultLayout>
        <Hero/>
        <Sponsors/>
        <Benefits/>
        <Features/>
        <Services/>
        <HowItWorks/>
        <Testimonials/>
        <Team/>
        <Community/>
        <Pricing/>        
        <FAQ :faqs="$page.props.faqs"/>
    </DefaultLayout>    
</template>
