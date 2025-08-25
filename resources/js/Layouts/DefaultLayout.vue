<script lang="ts" setup>
import { ref, onMounted, nextTick, watch, } from "vue";
import { usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3';
import { useCartSummary } from '@/composables/useCartSummary'
import axios from 'axios'

import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/Components/ui/drawer'

import { Button } from "@/Components/ui/button";
import { Separator } from "@/Components/ui/separator";
import { ShoppingCart } from "lucide-vue-next";
import { ChevronsDown, Menu, X } from "lucide-vue-next";

const { summary, loadSummary } = useCartSummary()

function formatPrice(cents: number) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100)
}



const isOpen = ref<boolean>(false);

onMounted(() => loadSummary())

</script>


<template>
    <div class="flex flex-col min-h-screen">
        <header
            class='w-[90%] 2xl:w-[75%]  mx-auto border border-border mt-10  rounded-2xl flex justify-between items-center p-2 bg-card shadow-md'>
            <Link :href="route('home')" class="font-bold text-lg flex items-center">
            <ChevronsDown
                class="bg-gradient-to-tr from-primary via-primary/70 to-primary rounded-lg w-9 h-9 mr-2 border border-transparent text-white" />
            ShadcnVue
            </Link>
            <!-- Mobile -->

            <Drawer direction="left" v-model:open="isOpen">
                <DrawerTrigger class="lg:hidden" as-child>
                    <Button variant="ghost">
                        <Menu />
                    </Button>
                </DrawerTrigger>
                <DrawerContent
                    class="flex w-full flex-col justify-between border-border rounded-tr-2xl rounded-br-2xl ">
                    <div>

                        <DrawerHeader class="bg-card p-2 mx-2 border border-border  my-10 rounded-2xl">
                            <DrawerTitle class="flex justify-between items-center">
                                <a href="/" class="flex items-center">
                                    <ChevronsDown
                                        class="bg-gradient-to-tr border-primary from-primary/70 via-primary to-primary/70 rounded-lg size-9 mr-2 border text-white" />
                                    ShadcnVue
                                </a>
                                <DrawerClose>
                                    <Button variant="ghost">
                                        <X />
                                    </Button>
                                </DrawerClose>
                            </DrawerTitle>

                            <DrawerDescription class="sr-only">
                                Navigation Menu
                            </DrawerDescription>
                        </DrawerHeader>
                        <div class="flex flex-col items-center gap-2">
                            <Link :href="route('games.index')">games</Link>
                            <Link :href="route('home')">FAQ</Link>
                            <Link :href="route('home')">Reviews</Link>
                            <Link :href="route('home')">Contact Us</Link>
                        </div>
                        <Separator class="my-4" />
                        <div v-if="!$page.props.auth.user" class="flex flex-col gap-4 items-center">
                            <Link class="hover:underline " :href="route('login')">Login</Link>
                            <Link class="hover:underline " :href="route('register')">Sign Up</Link>
                        </div>
                        <div v-if="$page.props.auth.user" class="flex flex-col items-center">
                            <Link class="hover:underline " :href="route('dashboard')">Dashboard</Link>
                        </div>
                    </div>

                </DrawerContent>
            </Drawer>



            <!-- Desktop -->
            <nav class="hidden lg:block">
                <ul class="flex gap-4 items-center">
                    <li>
                        <Link class="hover:underline " :href="route('games.index')">games</Link>
                    </li>
                    <li>
                        <Link class="hover:underline " :href="route('login')">FAQ</Link>
                    </li>
                    <li>
                        <Link class="hover:underline " :href="route('login')">Reviews</Link>
                    </li>
                    <li>
                        <Link class="hover:underline " :href="route('login')">Contact Us</Link>
                    </li>
                </ul>
            </nav>

            <div class="hidden lg:flex pr-2 xl:pr-4 gap-4">
                <div class="flex items-center gap-2 ">
                    <span v-if="summary.total_qty">{{ formatPrice(summary.total_sum_cents) }}</span>
                    <a href="/cart" class="relative">

                        <span v-if="summary.total_qty"
                            class="absolute -top-2 -right-2 bg-primary text-white text-xs px-2 py-0.5 rounded-full">
                            {{ summary.total_qty }}
                        </span>
                        <ShoppingCart />
                    </a>

                </div>
                <div v-if="!$page.props.auth.user" class="flex  justify-between gap-4 items-center">
                    <Link class="hover:underline " :href="route('login')">Login</Link>
                    <Link class="hover:underline " :href="route('register')">Sign Up</Link>
                </div>
                <div v-if="$page.props.auth.user" class="flex justify-between items-center">
                    <Link class="hover:underline " :href="route('dashboard')">
                    <span class="inline-flex  rounded-md">
                        <button type="button"
                            class="inline-flex items-center gap-2 rounded-md border border-transparent px-3 py-2 text-sm font-medium leading-4 transition duration-150 ease-in-out hover:text-gray-100 focus:outline-none">
                            <template v-if="$page.props.auth.user.avatar">
                                <img :src="$page.props.auth.user.avatar" alt="Avatar"
                                    class="h-8 w-8 rounded-full object-cover" />
                            </template>
                            <template v-else>
                                <div
                                    class="h-8 w-8 rounded-full bg-gray-500 text-white flex items-center justify-center text-sm font-bold">
                                    {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                                </div>
                            </template>




                        </button>
                    </span>
                    </Link>

                </div>

            </div>
        </header>


        <main class="flex-grow">
            <slot />
        </main>

        <footer
            class='w-[90%] 2xl:w-[75%]  mx-auto border border-border   rounded-2xl  p-4 lg:p-8 bg-card shadow-md mb-10'>
            <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-x-12 gap-y-8">
                <div class="col-span-full xl:col-span-2">
                    <a href="#" class="flex font-bold items-center">
                        <ChevronsDown
                            class="bg-gradient-to-tr from-primary via-primary/70 to-primary rounded-lg w-9 h-9 mr-2 border border-transparent text-white" />

                        <h3 class="text-2xl">Shadcn-Vue</h3>
                    </a>
                </div>

                <div class="flex flex-col gap-2">
                    <h3 class="font-bold text-lg">Contact</h3>
                    <div>
                        <a href="#" class="opacity-60 hover:opacity-100">
                            Github
                        </a>
                    </div>

                    <div>
                        <a href="#" class="opacity-60 hover:opacity-100">
                            Twitter
                        </a>
                    </div>

                    <div>
                        <a href="#" class="opacity-60 hover:opacity-100">
                            Instagram
                        </a>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <h3 class="font-bold text-lg">Platforms</h3>
                    <div>
                        <a href="#" class="opacity-60 hover:opacity-100">
                            iOS
                        </a>
                    </div>

                    <div>
                        <a href="#" class="opacity-60 hover:opacity-100">
                            Android
                        </a>
                    </div>

                    <div>
                        <a href="#" class="opacity-60 hover:opacity-100">
                            Web
                        </a>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <h3 class="font-bold text-lg">Help</h3>
                    <div>
                        <a href="#" class="opacity-60 hover:opacity-100">
                            Contact Us
                        </a>
                    </div>

                    <div>
                        <a href="#" class="opacity-60 hover:opacity-100">
                            FAQ
                        </a>
                    </div>

                    <div>
                        <a href="#" class="opacity-60 hover:opacity-100">
                            Feedback
                        </a>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <h3 class="font-bold text-lg">Socials</h3>
                    <div>
                        <a href="#" class="opacity-60 hover:opacity-100">
                            Twitch
                        </a>
                    </div>

                    <div>
                        <a href="#" class="opacity-60 hover:opacity-100">
                            Discord
                        </a>
                    </div>

                    <div>
                        <a href="#" class="opacity-60 hover:opacity-100">
                            Dribbble
                        </a>
                    </div>
                </div>
            </div>

        </footer>
    </div>
</template>