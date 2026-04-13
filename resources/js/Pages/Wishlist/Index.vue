<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
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

const { wishlistIds, count: wishlistCount, removeFromWishlist, clearWishlist, addToWishlist } = useWishlist();
const { t, localePath } = useLocale();

// Sharing state
const sharecopied = ref(false);
let shareTimeout: ReturnType<typeof setTimeout> | null = null;

// Detect if viewing a shared wishlist (URL has ?ids= but those items aren't in local storage)
const urlIds = computed((): number[] => {
    if (typeof window === 'undefined') { return []; }
    const raw = new URLSearchParams(window.location.search).get('ids') ?? '';
    return raw ? raw.split(',').map(Number).filter(Boolean) : [];
});

const isSharedView = computed(() => {
    if (urlIds.value.length === 0) { return false; }
    return urlIds.value.some(id => !wishlistIds.value.includes(id));
});

function generateShareUrl(): string {
    const ids = wishlistIds.value.join(',');
    const base = window.location.origin + localePath('/wishlist');
    return `${base}?ids=${ids}`;
}

async function shareWishlist() {
    const url = generateShareUrl();
    try {
        await navigator.clipboard.writeText(url);
    } catch {
        // Fallback: create a temporary input element
        const input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
    }
    sharecopied.value = true;
    if (shareTimeout) { clearTimeout(shareTimeout); }
    shareTimeout = setTimeout(() => { sharecopied.value = false; }, 2500);
}

function saveSharedToWishlist() {
    urlIds.value.forEach(id => {
        if (!wishlistIds.value.includes(id)) {
            addToWishlist(id);
        }
    });
}

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

// In shared view, show all products from the URL. In own view, filter to local wishlist.
const displayedProducts = computed(() => {
    if (isSharedView.value) {
        return props.products;
    }
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
                    <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
                        {{ t('wishlist.title') }}
                    </h1>
                    <p class="mt-1 text-slate-600 dark:text-slate-400">
                        {{ wishlistCount }} {{ wishlistCount === 1 ? 'item' : 'items' }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Share button (only when wishlist has items and it's the user's own list) -->
                    <button
                        v-if="wishlistCount > 0 && !isSharedView"
                        @click="shareWishlist"
                        class="inline-flex items-center gap-2 rounded-lg border border-brand-300 dark:border-brand-700 px-4 py-2 text-sm font-medium text-brand-600 dark:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition-colors"
                    >
                        <svg v-if="!sharecopied" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" />
                        </svg>
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        {{ sharecopied ? 'Link copied!' : 'Share wishlist' }}
                    </button>

                    <!-- Clear all -->
                    <button
                        v-if="wishlistCount > 0 && !isSharedView"
                        @click="handleClearAll"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-300 dark:border-red-700 px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                        {{ t('wishlist.clear_all') }}
                    </button>
                </div>
            </div>

            <!-- Shared wishlist banner -->
            <div v-if="isSharedView && props.products.length > 0" class="mb-6 flex items-center justify-between rounded-xl border border-brand-200 dark:border-brand-800/60 bg-brand-50 dark:bg-brand-900/20 px-5 py-4 gap-4">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">Someone shared this wishlist with you</p>
                        <p class="text-xs text-brand-600 dark:text-brand-400">{{ props.products.length }} {{ props.products.length === 1 ? 'item' : 'items' }} in this list</p>
                    </div>
                </div>
                <button
                    @click="saveSharedToWishlist"
                    class="shrink-0 rounded-lg bg-brand-500 hover:bg-brand-400 text-white text-sm font-semibold px-4 py-2 transition-colors"
                >
                    Save to my wishlist
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
                            class="absolute top-2 right-2 z-10 rounded-full bg-white dark:bg-navy-900/60 p-2 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 shadow-md hover:shadow-lg transition-all"
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
            <div v-else class="text-center py-16 bg-white dark:bg-navy-900/60 rounded-xl border border-slate-100 dark:border-navy-800/60">
                <div class="mx-auto h-24 w-24 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center mb-6">
                    <svg class="h-12 w-12 text-red-300 dark:text-red-700" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ t('wishlist.empty') }}
                </h3>
                <p class="mt-2 text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                    {{ t('wishlist.empty_description') }}
                </p>
                <Link
                    :href="localePath('/products')"
                    class="mt-6 inline-flex items-center rounded-lg bg-brand-500 px-6 py-3 text-sm font-semibold text-white hover:bg-brand-400 transition-colors"
                >
                    {{ t('wishlist.browse_products') }}
                </Link>
            </div>
        </div>
    </component>
</template>
