<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import Modal from '@/Components/UI/Modal.vue';
import Spinner from '@/Components/UI/Spinner.vue';
import QuantityStepper from '@/Components/UI/QuantityStepper.vue';
import { useCart } from '@/Composables/useCart';
import { useCurrency } from '@/Composables/useCurrency';
import { useWishlist } from '@/Composables/useWishlist';
import type { ProductApiResource } from '@/types/api';

interface Props {
    show: boolean;
    product: ProductApiResource | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    close: [];
}>();

const { addToCart, loading: cartLoading } = useCart();
const { formatPrice } = useCurrency();
const { isInWishlist, toggleWishlist } = useWishlist();

const quantity = ref(1);
const currentImageIndex = ref(0);
const isAdding = ref(false);
const showAddedFeedback = ref(false);

// Reset state when product changes
watch(() => props.product, () => {
    quantity.value = 1;
    currentImageIndex.value = 0;
    showAddedFeedback.value = false;
});

function normalizeImages(images: string | string[] | null | undefined): string[] {
    if (!images) return [];
    if (Array.isArray(images)) return images;
    if (typeof images === 'string') return [images];
    return [];
}

const productImages = computed(() => {
    if (!props.product) return [];
    return normalizeImages(props.product.images);
});


const effectivePrice = computed(() => {
    if (!props.product) return 0;
    return props.product.sale_price ?? props.product.price_cents;
});

const discountPercentage = computed(() => {
    if (!props.product?.sale_price || props.product.sale_price >= props.product.price_cents) {
        return null;
    }
    return Math.round(((props.product.price_cents - props.product.sale_price) / props.product.price_cents) * 100);
});

const stockStatus = computed(() => {
    if (!props.product?.stock) {
        return { text: 'Check availability', class: 'text-gray-500', available: true };
    }
    const available = props.product.stock.available ?? props.product.stock.quantity;
    if (available <= 0) {
        return { text: 'Out of stock', class: 'text-red-600', available: false };
    }
    if (available <= 5) {
        return { text: `Only ${available} left!`, class: 'text-yellow-600', available: true };
    }
    return { text: 'In stock', class: 'text-green-600', available: true };
});

const totalPrice = computed(() => {
    return effectivePrice.value * quantity.value;
});

function nextImage() {
    if (productImages.value.length > 0) {
        currentImageIndex.value = (currentImageIndex.value + 1) % productImages.value.length;
    }
}

function prevImage() {
    if (productImages.value.length > 0) {
        currentImageIndex.value = currentImageIndex.value === 0
            ? productImages.value.length - 1
            : currentImageIndex.value - 1;
    }
}

async function handleAddToCart() {
    if (!props.product || isAdding.value || !stockStatus.value.available) return;

    isAdding.value = true;
    const success = await addToCart(props.product.id, quantity.value);
    isAdding.value = false;

    if (success) {
        showAddedFeedback.value = true;
        setTimeout(() => {
            showAddedFeedback.value = false;
            emit('close');
        }, 1500);
    }
}

