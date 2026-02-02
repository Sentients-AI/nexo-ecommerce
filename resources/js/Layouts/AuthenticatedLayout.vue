<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import MobileNav from '@/Components/Layout/MobileNav.vue';
import CartBadge from '@/Components/Layout/CartBadge.vue';
import UserDropdown from '@/Components/Layout/UserDropdown.vue';

const page = usePage();
const flash = computed(() => page.props.flash);

const mobileNavOpen = ref(false);
const isScrolled = ref(false);

function handleScroll() {
    isScrolled.value = window.scrollY > 10;
}

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
    handleScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <div class="min-h-screen flex flex-col bg-gray-50 dark:bg-gray-900">
        <!-- Navigation -->
        <nav
            class="sticky top-0 z-30 transition-all duration-200"
            :class="[
                isScrolled
                    ? 'bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm shadow-md'
                    : 'bg-white dark:bg-gray-800 shadow'
            ]"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <!-- Left: Logo + Mobile menu button -->
                    <div class="flex items-center gap-4">
                        <!-- Mobile menu button -->
                        <button
                            @click="mobileNavOpen = true"
                            class="sm:hidden rounded-md p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <span class="sr-only">Open menu</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>

                        <!-- Logo -->
                        <Link href="/" class="flex items-center gap-2">
                            <span class="text-xl font-bold text-gray-900 dark:text-white">Store</span>
                        </Link>

                        <!-- Desktop nav links -->
                        <div class="hidden sm:flex sm:items-center sm:gap-1 sm:ml-6">
                            <Link
                                href="/products"
                                class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-700 transition-colors"
                            >
                                Products
                            </Link>
                            <Link
                                href="/orders"
                                class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-700 transition-colors"
                            >
                                Orders
                            </Link>
                        </div>
                    </div>

                    <!-- Right: Cart + User -->
                    <div class="flex items-center gap-2">
                        <CartBadge />
                        <div class="hidden sm:block sm:ml-2">
                            <UserDropdown />
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Mobile navigation -->
        <MobileNav :show="mobileNavOpen" @close="mobileNavOpen = false" />

        <!-- Flash messages -->
        <div v-if="flash.success || flash.error" class="mx-auto max-w-7xl w-full px-4 sm:px-6 lg:px-8 mt-4">
            <Transition
                enter-active-class="duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="flash.success"
                    class="rounded-lg bg-green-50 dark:bg-green-900/50 p-4 border border-green-200 dark:border-green-800"
                >
                    <div class="flex items-center gap-3">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-sm font-medium text-green-800 dark:text-green-200">
                            {{ flash.success }}
                        </p>
                    </div>
                </div>
            </Transition>
            <Transition
                enter-active-class="duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="flash.error"
                    class="rounded-lg bg-red-50 dark:bg-red-900/50 p-4 border border-red-200 dark:border-red-800"
                >
                    <div class="flex items-center gap-3">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-sm font-medium text-red-800 dark:text-red-200">
                            {{ flash.error }}
                        </p>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Main content -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-auto">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Brand -->
                    <div class="lg:col-span-1">
                        <Link href="/" class="text-xl font-bold text-gray-900 dark:text-white">
                            Store
                        </Link>
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                            Discover amazing products at great prices. Quality guaranteed.
                        </p>
                    </div>

                    <!-- Shop -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Shop</h3>
                        <ul class="mt-4 space-y-3">
                            <li>
                                <Link href="/products" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                                    All Products
                                </Link>
                            </li>
                            <li>
                                <Link href="/products?featured=1" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                                    Featured
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Account -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Account</h3>
                        <ul class="mt-4 space-y-3">
                            <li>
                                <Link href="/orders" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                                    My Orders
                                </Link>
                            </li>
                            <li>
                                <Link href="/cart" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                                    Cart
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Support -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Support</h3>
                        <ul class="mt-4 space-y-3">
                            <li>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    Contact Us
                                </span>
                            </li>
                            <li>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    Shipping Info
                                </span>
                            </li>
                            <li>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    Returns
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Bottom bar -->
                <div class="mt-12 pt-8 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                        &copy; {{ new Date().getFullYear() }} Store. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
