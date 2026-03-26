<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductCard from '@/Components/Products/ProductCard.vue';
import StarRating from '@/Components/Reviews/StarRating.vue';
import { useLocale } from '@/Composables/useLocale';
import type { ProductApiResource } from '@/types/api';

interface LaravelPaginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface StoreData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    total_products: number;
    total_reviews: number;
    average_rating: number | null;
}

interface Props {
    store: StoreData;
    products: LaravelPaginator<ProductApiResource>;
}

const props = defineProps<Props>();
const page = usePage();
const { t, localePath } = useLocale();

const isAuthenticated = computed(() => page.props.auth?.user !== null);
const Layout = computed(() => isAuthenticated.value ? AuthenticatedLayout : GuestLayout);

function formatPrice(cents: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}
</script>

<template>
    <Head :title="store.name" />

    <component :is="Layout">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Breadcrumb -->
            <nav class="mb-8">
                <ol class="flex flex-wrap items-center gap-2 text-sm">
                    <li>
                        <Link :href="localePath('/')" class="text-slate-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                            {{ t('nav.home') }}
                        </Link>
                    </li>
                    <li class="text-slate-400">/</li>
                    <li>
                        <span class="text-slate-500 dark:text-slate-400">{{ t('nav.stores') }}</span>
                    </li>
                    <li class="text-slate-400">/</li>
                    <li class="text-slate-900 dark:text-white font-medium">{{ store.name }}</li>
                </ol>
            </nav>

            <!-- Store header -->
            <div class="rounded-2xl bg-white dark:bg-navy-900/60 border border-slate-100 dark:border-navy-800/60 p-8 mb-8">
                <div class="flex flex-col sm:flex-row sm:items-center gap-6">
                    <!-- Store icon -->
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-brand-100 dark:bg-brand-900/50">
                        <svg class="h-10 w-10 text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                        </svg>
                    </div>

                    <div class="flex-1">
                        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ store.name }}</h1>
                        <p v-if="store.description" class="mt-2 text-slate-600 dark:text-slate-400">
                            {{ store.description }}
                        </p>

                        <!-- Store stats -->
                        <div class="mt-4 flex flex-wrap items-center gap-6">
                            <!-- Products count -->
                            <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                                <span class="font-semibold text-slate-900 dark:text-white">{{ store.total_products }}</span>
                                {{ t('stores.products') }}
                            </div>

                            <!-- Reviews count -->
                            <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                                </svg>
                                <span class="font-semibold text-slate-900 dark:text-white">{{ store.total_reviews }}</span>
                                {{ t('stores.reviews') }}
                            </div>

                            <!-- Average rating -->
                            <div v-if="store.average_rating" class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <StarRating :rating="Math.round(store.average_rating)" size="sm" />
                                <span class="font-semibold text-slate-900 dark:text-white">{{ store.average_rating }}</span>
                                {{ t('stores.avg_rating') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products heading -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">
                    {{ t('stores.products') }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ store.total_products }} {{ t('stores.products').toLowerCase() }}
                </p>
            </div>

            <!-- Products grid -->
            <div v-if="products.data.length > 0">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <ProductCard
                        v-for="product in products.data"
                        :key="product.id"
                        :product="product"
                    />
                </div>

                <!-- Pagination -->
                <div v-if="products.last_page > 1" class="mt-12 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ t('common.showing', { from: products.from ?? 0, to: products.to ?? 0, total: products.total }) }}
                    </p>

                    <nav class="flex items-center gap-2">
                        <Link
                            v-if="products.prev_page_url"
                            :href="products.prev_page_url"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-900/60 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                        >
                            {{ t('common.previous') }}
                        </Link>

                        <span class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ products.current_page }} / {{ products.last_page }}
                        </span>

                        <Link
                            v-if="products.next_page_url"
                            :href="products.next_page_url"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-900/60 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                        >
                            {{ t('common.next') }}
                        </Link>
                    </nav>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="text-center py-16 bg-white dark:bg-navy-900/60 rounded-xl border border-slate-100 dark:border-navy-800/60">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                </svg>
                <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">{{ t('products.no_results') }}</h3>
            </div>
        </div>
    </component>
</template>
