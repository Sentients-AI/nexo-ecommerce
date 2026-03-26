<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductCard from '@/Components/Products/ProductCard.vue';
import FilterSidebar from '@/Components/Filters/FilterSidebar.vue';
import QuickViewModal from '@/Components/Products/QuickViewModal.vue';
import { useWishlist } from '@/Composables/useWishlist';
import { useRecentlyViewed } from '@/Composables/useRecentlyViewed';
import { useLocale } from '@/Composables/useLocale';
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
        min_price?: number;
        max_price?: number;
        in_stock?: boolean;
        on_sale?: boolean;
    };
}

const props = defineProps<Props>();

const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user !== null);
const Layout = computed(() => isAuthenticated.value ? AuthenticatedLayout : GuestLayout);

const { count: wishlistCount } = useWishlist();
const { getRecentProductIds } = useRecentlyViewed();
const { localePath } = useLocale();

// Filter state
const search = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || '');
const sortBy = ref('newest');
const minPrice = ref(props.filters.min_price || 0);
const maxPrice = ref(props.filters.max_price || 100000);
const inStockOnly = ref(props.filters.in_stock || false);
const onSaleOnly = ref(props.filters.on_sale || false);

// View state
const viewMode = ref<'grid' | 'list'>('grid');
const showMobileFilters = ref(false);

// Quick view modal
const quickViewProduct = ref<ProductApiResource | null>(null);
const showQuickView = ref(false);

function handleSearch() {
    applyFilters();
}

