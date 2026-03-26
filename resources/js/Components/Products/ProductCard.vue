<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useCart } from '@/Composables/useCart';
import { useCurrency } from '@/Composables/useCurrency';
import { useWishlist } from '@/Composables/useWishlist';
import { useLocale } from '@/Composables/useLocale';
import type { ProductApiResource } from '@/types/api';
import Spinner from '@/Components/UI/Spinner.vue';

interface Props {
    product: ProductApiResource;
    showQuickAdd?: boolean;
    showQuickView?: boolean;
    viewMode?: 'grid' | 'list';
}

const props = withDefaults(defineProps<Props>(), {
    showQuickAdd: true,
    showQuickView: true,
    viewMode: 'grid',
});

const emit = defineEmits<{
    quickView: [product: ProductApiResource];
}>();

const { addToCart, loading } = useCart();
const { formatPrice } = useCurrency();
const { isInWishlist, toggleWishlist } = useWishlist();
const { localePath } = useLocale();
const isAdding = ref(false);
const showAddedFeedback = ref(false);
const isHovered = ref(false);

function normalizeImages(images: string | string[] | null | undefined): string[] {
    if (!images) { return []; }
    if (Array.isArray(images)) { return images; }
    if (typeof images === 'string') { return [images]; }
    return [];
}

const productImages = computed(() => normalizeImages(props.product.images));


const effectivePrice = computed(() => {
    return props.product.sale_price ?? props.product.price_cents;
});

const discountPercentage = computed(() => {
    if (!props.product.sale_price || props.product.sale_price >= props.product.price_cents) {
        return null;
    }
    return Math.round(((props.product.price_cents - props.product.sale_price) / props.product.price_cents) * 100);
});

const stockStatus = computed(() => {
    if (!props.product.stock) {
        return { text: 'Check availability', class: 'text-slate-400 dark:text-navy-400', available: true };
    }
    const available = props.product.stock.available ?? props.product.stock.quantity;
    if (available <= 0) {
        return { text: 'Out of stock', class: 'text-red-500 dark:text-red-400', available: false };
    }
    if (available <= 5) {
        return { text: `Only ${available} left`, class: 'text-amber-500 dark:text-amber-400', available: true };
    }
    return { text: 'In stock', class: 'text-accent-600 dark:text-accent-400', available: true };
});

async function handleQuickAdd(e: Event) {
    e.preventDefault();
    e.stopPropagation();

    if (isAdding.value || !stockStatus.value.available) { return; }

    isAdding.value = true;
    const success = await addToCart(props.product.id, 1);
    isAdding.value = false;

    if (success) {
        showAddedFeedback.value = true;
        setTimeout(() => { showAddedFeedback.value = false; }, 2000);
    }
}

function handleWishlistClick(e: Event) {
    e.preventDefault();
    e.stopPropagation();
    toggleWishlist(props.product.id);
}

function handleQuickView(e: Event) {
    e.preventDefault();
    e.stopPropagation();
    emit('quickView', props.product);
}

const isNew = computed(() => props.product.is_featured);
</script>

