<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useCart } from '@/Composables/useCart';
import type { ProductApiResource } from '@/types/api';
import Spinner from '@/Components/UI/Spinner.vue';

interface Props {
    product: ProductApiResource;
    showQuickAdd?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showQuickAdd: true,
});

const { addToCart, loading } = useCart();
const isAdding = ref(false);
const showAddedFeedback = ref(false);

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
</script>

<template>
    <Link
        :href="`/products/${product.slug}`"
        class="group relative flex flex-col rounded-xl bg-white dark:bg-gray-800 shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden border border-gray-200 dark:border-gray-700"
    >
        <!-- Image container -->
        <div class="relative aspect-square overflow-hidden bg-gray-100 dark:bg-gray-700">
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
                <svg class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>

            <!-- Sale badge -->
            <div
                v-if="discountPercentage"
                class="absolute top-3 left-3 rounded-full bg-red-500 px-2.5 py-1 text-xs font-bold text-white shadow-sm"
            >
                -{{ discountPercentage }}%
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
                    v-if="showQuickAdd && stockStatus.available"
                    @click="handleQuickAdd"
                    :disabled="isAdding || loading"
                    class="absolute bottom-3 left-3 right-3 flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg opacity-0 group-hover:opacity-100 transition-all duration-200 hover:bg-indigo-500 disabled:opacity-70 disabled:cursor-not-allowed"
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
</template>
