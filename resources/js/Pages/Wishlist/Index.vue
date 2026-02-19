<script setup lang="ts">
import { computed, onMounted, watch } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductCard from '@/Components/Products/ProductCard.vue';
import { useWishlist } from '@/Composables/useWishlist';
import { useLocale } from '@/Composables/useLocale';
import type { ProductApiResource } from '@/types/api';

interface Props {
    products: ProductApiResource[];
}

const props = defineProps<Props>();

const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user !== null);
const Layout = computed(() => isAuthenticated.value ? AuthenticatedLayout : GuestLayout);

const { wishlistIds, count: wishlistCount, removeFromWishlist, clearWishlist } = useWishlist();
const { t, localePath } = useLocale();

function loadWishlistProducts() {
    const ids = wishlistIds.value;
    if (ids.length === 0) {
        // If no wishlist IDs but we have products in props, just let it show empty
        if (props.products.length > 0) {
            router.reload({ data: { ids: '' } });
        }
        return;
    }

    const currentIds = new URLSearchParams(window.location.search).get('ids');
    const newIds = ids.join(',');

    if (currentIds !== newIds) {
        router.get(localePath('/wishlist'), { ids: newIds }, {
            preserveState: true,
            preserveScroll: true,
        });
    }
}

onMounted(() => {
    loadWishlistProducts();
});

// Reload when wishlist changes (item removed)
watch(wishlistIds, () => {
    loadWishlistProducts();
});

function handleRemove(productId: number) {
    removeFromWishlist(productId);
}

function handleClearAll() {
    clearWishlist();
}

// Filter displayed products to only those still in wishlist
const displayedProducts = computed(() => {
    return props.products.filter(p => wishlistIds.value.includes(p.id));
});
</script>

<template>
    <Head :title="t('wishlist.title')" />

    <component :is="Layout">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ t('wishlist.title') }}
                    </h1>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">
                        {{ wishlistCount }} {{ wishlistCount === 1 ? 'item' : 'items' }}
                    </p>
                </div>

                <button
                    v-if="wishlistCount > 0"
                    @click="handleClearAll"
                    class="inline-flex items-center gap-2 rounded-lg border border-red-300 dark:border-red-700 px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                    {{ t('wishlist.clear_all') }}
                </button>
            </div>

            <!-- Products grid -->
            <div v-if="displayedProducts.length > 0">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div v-for="product in displayedProducts" :key="product.id" class="relative">
                        <ProductCard :product="product" :show-quick-view="false" />

                        <!-- Remove button overlay -->
                        <button
                            @click="handleRemove(product.id)"
                            class="absolute top-2 right-2 z-10 rounded-full bg-white dark:bg-gray-800 p-2 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 shadow-md hover:shadow-lg transition-all"
                            :title="t('wishlist.remove')"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="text-center py-16 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="mx-auto h-24 w-24 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center mb-6">
                    <svg class="h-12 w-12 text-red-300 dark:text-red-700" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ t('wishlist.empty') }}
                </h3>
                <p class="mt-2 text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                    {{ t('wishlist.empty_description') }}
                </p>
                <Link
                    :href="localePath('/products')"
                    class="mt-6 inline-flex items-center rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-500 transition-colors"
                >
                    {{ t('wishlist.browse_products') }}
                </Link>
            </div>
        </div>
    </component>
</template>
