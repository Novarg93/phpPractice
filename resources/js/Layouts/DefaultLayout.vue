<script lang="ts" setup>
import { ref, computed, nextTick, watch, } from "vue";
import { usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/Components/ui/sheet'

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

import { ChevronsDown, Menu, CircleX } from "lucide-vue-next";

const { props } = usePage()
const user = computed(() => props.auth.user)






const isOpen = ref<boolean>(false);



</script>

<style scoped>
.shadow-light {
    box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.085);
}

.shadow-dark {
    box-shadow: inset 0 0 5px rgba(255, 255, 255, 0.141);
}
</style>

<template>
    <div class="flex flex-col min-h-screen">
        <header
            class='w-[90%] 2xl:w-[75%]  mx-auto border mt-10  rounded-2xl flex justify-between items-center p-2 bg-card shadow-md'>
            <Link :href="route('home')" class="font-bold text-lg flex items-center">
            <ChevronsDown
                class="bg-gradient-to-tr from-primary via-primary/70 to-primary rounded-lg w-9 h-9 mr-2 border text-white" />
            ShadcnVue
            </Link>
            <!-- Mobile -->
            <div class="flex items-center lg:hidden">
                <Sheet v-model:open="isOpen">
                    <SheetTrigger as-child>
                        <Button variant="ghost">
                            <Menu />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left"
                        class="flex w-full flex-col justify-between rounded-tr-2xl rounded-br-2xl bg-card">
                        <div>

                            <SheetHeader class="mb-4  mt-6">
                                <SheetTitle class="flex items-center">
                                    <a href="/" class="flex items-center">
                                        <ChevronsDown
                                            class="bg-gradient-to-tr from-primary/70 via-primary to-primary/70 rounded-lg size-9 mr-2 border text-white" />
                                        ShadcnVue
                                    </a>
                                </SheetTitle>
                                <SheetDescription>
                                    Navigation Menu
                                </SheetDescription>
                            </SheetHeader>
                            <div class="flex flex-col items-center gap-2">
                                <Link :href="route('home')">Catalog</Link>
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

                    </SheetContent>
                </Sheet>

            </div>

            <!-- Desktop -->
            <nav class="hidden lg:block">
                <ul class="flex gap-4 items-center">
                    <li>
                        <Link class="hover:underline " :href="route('login')">Catalog</Link>
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

            <div class="hidden lg:flex pr-2 xl:pr-4">
                <div v-if="!$page.props.auth.user" class="flex justify-between gap-8 items-center">
                    <Link class="hover:underline " :href="route('login')">Login</Link>
                    <Link class="hover:underline " :href="route('register')">Sign Up</Link>
                </div>
                <div v-if="$page.props.auth.user" class="flex justify-between items-center">
                    <Link class="hover:underline " :href="route('dashboard')">Dashboard</Link>
                </div>

            </div>
        </header>


        <main class="flex-grow">
            <slot />
        </main>

        <footer class='w-[90%] 2xl:w-[75%]  mx-auto border   rounded-2xl  p-4 lg:p-8 bg-card shadow-md mb-10'>
            <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-x-12 gap-y-8">
                <div class="col-span-full xl:col-span-2">
                    <a href="#" class="flex font-bold items-center">
                        <ChevronsDown
                            class="bg-gradient-to-tr from-primary via-primary/70 to-primary rounded-lg w-9 h-9 mr-2 border text-white" />

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