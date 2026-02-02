<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductCard from '@/Components/Products/ProductCard.vue';
import type { ProductApiResource, CategoryApiResource } from '@/types/api';

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
    first_page_url: string;
    last_page_url: string;
    path: string;
}

interface Props {
    products: LaravelPaginator<ProductApiResource>;
    categories?: CategoryApiResource[];
    filters: {
        search: string | null;
        category: string | null;
        featured: boolean;
    };
}

const props = defineProps<Props>();

const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user !== null);
const Layout = computed(() => isAuthenticated.value ? AuthenticatedLayout : GuestLayout);

const search = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || '');
const sortBy = ref('newest');

function handleSearch() {
    applyFilters();
}

function handleCategoryChange() {
    applyFilters();
}

function handleSortChange() {
    applyFilters();
}

function applyFilters() {
    const params: Record<string, string | undefined> = {};

    if (search.value) {
        params.search = search.value;
    }
    if (selectedCategory.value) {
        params.category = selectedCategory.value;
    }
    if (sortBy.value !== 'newest') {
        params.sort = sortBy.value;
    }

    router.get('/products', params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function clearFilters() {
    search.value = '';
    selectedCategory.value = '';
    sortBy.value = 'newest';
    router.get('/products', {}, {
        preserveState: true,
        preserveScroll: true,
    });
}

const hasActiveFilters = computed(() => {
    return search.value || selectedCategory.value || props.filters.featured;
});
</script>

<template>
    <Head title="Products" />

    <component :is="Layout">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    {{ filters.featured ? 'Featured Products' : 'All Products' }}
                </h1>
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    {{ products.total }} {{ products.total === 1 ? 'product' : 'products' }} found
                </p>
            </div>

            <!-- Filters bar -->
            <div class="mb-8 flex flex-col gap-4 rounded-xl bg-white dark:bg-gray-800 p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row gap-4">
                    <!-- Search -->
                    <form @submit.prevent="handleSearch" class="flex-1">
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                            </div>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search products..."
                                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 pl-10 pr-4 py-2.5 text-gray-900 dark:text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-indigo-500"
                                @keyup.enter="handleSearch"
                            />
                        </div>
                    </form>

                    <!-- Category filter -->
                    <div v-if="categories && categories.length > 0" class="w-full sm:w-48">
                        <select
                            v-model="selectedCategory"
                            @change="handleCategoryChange"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 py-2.5 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">All Categories</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.slug">
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Sort -->
                    <div class="w-full sm:w-40">
                        <select
                            v-model="sortBy"
                            @change="handleSortChange"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 py-2.5 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="newest">Newest</option>
                            <option value="price_asc">Price: Low to High</option>
                            <option value="price_desc">Price: High to Low</option>
                            <option value="name_asc">Name: A-Z</option>
                        </select>
                    </div>
                </div>

                <!-- Active filters -->
                <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Active filters:</span>

                    <span
                        v-if="filters.featured"
                        class="inline-flex items-center gap-1 rounded-full bg-indigo-100 dark:bg-indigo-900/50 px-3 py-1 text-sm font-medium text-indigo-700 dark:text-indigo-300"
                    >
                        Featured
                    </span>

                    <span
                        v-if="search"
                        class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-700 px-3 py-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        "{{ search }}"
                        <button @click="search = ''; handleSearch()" class="ml-1 hover:text-gray-900 dark:hover:text-white">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>

                    <span
                        v-if="selectedCategory"
                        class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-700 px-3 py-1 text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        {{ categories?.find(c => c.slug === selectedCategory)?.name || selectedCategory }}
                        <button @click="selectedCategory = ''; handleCategoryChange()" class="ml-1 hover:text-gray-900 dark:hover:text-white">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>

                    <button
                        @click="clearFilters"
                        class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500"
                    >
                        Clear all
                    </button>
                </div>
            </div>

            <!-- Products grid -->
            <div v-if="products.data.length > 0" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <ProductCard
                    v-for="product in products.data"
                    :key="product.id"
                    :product="product"
                />
            </div>

            <!-- Empty state -->
            <div v-else class="text-center py-16">
                <div class="mx-auto h-24 w-24 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-6">
                    <svg class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">No products found</h3>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    Try adjusting your search or filter criteria
                </p>
                <button
                    v-if="hasActiveFilters"
                    @click="clearFilters"
                    class="mt-6 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    Clear filters
                </button>
            </div>

            <!-- Pagination -->
            <div v-if="products.last_page > 1" class="mt-12 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ products.from }} to {{ products.to }} of {{ products.total }} results
                </p>

                <nav class="flex items-center gap-2">
                    <Link
                        v-if="products.prev_page_url"
                        :href="products.prev_page_url"
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                        Previous
                    </Link>
                    <span
                        v-else
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                        Previous
                    </span>

                    <span class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ products.current_page }} / {{ products.last_page }}
                    </span>

                    <Link
                        v-if="products.next_page_url"
                        :href="products.next_page_url"
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        Next
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </Link>
                    <span
                        v-else
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed"
                    >
                        Next
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </span>
                </nav>
            </div>
        </div>
    </component>
</template>