<template>
    <!-- ── GRID VIEW ─────────────────────────────────────────────── -->
    <Link
        v-if="viewMode === 'grid'"
        :href="localePath(`/products/${product.slug}`)"
        class="group relative flex flex-col rounded-2xl bg-white dark:bg-navy-900/60 border border-slate-100 dark:border-navy-800/60 overflow-hidden transition-all duration-300 hover:shadow-lg hover:shadow-slate-200/60 dark:hover:shadow-navy-950 hover:-translate-y-0.5"
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
    >
        <!-- Image -->
        <div class="relative aspect-square overflow-hidden bg-slate-50 dark:bg-navy-800/60">
            <img
                v-if="productImages.length > 0"
                :src="productImages[0]"
                :alt="product.name"
                class="h-full w-full object-cover object-center transition-transform duration-500 group-hover:scale-105"
            />
            <div
                v-else
                class="flex h-full items-center justify-center"
            >
                <svg class="h-16 w-16 text-slate-200 dark:text-navy-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>

            <!-- Badges -->
            <div class="absolute top-3 left-3 flex flex-col gap-1.5">
                <span v-if="discountPercentage" class="rounded-lg bg-red-500 px-2.5 py-0.5 text-xs font-bold text-white shadow-sm">
                    -{{ discountPercentage }}%
                </span>
                <span v-if="isNew" class="rounded-lg bg-accent-500 px-2.5 py-0.5 text-xs font-bold text-white shadow-sm">
                    NEW
                </span>
                <span
                    v-if="product.stock && (product.stock.available ?? product.stock.quantity) > 0 && (product.stock.available ?? product.stock.quantity) <= 5"
                    class="rounded-lg bg-amber-500 px-2.5 py-0.5 text-xs font-bold text-white shadow-sm"
                >
                    LOW STOCK
                </span>
            </div>

            <!-- Wishlist -->
            <button
                @click="handleWishlistClick"
                class="absolute top-3 right-3 flex h-8 w-8 items-center justify-center rounded-xl bg-white/90 dark:bg-navy-800/90 shadow-sm backdrop-blur-sm transition-all hover:scale-110"
                :class="isInWishlist(product.id) ? 'text-red-500' : 'text-slate-400 hover:text-red-500'"
            >
                <svg
                    class="h-4 w-4"
                    :fill="isInWishlist(product.id) ? 'currentColor' : 'none'"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                </svg>
            </button>

            <!-- Quick view on hover -->
            <Transition
                enter-active-class="duration-150 ease-out"
                enter-from-class="opacity-0 scale-90"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="duration-100 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-90"
            >
                <button
                    v-if="showQuickView && isHovered"
                    @click="handleQuickView"
                    class="absolute top-12 right-3 flex h-8 w-8 items-center justify-center rounded-xl bg-white/90 dark:bg-navy-800/90 shadow-sm backdrop-blur-sm text-slate-400 hover:text-brand-500 dark:hover:text-brand-400 transition-all hover:scale-110"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </button>
            </Transition>

            <!-- Quick add CTA (slides up on hover) -->
            <Transition
                enter-active-class="duration-200 ease-out"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <button
                    v-if="showQuickAdd && stockStatus.available && isHovered"
                    @click="handleQuickAdd"
                    :disabled="isAdding || loading"
                    class="absolute bottom-3 left-3 right-3 flex items-center justify-center gap-2 rounded-xl bg-brand-500 hover:bg-brand-400 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/30 transition-all disabled:opacity-70 disabled:cursor-not-allowed"
                >
                    <template v-if="isAdding">
                        <Spinner size="sm" color="white" />
                        Adding...
                    </template>
                    <template v-else-if="showAddedFeedback">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Added to cart!
                    </template>
                    <template v-else>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add to Cart
                    </template>
                </button>
            </Transition>
        </div>

        <!-- Content -->
        <div class="flex flex-1 flex-col p-4">
            <p v-if="product.category" class="text-xs font-semibold text-brand-500 dark:text-brand-400 uppercase tracking-wide">
                {{ product.category.name }}
            </p>
            <h3 class="mt-1 text-sm font-semibold text-slate-900 dark:text-white line-clamp-2 group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors leading-snug">
                {{ product.name }}
            </h3>
            <div class="flex-1" />
            <div class="mt-3 flex items-center justify-between gap-2">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-base font-bold text-slate-900 dark:text-white">
                        {{ formatPrice(effectivePrice) }}
                    </span>
                    <span v-if="product.sale_price" class="text-xs text-slate-400 line-through">
                        {{ formatPrice(product.price_cents) }}
                    </span>
                </div>
                <span :class="['text-xs font-medium', stockStatus.class]">
                    {{ stockStatus.text }}
                </span>
            </div>
        </div>
    </Link>

    <!-- ── LIST VIEW ──────────────────────────────────────────────── -->
    <Link
        v-else
        :href="localePath(`/products/${product.slug}`)"
        class="group flex rounded-2xl bg-white dark:bg-navy-900/60 border border-slate-100 dark:border-navy-800/60 overflow-hidden transition-all duration-300 hover:shadow-md hover:shadow-slate-200/60 dark:hover:shadow-navy-950 hover:-translate-y-0.5"
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
    >
        <!-- Image -->
        <div class="relative w-44 shrink-0 overflow-hidden bg-slate-50 dark:bg-navy-800/60">
            <img
                v-if="productImages.length > 0"
                :src="productImages[0]"
                :alt="product.name"
                class="h-full w-full object-cover object-center transition-transform duration-300 group-hover:scale-105"
            />
            <div v-else class="flex h-full items-center justify-center">
                <svg class="h-12 w-12 text-slate-200 dark:text-navy-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <div class="absolute top-2 left-2 flex flex-wrap gap-1">
                <span v-if="discountPercentage" class="rounded-lg bg-red-500 px-2 py-0.5 text-xs font-bold text-white">-{{ discountPercentage }}%</span>
                <span v-if="isNew" class="rounded-lg bg-accent-500 px-2 py-0.5 text-xs font-bold text-white">NEW</span>
            </div>
        </div>

        <!-- Content -->
        <div class="flex flex-1 flex-col p-4">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <p v-if="product.category" class="text-xs font-semibold text-brand-500 dark:text-brand-400 uppercase tracking-wide">
                        {{ product.category.name }}
                    </p>
                    <h3 class="mt-1 text-base font-semibold text-slate-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                        {{ product.name }}
                    </h3>
                    <p v-if="product.short_description" class="mt-1.5 text-sm text-slate-500 dark:text-navy-400 line-clamp-2">
                        {{ product.short_description }}
                    </p>
                </div>
                <button
                    @click="handleWishlistClick"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-slate-200 dark:border-navy-700 transition-all hover:scale-110"
                    :class="isInWishlist(product.id) ? 'text-red-500 border-red-200 dark:border-red-900/50' : 'text-slate-400 hover:text-red-500'"
                >
                    <svg class="h-4 w-4" :fill="isInWishlist(product.id) ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                    </svg>
                </button>
            </div>
            <div class="flex-1" />
            <div class="mt-4 flex items-center justify-between gap-4">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-xl font-bold text-slate-900 dark:text-white">{{ formatPrice(effectivePrice) }}</span>
                    <span v-if="product.sale_price" class="text-sm text-slate-400 line-through">{{ formatPrice(product.price_cents) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span :class="['text-xs font-medium', stockStatus.class]">{{ stockStatus.text }}</span>
                    <button
                        v-if="showQuickView"
                        @click="handleQuickView"
                        class="flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 dark:border-navy-700 text-slate-400 hover:text-brand-500 hover:border-brand-300 dark:hover:border-brand-800/50 transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>
                    <button
                        v-if="showQuickAdd && stockStatus.available"
                        @click="handleQuickAdd"
                        :disabled="isAdding || loading"
                        class="flex items-center gap-2 rounded-xl bg-brand-500 hover:bg-brand-400 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all disabled:opacity-70 disabled:cursor-not-allowed"
                    >
                        <Spinner v-if="isAdding" size="sm" color="white" />
                        <template v-else-if="showAddedFeedback">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            Added!
                        </template>
                        <template v-else>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Add
                        </template>
                    </button>
                </div>
            </div>
        </div>
    </Link>
</template>
