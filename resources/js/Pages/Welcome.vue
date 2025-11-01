<script setup>
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import Hero from '@/components/Hero.vue';
import { Head, Link } from '@inertiajs/vue3';
import Sponsors from '@/components/Sponsors.vue';
import Benefits from '@/components/Benefits.vue';
import Features from '@/components/Features.vue';
import Services from '@/components/Services.vue';
import HowItWorks from '@/components/HowItWorks.vue';
import Testimonials from '@/components/Testimonials.vue';
import Team from '@/components/Team.vue';
import Community from '@/components/Community.vue';
import Pricing from '@/components/Pricing.vue';
import Contact from '@/components/Contact.vue';
import FAQ from '@/components/FAQ.vue'





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