function applyFilters() {
    const params: Record<string, string | number | boolean | undefined> = {};

    if (search.value) {
        params.search = search.value;
    }
    if (selectedCategory.value) {
        params.category = selectedCategory.value;
    }
    if (sortBy.value !== 'newest') {
        params.sort = sortBy.value;
    }
    if (minPrice.value > 0) {
        params.min_price = minPrice.value;
    }
    if (maxPrice.value < 100000) {
        params.max_price = maxPrice.value;
    }
    if (inStockOnly.value) {
        params.in_stock = true;
    }
    if (onSaleOnly.value) {
        params.on_sale = true;
    }

    router.get(localePath('/products'), params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function clearFilters() {
    search.value = '';
    selectedCategory.value = '';
    sortBy.value = 'newest';
    minPrice.value = 0;
    maxPrice.value = 100000;
    inStockOnly.value = false;
    onSaleOnly.value = false;
    router.get(localePath('/products'), {}, {
        preserveState: true,
        preserveScroll: true,
    });
}

const hasActiveFilters = computed(() => {
    return search.value ||
        selectedCategory.value ||
        props.filters.featured ||
        minPrice.value > 0 ||
        maxPrice.value < 100000 ||
        inStockOnly.value ||
        onSaleOnly.value;
});

const activeFilterCount = computed(() => {
    let count = 0;
    if (search.value) count++;
    if (selectedCategory.value) count++;
    if (minPrice.value > 0 || maxPrice.value < 100000) count++;
    if (inStockOnly.value) count++;
    if (onSaleOnly.value) count++;
    return count;
});

function openQuickView(product: ProductApiResource) {
    quickViewProduct.value = product;
    showQuickView.value = true;
}

function closeQuickView() {
    showQuickView.value = false;
    quickViewProduct.value = null;
}

// Recently viewed products (from stored IDs, find matching ones in current products)
const recentlyViewedProducts = computed(() => {
    const recentIds = getRecentProductIds(4);
    return props.products.data.filter(p => recentIds.includes(p.id)).slice(0, 4);
});
</script>

<template>
    <Head title="Products" />

    <component :is="Layout">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
                            {{ filters.featured ? 'Featured Products' : 'All Products' }}
                        </h1>
                        <p class="mt-1 text-slate-600 dark:text-slate-400">
                            {{ products.total }} {{ products.total === 1 ? 'product' : 'products' }} found
                        </p>
                    </div>

                    <!-- Wishlist link (if has items) -->
                    <Link
                        v-if="wishlistCount > 0"
                        :href="localePath('/wishlist')"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors"
                    >
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                        </svg>
                        Wishlist ({{ wishlistCount }})
                    </Link>
                </div>
            </div>

            <!-- Top bar: Search + Sort + View toggle -->
            <div class="mb-6 flex flex-col sm:flex-row gap-4 items-stretch sm:items-center">
                <!-- Search -->
                <form @submit.prevent="handleSearch" class="flex-1 max-w-md">
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </div>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search products..."
                            class="block w-full rounded-lg border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-800/60 pl-10 pr-4 py-2.5 text-slate-900 dark:text-white placeholder-gray-500 focus:border-brand-500 focus:ring-brand-500 transition-colors"
                            @keyup.enter="handleSearch"
                        />
                    </div>
                </form>

                <div class="flex items-center gap-3">
                    <!-- Mobile filter button -->
                    <button
                        @click="showMobileFilters = !showMobileFilters"
                        class="lg:hidden inline-flex items-center gap-2 rounded-lg border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-800/60 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                        </svg>
                        Filters
                        <span
                            v-if="activeFilterCount > 0"
                            class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-xs font-medium text-white"
                        >
                            {{ activeFilterCount }}
                        </span>
                    </button>

                    <!-- Sort dropdown -->
                    <select
                        v-model="sortBy"
                        @change="applyFilters"
                        class="rounded-lg border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-800/60 py-2.5 text-sm text-slate-900 dark:text-white focus:border-brand-500 focus:ring-brand-500"
                    >
                        <option value="newest">Newest</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="name_asc">Name: A-Z</option>
                        <option value="popular">Most Popular</option>
                    </select>

                    <!-- View toggle -->
                    <div class="hidden sm:flex items-center rounded-lg border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-800/60 p-1">
                        <button
                            @click="viewMode = 'grid'"
                            class="p-2 rounded-md transition-colors"
                            :class="viewMode === 'grid' ? 'bg-brand-100 dark:bg-brand-900/50 text-brand-600 dark:text-brand-400' : 'text-slate-500 hover:text-gray-700 dark:hover:text-gray-300'"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                            </svg>
                        </button>
                        <button
                            @click="viewMode = 'list'"
                            class="p-2 rounded-md transition-colors"
                            :class="viewMode === 'list' ? 'bg-brand-100 dark:bg-brand-900/50 text-brand-600 dark:text-brand-400' : 'text-slate-500 hover:text-gray-700 dark:hover:text-gray-300'"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Active filters chips -->
            <div v-if="hasActiveFilters" class="mb-6 flex flex-wrap items-center gap-2">
                <span class="text-sm text-slate-500 dark:text-slate-400">Active filters:</span>

                <span
                    v-if="filters.featured"
                    class="inline-flex items-center gap-1 rounded-full bg-brand-100 dark:bg-brand-900/50 px-3 py-1 text-sm font-medium text-brand-700 dark:text-brand-300"
                >
                    Featured
                </span>

                <span
                    v-if="search"
                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-navy-800 px-3 py-1 text-sm font-medium text-slate-700 dark:text-slate-300"
                >
                    "{{ search }}"
                    <button @click="search = ''; handleSearch()" class="ml-1 hover:text-gray-900 dark:hover:text-white">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>

                <span
                    v-if="selectedCategory"
                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-navy-800 px-3 py-1 text-sm font-medium text-slate-700 dark:text-slate-300"
                >
                    {{ categories?.find(c => c.slug === selectedCategory)?.name || selectedCategory }}
                    <button @click="selectedCategory = ''; applyFilters()" class="ml-1 hover:text-gray-900 dark:hover:text-white">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>

                <span
                    v-if="minPrice > 0 || maxPrice < 100000"
                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-navy-800 px-3 py-1 text-sm font-medium text-slate-700 dark:text-slate-300"
                >
                    ${{ (minPrice / 100).toFixed(0) }} - ${{ (maxPrice / 100).toFixed(0) }}
                    <button @click="minPrice = 0; maxPrice = 100000; applyFilters()" class="ml-1 hover:text-gray-900 dark:hover:text-white">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>

                <span
                    v-if="inStockOnly"
                    class="inline-flex items-center gap-1 rounded-full bg-green-100 dark:bg-green-900/50 px-3 py-1 text-sm font-medium text-green-700 dark:text-green-300"
                >
                    In Stock
                    <button @click="inStockOnly = false; applyFilters()" class="ml-1 hover:text-green-900 dark:hover:text-green-100">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>

                <span
                    v-if="onSaleOnly"
                    class="inline-flex items-center gap-1 rounded-full bg-red-100 dark:bg-red-900/50 px-3 py-1 text-sm font-medium text-red-700 dark:text-red-300"
                >
                    On Sale
                    <button @click="onSaleOnly = false; applyFilters()" class="ml-1 hover:text-red-900 dark:hover:text-red-100">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>

                <button
                    @click="clearFilters"
                    class="text-sm font-medium text-brand-600 dark:text-brand-400 hover:text-brand-500"
                >
                    Clear all
                </button>
            </div>

            <!-- Main content area -->
            <div class="flex gap-8">
                <!-- Desktop Filter Sidebar -->
                <div class="hidden lg:block">
                    <FilterSidebar
                        :categories="categories"
                        :selected-category="selectedCategory"
                        :min-price="minPrice"
                        :max-price="maxPrice"
                        :in-stock-only="inStockOnly"
                        :on-sale-only="onSaleOnly"
                        @update:selected-category="selectedCategory = $event"
                        @update:min-price="minPrice = $event"
                        @update:max-price="maxPrice = $event"
                        @update:in-stock-only="inStockOnly = $event"
                        @update:on-sale-only="onSaleOnly = $event"
                        @apply="applyFilters"
                        @clear="clearFilters"
                    />
                </div>

                <!-- Products area -->
                <div class="flex-1 min-w-0">
                    <!-- Products grid/list -->
                    <div v-if="products.data.length > 0">
                        <!-- Grid view -->
                        <div
                            v-if="viewMode === 'grid'"
                            class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3"
                        >
                            <ProductCard
                                v-for="product in products.data"
                                :key="product.id"
                                :product="product"
                                :view-mode="viewMode"
                                @quick-view="openQuickView"
                            />
                        </div>

                        <!-- List view -->
                        <div v-else class="flex flex-col gap-4">
                            <ProductCard
                                v-for="product in products.data"
                                :key="product.id"
                                :product="product"
                                :view-mode="viewMode"
                                @quick-view="openQuickView"
                            />
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-else class="text-center py-16 bg-white dark:bg-navy-800/60 rounded-xl border border-slate-200 dark:border-navy-800">
                        <div class="mx-auto h-24 w-24 rounded-full bg-slate-100 dark:bg-navy-800 flex items-center justify-center mb-6">
                            <svg class="h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">No products found</h3>
                        <p class="mt-2 text-slate-500 dark:text-slate-400">
                            Try adjusting your search or filter criteria
                        </p>
                        <button
                            v-if="hasActiveFilters"
                            @click="clearFilters"
                            class="mt-6 inline-flex items-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-400 transition-colors"
                        >
                            Clear filters
                        </button>
                    </div>

                    <!-- Pagination -->
                    <div v-if="products.last_page > 1" class="mt-12 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Showing {{ products.from }} to {{ products.to }} of {{ products.total }} results
                        </p>

                        <nav class="flex items-center gap-2">
                            <Link
                                v-if="products.prev_page_url"
                                :href="products.prev_page_url"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-800/60 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                </svg>
                                Previous
                            </Link>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-navy-800 bg-slate-50 dark:bg-navy-900 px-4 py-2 text-sm font-medium text-slate-400 dark:text-slate-500 cursor-not-allowed"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                </svg>
                                Previous
                            </span>

                            <span class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ products.current_page }} / {{ products.last_page }}
                            </span>

                            <Link
                                v-if="products.next_page_url"
                                :href="products.next_page_url"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-800/60 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                            >
                                Next
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </Link>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-navy-800 bg-slate-50 dark:bg-navy-900 px-4 py-2 text-sm font-medium text-slate-400 dark:text-slate-500 cursor-not-allowed"
                            >
                                Next
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </span>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Filter Drawer -->
        <Teleport to="body">
            <Transition
                enter-active-class="duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showMobileFilters"
                    class="fixed inset-0 z-50 lg:hidden"
                >
                    <!-- Backdrop -->
                    <div
                        class="fixed inset-0 bg-navy-950/60"
                        @click="showMobileFilters = false"
                    />

                    <!-- Drawer -->
                    <Transition
                        enter-active-class="duration-300 ease-out"
                        enter-from-class="-translate-x-full"
                        enter-to-class="translate-x-0"
                        leave-active-class="duration-200 ease-in"
                        leave-from-class="translate-x-0"
                        leave-to-class="-translate-x-full"
                    >
                        <div
                            v-if="showMobileFilters"
                            class="fixed inset-y-0 left-0 w-full max-w-xs bg-white dark:bg-navy-800/60 shadow-xl"
                        >
                            <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-navy-800">
                                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Filters</h2>
                                <button
                                    @click="showMobileFilters = false"
                                    class="p-2 rounded-lg text-slate-400 hover:text-gray-600 hover:bg-slate-100 dark:hover:bg-navy-800"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <div class="p-4 overflow-y-auto h-full pb-32">
                                <FilterSidebar
                                    :categories="categories"
                                    :selected-category="selectedCategory"
                                    :min-price="minPrice"
                                    :max-price="maxPrice"
                                    :in-stock-only="inStockOnly"
                                    :on-sale-only="onSaleOnly"
                                    @update:selected-category="selectedCategory = $event"
                                    @update:min-price="minPrice = $event"
                                    @update:max-price="maxPrice = $event"
                                    @update:in-stock-only="inStockOnly = $event"
                                    @update:on-sale-only="onSaleOnly = $event"
                                    @apply="applyFilters(); showMobileFilters = false"
                                    @clear="clearFilters(); showMobileFilters = false"
                                />
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- Quick View Modal -->
        <QuickViewModal
            :show="showQuickView"
            :product="quickViewProduct"
            @close="closeQuickView"
        />
    </component>
</template>
