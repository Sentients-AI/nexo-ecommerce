<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductCard from '@/Components/Products/ProductCard.vue';
import type { ProductApiResource, CategoryApiResource } from '@/types/api';

interface Props {
    featuredProducts?: ProductApiResource[];
    categories?: CategoryApiResource[];
}

const props = withDefaults(defineProps<Props>(), {
    featuredProducts: () => [],
    categories: () => [],
});

const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user !== null);
const Layout = computed(() => isAuthenticated.value ? AuthenticatedLayout : GuestLayout);

const email = ref('');
const isSubscribed = ref(false);

function handleSubscribe(e: Event) {
    e.preventDefault();
    if (email.value) {
        isSubscribed.value = true;
        email.value = '';
    }
}

const benefits = [
    {
        icon: 'truck',
        title: 'Free Shipping',
        description: 'On orders over $50',
    },
    {
        icon: 'shield',
        title: 'Secure Payment',
        description: '100% secure checkout',
    },
    {
        icon: 'refresh',
        title: 'Easy Returns',
        description: '30-day return policy',
    },
    {
        icon: 'support',
        title: '24/7 Support',
        description: 'Dedicated support team',
    },
];
</script>

<template>
    <Head title="Home" />

    <component :is="Layout">
        <!-- Hero Section -->
        <section class="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-800">
            <!-- Background pattern -->
            <div class="absolute inset-0 opacity-10">
                <svg class="h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <defs>
                        <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                            <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5" />
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)" />
                </svg>
            </div>

            <div class="relative mx-auto max-w-7xl px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
                <div class="text-center">
                    <h1 class="text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl">
                        Discover Amazing
                        <span class="block text-indigo-200">Products</span>
                    </h1>
                    <p class="mx-auto mt-6 max-w-2xl text-lg text-indigo-100">
                        Shop our curated collection of high-quality products at unbeatable prices. Free shipping on orders over $50.
                    </p>
                    <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                        <Link
                            href="/products"
                            class="w-full sm:w-auto inline-flex items-center justify-center rounded-lg bg-white px-8 py-3.5 text-base font-semibold text-indigo-600 shadow-lg hover:bg-indigo-50 transition-colors"
                        >
                            Shop Now
                            <svg class="ml-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                        <Link
                            v-if="!isAuthenticated"
                            href="/register"
                            class="w-full sm:w-auto inline-flex items-center justify-center rounded-lg border-2 border-white/30 px-8 py-3.5 text-base font-semibold text-white hover:bg-white/10 transition-colors"
                        >
                            Create Account
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Wave divider -->
            <div class="absolute bottom-0 left-0 right-0">
                <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full">
                    <path d="M0 120L60 110C120 100 240 80 360 70C480 60 600 60 720 65C840 70 960 80 1080 85C1200 90 1320 90 1380 90L1440 90V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z" class="fill-gray-50 dark:fill-gray-900"/>
                </svg>
            </div>
        </section>

        <!-- Benefits Bar -->
        <section class="bg-gray-50 dark:bg-gray-900 py-8 border-b border-gray-200 dark:border-gray-800">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-6 lg:grid-cols-4">
                    <div
                        v-for="benefit in benefits"
                        :key="benefit.title"
                        class="flex items-center gap-4"
                    >
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/50">
                            <!-- Truck icon -->
                            <svg v-if="benefit.icon === 'truck'" class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                            </svg>
                            <!-- Shield icon -->
                            <svg v-else-if="benefit.icon === 'shield'" class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                            <!-- Refresh icon -->
                            <svg v-else-if="benefit.icon === 'refresh'" class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <!-- Support icon -->
                            <svg v-else class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ benefit.title }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ benefit.description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Products -->
        <section v-if="featuredProducts.length > 0" class="py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-end justify-between mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">
                            Featured Products
                        </h2>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">
                            Handpicked selections just for you
                        </p>
                    </div>
                    <Link
                        href="/products?featured=1"
                        class="hidden sm:inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-500"
                    >
                        View all
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </Link>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <ProductCard
                        v-for="product in featuredProducts.slice(0, 8)"
                        :key="product.id"
                        :product="product"
                    />
                </div>

                <div class="mt-8 text-center sm:hidden">
                    <Link
                        href="/products?featured=1"
                        class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 dark:text-indigo-400"
                    >
                        View all featured products
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Categories (if available) -->
        <section v-if="categories.length > 0" class="py-16 sm:py-24 bg-gray-50 dark:bg-gray-800/50">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">
                        Shop by Category
                    </h2>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        Browse our wide selection of categories
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <Link
                        v-for="category in categories"
                        :key="category.id"
                        :href="`/products?category=${category.slug}`"
                        class="group relative flex flex-col items-center justify-center rounded-xl bg-white dark:bg-gray-800 p-6 shadow-sm hover:shadow-md transition-all duration-200 border border-gray-200 dark:border-gray-700"
                    >
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/50 group-hover:bg-indigo-200 dark:group-hover:bg-indigo-800/50 transition-colors">
                            <svg class="h-8 w-8 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-sm font-semibold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                            {{ category.name }}
                        </h3>
                    </Link>
                </div>
            </div>
        </section>

        <!-- CTA / Browse All Products (fallback if no featured products) -->
        <section v-if="featuredProducts.length === 0" class="py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-2xl bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-16 sm:px-12 sm:py-20 text-center">
                    <h2 class="text-2xl font-bold text-white sm:text-3xl">
                        Ready to explore?
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-indigo-100">
                        Browse our complete collection of products and find exactly what you're looking for.
                    </p>
                    <Link
                        href="/products"
                        class="mt-8 inline-flex items-center justify-center rounded-lg bg-white px-8 py-3 text-base font-semibold text-indigo-600 shadow-lg hover:bg-indigo-50 transition-colors"
                    >
                        Browse Products
                    </Link>
                </div>
            </div>
        </section>

        <!-- Newsletter -->
        <section class="py-16 sm:py-24 bg-gray-900 dark:bg-gray-950">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-white sm:text-3xl">
                        Stay in the loop
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-gray-400">
                        Subscribe to our newsletter for exclusive deals, new arrivals, and special offers.
                    </p>

                    <Transition
                        enter-active-class="duration-300 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                        mode="out-in"
                    >
                        <div v-if="isSubscribed" class="mt-8">
                            <div class="inline-flex items-center gap-2 rounded-full bg-green-500/20 px-4 py-2 text-green-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                Thanks for subscribing!
                            </div>
                        </div>
                        <form v-else @submit="handleSubscribe" class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                            <input
                                v-model="email"
                                type="email"
                                required
                                placeholder="Enter your email"
                                class="w-full sm:w-80 rounded-lg border-0 bg-white/10 px-4 py-3 text-white placeholder-gray-400 focus:ring-2 focus:ring-indigo-500"
                            />
                            <button
                                type="submit"
                                class="w-full sm:w-auto rounded-lg bg-indigo-600 px-6 py-3 font-semibold text-white hover:bg-indigo-500 transition-colors"
                            >
                                Subscribe
                            </button>
                        </form>
                    </Transition>

                    <p class="mt-4 text-sm text-gray-500">
                        We respect your privacy. Unsubscribe at any time.
                    </p>
                </div>
            </div>
        </section>
    </component>
</template>