function handleToggleWishlist() {
    if (props.product) {
        toggleWishlist(props.product.id);
    }
}
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div v-if="product" class="flex flex-col md:flex-row">
            <!-- Image Section -->
            <div class="relative w-full md:w-1/2 bg-gray-100 dark:bg-gray-700">
                <div class="aspect-square relative overflow-hidden">
                    <img
                        v-if="productImages.length > 0"
                        :src="productImages[currentImageIndex]"
                        :alt="product.name"
                        class="h-full w-full object-cover object-center"
                    />
                    <div
                        v-else
                        class="flex h-full items-center justify-center text-gray-400"
                    >
                        <svg class="h-24 w-24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>

                    <!-- Sale badge -->
                    <div
                        v-if="discountPercentage"
                        class="absolute top-4 left-4 rounded-full bg-red-500 px-3 py-1.5 text-sm font-bold text-white shadow-lg"
                    >
                        -{{ discountPercentage }}% OFF
                    </div>

                    <!-- Image navigation -->
                    <template v-if="productImages.length > 1">
                        <button
                            @click="prevImage"
                            class="absolute left-2 top-1/2 -translate-y-1/2 flex h-10 w-10 items-center justify-center rounded-full bg-white/90 shadow-md hover:bg-white transition-colors"
                        >
                            <svg class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </button>
                        <button
                            @click="nextImage"
                            class="absolute right-2 top-1/2 -translate-y-1/2 flex h-10 w-10 items-center justify-center rounded-full bg-white/90 shadow-md hover:bg-white transition-colors"
                        >
                            <svg class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </template>
                </div>

                <!-- Thumbnail strip -->
                <div v-if="productImages.length > 1" class="flex gap-2 p-3 overflow-x-auto">
                    <button
                        v-for="(img, idx) in productImages"
                        :key="idx"
                        @click="currentImageIndex = idx"
                        class="shrink-0 h-16 w-16 rounded-lg overflow-hidden border-2 transition-colors"
                        :class="idx === currentImageIndex ? 'border-indigo-500' : 'border-transparent hover:border-gray-300'"
                    >
                        <img :src="img" :alt="`${product.name} - Image ${idx + 1}`" class="h-full w-full object-cover" />
                    </button>
                </div>
            </div>

            <!-- Content Section -->
            <div class="w-full md:w-1/2 p-6 flex flex-col">
                <!-- Close button -->
                <button
                    @click="emit('close')"
                    class="absolute top-4 right-4 p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Category -->
                <p v-if="product.category" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wide">
                    {{ product.category.name }}
                </p>

                <!-- Title -->
                <h2 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                    {{ product.name }}
                </h2>

                <!-- Price -->
                <div class="mt-4 flex items-baseline gap-3">
                    <span class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ formatPrice(effectivePrice) }}
                    </span>
                    <span
                        v-if="product.sale_price"
                        class="text-lg text-gray-400 line-through"
                    >
                        {{ formatPrice(product.price_cents) }}
                    </span>
                </div>

                <!-- Stock status -->
                <div class="mt-3 flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full" :class="stockStatus.available ? 'bg-green-500' : 'bg-red-500'" />
                    <span :class="['text-sm font-medium', stockStatus.class]">
                        {{ stockStatus.text }}
                    </span>
                </div>

                <!-- Description -->
                <p v-if="product.short_description || product.description" class="mt-4 text-sm text-gray-600 dark:text-gray-400 line-clamp-3">
                    {{ product.short_description || product.description }}
                </p>

                <div class="flex-1" />

                <!-- Quantity and Actions -->
                <div class="mt-6 space-y-4">
                    <!-- Quantity -->
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Quantity:</span>
                        <QuantityStepper
                            v-model="quantity"
                            :min="1"
                            :max="product.stock?.available || 99"
                        />
                        <span class="text-lg font-semibold text-gray-900 dark:text-white ml-auto">
                            {{ formatPrice(totalPrice) }}
                        </span>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex gap-3">
                        <button
                            @click="handleAddToCart"
                            :disabled="isAdding || cartLoading || !stockStatus.available"
                            class="flex-1 flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                        >
                            <template v-if="isAdding">
                                <Spinner size="sm" color="white" />
                                Adding...
                            </template>
                            <template v-else-if="showAddedFeedback">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                Added to Cart!
                            </template>
                            <template v-else-if="!stockStatus.available">
                                Out of Stock
                            </template>
                            <template v-else>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121 0 2.083-.79 2.31-1.886l1.54-7.442a1.125 1.125 0 00-1.094-1.381H6.744" />
                                </svg>
                                Add to Cart
                            </template>
                        </button>

                        <button
                            @click="handleToggleWishlist"
                            class="flex items-center justify-center rounded-lg border-2 px-4 py-3 transition-all"
                            :class="isInWishlist(product.id)
                                ? 'border-red-500 bg-red-50 dark:bg-red-900/20 text-red-500'
                                : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-red-300 hover:text-red-500'"
                        >
                            <svg
                                class="h-6 w-6"
                                :fill="isInWishlist(product.id) ? 'currentColor' : 'none'"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                            </svg>
                        </button>
                    </div>

                    <!-- View full details link -->
                    <Link
                        :href="`/products/${product.slug}`"
                        class="block text-center text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 transition-colors"
                    >
                        View Full Details →
                    </Link>
                </div>
            </div>
        </div>
    </Modal>
</template>
