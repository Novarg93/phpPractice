<script lang="ts" setup>
import { ref, onMounted, nextTick, watch, } from "vue";
import { usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3';
import { useCartSummary } from '@/composables/useCartSummary'
import { User as UserIcon } from "lucide-vue-next"
import axios from 'axios'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/Components/ui/drawer'
import { Toaster } from '@/Components/ui/sonner'
import 'vue-sonner/style.css';
import { Button } from "@/Components/ui/button";
import { Separator } from "@/Components/ui/separator";
import { ShoppingCart } from "lucide-vue-next";
import { ChevronsDown, Menu, X } from "lucide-vue-next";
import GlobalSearch from '@/Components/GlobalSearch.vue'

const { summary, loadSummary } = useCartSummary()

function formatPrice(cents: number) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100)
}


const legalPages = usePage().props.legalPages as Array<{
    id: number
    name: string
    code: string
    url: string
}>

const user = usePage().props.auth.user
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
                            <Link :href="route('games.index')">Games</Link>
                            <Link :href="route('posts.index')">Blog</Link>
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
                        <Link class="hover:underline " :href="route('games.index')">Games</Link>
                    </li>
                    <li>
                        <Link class="hover:underline " :href="route('posts.index')">Blog</Link>
                    </li>
                    <li>
                        <Link class="hover:underline " :href="route('login')">Reviews</Link>
                    </li>
                    <li>
                        <Link class="hover:underline " :href="route('contact.show')">Contact Us</Link>
                    </li>
                    <GlobalSearch />
                    
                </ul>
            </nav>

            
            
            <div class="hidden lg:flex pr-2 xl:pr-4 gap-6">
                
                <div class="flex items-center gap-4 ">
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
                    <DropdownMenu>
                        <DropdownMenuTrigger>
                            
                            <template v-if="user?.avatar_url">
                                <img :src="user.avatar_url" alt="User avatar"
                                    class="h-8 w-8 rounded-full object-cover" @error="$event.target.src = ''" />
                            </template>
                            <template v-else>
                                <div
                                    class="h-8 w-8 items-center justify-center   flex ">
                                    <UserIcon  />
                                </div>
                            </template>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="border border-border">

                            <DropdownMenuLabel>My Account</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <Link :href="route('dashboard')">
                            <DropdownMenuItem>
                                Dashboard


                            </DropdownMenuItem>
                            </Link>
                            <Link :href="route('orders.index')">
                            <DropdownMenuItem>
                                Orders

                            </DropdownMenuItem>
                            </Link>
                            <Link :href="route('profile.edit')">
                            <DropdownMenuItem>
                                Settings

                            </DropdownMenuItem>
                            </Link>
                            <Link class="w-full" :href="route('logout')" method="post" as="button">
                            <DropdownMenuItem class="w-full">
                                Logout

                            </DropdownMenuItem>
                            </Link>
                        </DropdownMenuContent>
                    </DropdownMenu>


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
                    <h3 class="font-bold text-lg">Legal</h3>
                    <div v-for="p in legalPages" :key="p.id">
                        <Link :href="p.url" class="opacity-60 hover:opacity-100">
                        {{ p.name }}
                        </Link>
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
    <Toaster theme="dark" rich-colors :visible-toasts="2"  />
</template>