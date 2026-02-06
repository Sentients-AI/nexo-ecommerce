<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useCart } from '@/Composables/useCart';
import { useWishlist } from '@/Composables/useWishlist';
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
const { isInWishlist, toggleWishlist } = useWishlist();
const isAdding = ref(false);
const showAddedFeedback = ref(false);
const isHovered = ref(false);

function normalizeImages(images: string | string[] | null | undefined): string[] {
    if (!images) return [];
    if (Array.isArray(images)) return images;
    if (typeof images === 'string') return [images];
    return [];
}

const productImages = computed(() => normalizeImages(props.product.images));

function formatPrice(cents: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

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
        return { text: 'Check availability', class: 'text-gray-500 dark:text-gray-400', available: true };
    }
    const available = props.product.stock.available ?? props.product.stock.quantity;
    if (available <= 0) {
        return { text: 'Out of stock', class: 'text-red-600 dark:text-red-400', available: false };
    }
    if (available <= 5) {
        return { text: `Only ${available} left`, class: 'text-yellow-600 dark:text-yellow-400', available: true };
    }
    return { text: 'In stock', class: 'text-green-600 dark:text-green-400', available: true };
});

async function handleQuickAdd(e: Event) {
    e.preventDefault();
    e.stopPropagation();

    if (isAdding.value || !stockStatus.value.available) return;

    isAdding.value = true;
    const success = await addToCart(props.product.id, 1);
    isAdding.value = false;

    if (success) {
        showAddedFeedback.value = true;
        setTimeout(() => {
            showAddedFeedback.value = false;
        }, 2000);
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

const isNew = computed(() => {
    // Consider product "new" if created within last 7 days
    // This would need actual data from backend, using placeholder logic
    return props.product.is_featured;
});
</script>

<template>
    <!-- Grid View -->
    <Link
        v-if="viewMode === 'grid'"
        :href="`/products/${product.slug}`"
        class="group relative flex flex-col rounded-xl bg-white dark:bg-gray-800 shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-200 dark:border-gray-700"
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
    >
        <!-- Image container -->
        <div class="relative aspect-square overflow-hidden bg-gray-100 dark:bg-gray-700">
            <img
                v-if="productImages.length > 0"
                :src="productImages[0]"
                :alt="product.name"
                class="h-full w-full object-cover object-center transition-transform duration-500 group-hover:scale-110"
            />
            <div
                v-else
                class="flex h-full items-center justify-center text-gray-400 dark:text-gray-500"
            >
                <svg class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>

            <!-- Badges -->
            <div class="absolute top-3 left-3 flex flex-col gap-2">
                <div
                    v-if="discountPercentage"
                    class="rounded-full bg-red-500 px-2.5 py-1 text-xs font-bold text-white shadow-sm"
                >
                    -{{ discountPercentage }}%
                </div>
                <div
                    v-if="isNew"
                    class="rounded-full bg-emerald-500 px-2.5 py-1 text-xs font-bold text-white shadow-sm"
                >
                    NEW
                </div>
                <div
                    v-if="stockStatus.text === 'Only ' + (product.stock?.available ?? 0) + ' left'"
                    class="rounded-full bg-amber-500 px-2.5 py-1 text-xs font-bold text-white shadow-sm"
                >
                    LOW STOCK
                </div>
            </div>

            <!-- Top right action buttons -->
            <div class="absolute top-3 right-3 flex flex-col gap-2">
                <!-- Wishlist button -->
                <button
                    @click="handleWishlistClick"
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-white/90 dark:bg-gray-800/90 shadow-md backdrop-blur-sm transition-all hover:scale-110"
                    :class="isInWishlist(product.id) ? 'text-red-500' : 'text-gray-600 hover:text-red-500'"
                >
                    <svg
                        class="h-5 w-5"
                        :fill="isInWishlist(product.id) ? 'currentColor' : 'none'"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                    </svg>
                </button>

                <!-- Quick view button -->
                <Transition
                    enter-active-class="duration-150 ease-out"
                    enter-from-class="opacity-0 scale-75"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="duration-100 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-75"
                >
                    <button
                        v-if="showQuickView && isHovered"
                        @click="handleQuickView"
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-white/90 dark:bg-gray-800/90 shadow-md backdrop-blur-sm text-gray-600 hover:text-indigo-600 transition-all hover:scale-110"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>
                </Transition>
            </div>

            <!-- Quick add button -->
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
                    class="absolute bottom-3 left-3 right-3 flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 disabled:opacity-70 disabled:cursor-not-allowed"
                >
                    <template v-if="isAdding">
                        <Spinner size="sm" color="white" />
                        Adding...
                    </template>
                    <template v-else-if="showAddedFeedback">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Added!
                    </template>
                    <template v-else>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add to Cart
                    </template>
                </button>
            </Transition>
        </div>

        <!-- Content -->
        <div class="flex flex-1 flex-col p-4">
            <!-- Category -->
            <p v-if="product.category" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wide">
                {{ product.category.name }}
            </p>

            <!-- Name -->
            <h3 class="mt-1 text-sm font-semibold text-gray-900 dark:text-white line-clamp-2 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                {{ product.name }}
            </h3>

            <!-- Spacer -->
            <div class="flex-1" />

            <!-- Price and stock -->
            <div class="mt-3 flex items-end justify-between">
                <div class="flex items-baseline gap-2">
                    <span class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ formatPrice(effectivePrice) }}
                    </span>
                    <span
                        v-if="product.sale_price"
                        class="text-sm text-gray-400 line-through"
                    >
                        {{ formatPrice(product.price_cents) }}
                    </span>
                </div>
                <span :class="['text-xs font-medium', stockStatus.class]">
                    {{ stockStatus.text }}
                </span>
            </div>
        </div>
    </Link>

    <!-- List View -->
    <Link
        v-else
        :href="`/products/${product.slug}`"
        class="group flex rounded-xl bg-white dark:bg-gray-800 shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden border border-gray-200 dark:border-gray-700"
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
    >
        <!-- Image -->
        <div class="relative w-48 shrink-0 overflow-hidden bg-gray-100 dark:bg-gray-700">
            <img
                v-if="productImages.length > 0"
                :src="productImages[0]"
                :alt="product.name"
                class="h-full w-full object-cover object-center transition-transform duration-300 group-hover:scale-105"
            />
            <div
                v-else
                class="flex h-full items-center justify-center text-gray-400 dark:text-gray-500"
            >
                <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>

            <!-- Badges -->
            <div class="absolute top-2 left-2 flex flex-wrap gap-1">
                <div
                    v-if="discountPercentage"
                    class="rounded-full bg-red-500 px-2 py-0.5 text-xs font-bold text-white shadow-sm"
                >
                    -{{ discountPercentage }}%
                </div>
                <div
                    v-if="isNew"
                    class="rounded-full bg-emerald-500 px-2 py-0.5 text-xs font-bold text-white shadow-sm"
                >
                    NEW
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="flex flex-1 flex-col p-4">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <!-- Category -->
                    <p v-if="product.category" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wide">
                        {{ product.category.name }}
                    </p>

                    <!-- Name -->
                    <h3 class="mt-1 text-base font-semibold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                        {{ product.name }}
                    </h3>

                    <!-- Description -->
                    <p v-if="product.short_description" class="mt-2 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                        {{ product.short_description }}
                    </p>
                </div>

                <!-- Wishlist button -->
                <button
                    @click="handleWishlistClick"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-gray-200 dark:border-gray-600 transition-all hover:scale-110"
                    :class="isInWishlist(product.id) ? 'text-red-500 border-red-200' : 'text-gray-400 hover:text-red-500 hover:border-red-200'"
                >
                    <svg
                        class="h-5 w-5"
                        :fill="isInWishlist(product.id) ? 'currentColor' : 'none'"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                    </svg>
                </button>
            </div>

            <div class="flex-1" />

            <!-- Bottom row -->
            <div class="mt-4 flex items-center justify-between gap-4">
                <!-- Price -->
                <div class="flex items-baseline gap-2">
                    <span class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ formatPrice(effectivePrice) }}
                    </span>
                    <span
                        v-if="product.sale_price"
                        class="text-sm text-gray-400 line-through"
                    >
                        {{ formatPrice(product.price_cents) }}
                    </span>
                </div>

                <!-- Stock + Actions -->
                <div class="flex items-center gap-3">
                    <span :class="['text-xs font-medium', stockStatus.class]">
                        {{ stockStatus.text }}
                    </span>

                    <button
                        v-if="showQuickView"
                        @click="handleQuickView"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 dark:border-gray-600 text-gray-500 hover:text-indigo-600 hover:border-indigo-300 transition-colors"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>

                    <button
                        v-if="showQuickAdd && stockStatus.available"
                        @click="handleQuickAdd"
                        :disabled="isAdding || loading"
                        class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-70 disabled:cursor-not-allowed transition-colors"
                    >
                        <template v-if="isAdding">
                            <Spinner size="sm" color="white" />
                        </template>
                        <template v-else-if="showAddedFeedback">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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
