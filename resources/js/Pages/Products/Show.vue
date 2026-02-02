<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductCard from '@/Components/Products/ProductCard.vue';
import ImageLightbox from '@/Components/Products/ImageLightbox.vue';
import QuantityStepper from '@/Components/UI/QuantityStepper.vue';
import Spinner from '@/Components/UI/Spinner.vue';
import { useCart } from '@/Composables/useCart';
import type { ProductApiResource } from '@/types/api';

interface Props {
    product: ProductApiResource;
    relatedProducts: ProductApiResource[];
}

const props = defineProps<Props>();

const page = usePage();
const { addToCart, loading: cartLoading, error: cartError } = useCart();

const quantity = ref(1);
const selectedImage = ref(0);
const addedToCart = ref(false);
const lightboxOpen = ref(false);
const activeTab = ref<'description' | 'specifications' | 'reviews'>('description');

const isAuthenticated = computed(() => page.props.auth?.user !== null);
const Layout = computed(() => isAuthenticated.value ? AuthenticatedLayout : GuestLayout);

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
        return { text: `Only ${available} left in stock - order soon`, class: 'text-yellow-600 dark:text-yellow-400', available: true };
    }
    return { text: 'In stock', class: 'text-green-600 dark:text-green-400', available: true };
});

const maxQuantity = computed(() => {
    if (!props.product.stock) {
        return 10;
    }
    const available = props.product.stock.available ?? props.product.stock.quantity ?? 10;
    return Math.max(1, Math.min(available, 10));
});

async function handleAddToCart() {
    addedToCart.value = false;
    const success = await addToCart(props.product.id, quantity.value);
    if (success) {
        addedToCart.value = true;
        setTimeout(() => {
            addedToCart.value = false;
        }, 3000);
    }
}

function openLightbox(index: number) {
    selectedImage.value = index;
    lightboxOpen.value = true;
}

const trustBadges = [
    { icon: 'shield', text: 'Secure Checkout' },
    { icon: 'truck', text: 'Free Shipping Over $50' },
    { icon: 'refresh', text: '30-Day Returns' },
];
</script>

<template>
    <Head :title="product.name" />

    <component :is="Layout">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Breadcrumb -->
            <nav class="mb-8">
                <ol class="flex flex-wrap items-center gap-2 text-sm">
                    <li>
                        <Link href="/" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                            Home
                        </Link>
                    </li>
                    <li class="text-gray-400">/</li>
                    <li>
                        <Link href="/products" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                            Products
                        </Link>
                    </li>
                    <li v-if="product.category" class="text-gray-400">/</li>
                    <li v-if="product.category">
                        <Link :href="`/products?category=${product.category.slug}`" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                            {{ product.category.name }}
                        </Link>
                    </li>
                    <li class="text-gray-400">/</li>
                    <li class="text-gray-900 dark:text-white font-medium truncate">{{ product.name }}</li>
                </ol>
            </nav>

            <div class="lg:grid lg:grid-cols-2 lg:gap-x-12">
                <!-- Image gallery -->
                <div class="lg:row-span-3">
                    <!-- Main image -->
                    <div
                        class="relative aspect-square overflow-hidden rounded-2xl bg-gray-100 dark:bg-gray-800 cursor-zoom-in group"
                        @click="openLightbox(selectedImage)"
                    >
                        <img
                            v-if="productImages.length > 0"
                            :src="productImages[selectedImage]"
                            :alt="product.name"
                            class="h-full w-full object-cover object-center transition-transform duration-300 group-hover:scale-105"
                        />
                        <div
                            v-else
                            class="flex h-full items-center justify-center text-gray-400 dark:text-gray-500"
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

                        <!-- Zoom hint -->
                        <div class="absolute bottom-4 right-4 flex items-center gap-1.5 rounded-full bg-black/50 px-3 py-1.5 text-xs text-white opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" />
                            </svg>
                            Click to zoom
                        </div>
                    </div>

                    <!-- Thumbnails -->
                    <div
                        v-if="productImages.length > 1"
                        class="mt-4 grid grid-cols-4 gap-3"
                    >
                        <button
                            v-for="(image, index) in productImages"
                            :key="index"
                            @click="selectedImage = index"
                            class="aspect-square overflow-hidden rounded-lg transition-all"
                            :class="[
                                selectedImage === index
                                    ? 'ring-2 ring-indigo-500 ring-offset-2 dark:ring-offset-gray-900'
                                    : 'opacity-60 hover:opacity-100'
                            ]"
                        >
                            <img :src="image" :alt="`${product.name} image ${index + 1}`" class="h-full w-full object-cover" />
                        </button>
                    </div>
                </div>

                <!-- Product info -->
                <div class="mt-10 lg:mt-0">
                    <!-- Category -->
                    <p v-if="product.category" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wide">
                        {{ product.category.name }}
                    </p>

                    <!-- Name -->
                    <h1 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white sm:text-4xl">
                        {{ product.name }}
                    </h1>

                    <!-- SKU -->
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        SKU: {{ product.sku }}
                    </p>

                    <!-- Price -->
                    <div class="mt-6 flex items-baseline gap-4">
                        <span class="text-4xl font-bold text-gray-900 dark:text-white">
                            {{ formatPrice(effectivePrice) }}
                        </span>
                        <span
                            v-if="product.sale_price"
                            class="text-xl text-gray-400 line-through"
                        >
                            {{ formatPrice(product.price_cents) }}
                        </span>
                        <span
                            v-if="discountPercentage"
                            class="rounded-full bg-red-100 dark:bg-red-900/50 px-3 py-1 text-sm font-semibold text-red-600 dark:text-red-400"
                        >
                            Save {{ discountPercentage }}%
                        </span>
                    </div>

                    <!-- Stock status -->
                    <div class="mt-4 flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 text-sm font-medium"
                            :class="stockStatus.class"
                        >
                            <span
                                class="h-2 w-2 rounded-full"
                                :class="{
                                    'bg-green-500': stockStatus.available && (!product.stock || (product.stock.available ?? product.stock.quantity) > 5),
                                    'bg-yellow-500': stockStatus.available && product.stock && (product.stock.available ?? product.stock.quantity) <= 5,
                                    'bg-red-500': !stockStatus.available,
                                }"
                            />
                            {{ stockStatus.text }}
                        </span>
                    </div>

                    <!-- Add to cart section -->
                    <div v-if="stockStatus.available" class="mt-8 space-y-4">
                        <div class="flex items-center gap-4">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Quantity
                            </label>
                            <QuantityStepper
                                v-model="quantity"
                                :min="1"
                                :max="maxQuantity"
                                size="md"
                            />
                        </div>

                        <!-- Error message -->
                        <div v-if="cartError" class="rounded-lg bg-red-50 dark:bg-red-900/50 p-4 border border-red-200 dark:border-red-800">
                            <p class="text-sm text-red-700 dark:text-red-200">{{ cartError.message }}</p>
                        </div>

                        <!-- Success message -->
                        <Transition
                            enter-active-class="duration-200 ease-out"
                            enter-from-class="opacity-0 -translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="duration-150 ease-in"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 -translate-y-2"
                        >
                            <div v-if="addedToCart" class="rounded-lg bg-green-50 dark:bg-green-900/50 p-4 border border-green-200 dark:border-green-800">
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                    </svg>
                                    <p class="text-sm font-medium text-green-700 dark:text-green-200">Added to cart!</p>
                                    <Link href="/cart" class="ml-auto text-sm font-medium text-green-600 dark:text-green-400 hover:underline">
                                        View cart
                                    </Link>
                                </div>
                            </div>
                        </Transition>

                        <button
                            @click="handleAddToCart"
                            :disabled="cartLoading"
                            class="w-full flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-8 py-4 text-base font-semibold text-white shadow-lg hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <Spinner v-if="cartLoading" size="sm" color="white" />
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                            </svg>
                            {{ cartLoading ? 'Adding...' : 'Add to Cart' }}
                        </button>
                    </div>

                    <!-- Out of stock message -->
                    <div v-else class="mt-8">
                        <button
                            disabled
                            class="w-full flex items-center justify-center gap-2 rounded-xl bg-gray-300 dark:bg-gray-700 px-8 py-4 text-base font-semibold text-gray-500 dark:text-gray-400 cursor-not-allowed"
                        >
                            Out of Stock
                        </button>
                    </div>

                    <!-- Trust badges -->
                    <div class="mt-8 grid grid-cols-3 gap-4 border-t border-gray-200 dark:border-gray-700 pt-8">
                        <div v-for="badge in trustBadges" :key="badge.text" class="flex flex-col items-center text-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                <svg v-if="badge.icon === 'shield'" class="h-5 w-5 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                                <svg v-else-if="badge.icon === 'truck'" class="h-5 w-5 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                </svg>
                                <svg v-else class="h-5 w-5 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </div>
                            <span class="mt-2 text-xs font-medium text-gray-600 dark:text-gray-400">{{ badge.text }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabbed content -->
            <div class="mt-16">
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex gap-8">
                        <button
                            @click="activeTab = 'description'"
                            class="py-4 text-sm font-medium border-b-2 transition-colors"
                            :class="activeTab === 'description'
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
                        >
                            Description
                        </button>
                        <button
                            @click="activeTab = 'specifications'"
                            class="py-4 text-sm font-medium border-b-2 transition-colors"
                            :class="activeTab === 'specifications'
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
                        >
                            Specifications
                        </button>
                        <button
                            @click="activeTab = 'reviews'"
                            class="py-4 text-sm font-medium border-b-2 transition-colors"
                            :class="activeTab === 'reviews'
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
                        >
                            Reviews
                        </button>
                    </nav>
                </div>

                <div class="py-8">
                    <!-- Description tab -->
                    <div v-if="activeTab === 'description'">
                        <div class="prose prose-gray dark:prose-invert max-w-none">
                            <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                                {{ product.description || product.short_description || 'No description available for this product.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Specifications tab -->
                    <div v-if="activeTab === 'specifications'">
                        <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                            <div class="py-4 grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">SKU</dt>
                                <dd class="col-span-2 text-sm text-gray-900 dark:text-white">{{ product.sku }}</dd>
                            </div>
                            <div v-if="product.category" class="py-4 grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Category</dt>
                                <dd class="col-span-2 text-sm text-gray-900 dark:text-white">{{ product.category.name }}</dd>
                            </div>
                            <div class="py-4 grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Availability</dt>
                                <dd class="col-span-2 text-sm" :class="stockStatus.class">{{ stockStatus.text }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Reviews tab -->
                    <div v-if="activeTab === 'reviews'">
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No reviews yet</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Be the first to review this product!</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related products -->
            <div v-if="relatedProducts.length > 0" class="mt-16">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Related Products</h2>
                <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <ProductCard
                        v-for="related in relatedProducts.slice(0, 4)"
                        :key="related.id"
                        :product="related"
                    />
                </div>
            </div>
        </div>

        <!-- Sticky mobile add-to-cart bar -->
        <div
            v-if="stockStatus.available"
            class="fixed bottom-0 left-0 right-0 z-40 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-4 lg:hidden"
        >
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ formatPrice(effectivePrice) }}</p>
                    <p class="text-sm" :class="stockStatus.class">{{ stockStatus.text }}</p>
                </div>
                <button
                    @click="handleAddToCart"
                    :disabled="cartLoading"
                    class="flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <Spinner v-if="cartLoading" size="sm" color="white" />
                    <span>{{ cartLoading ? 'Adding...' : 'Add to Cart' }}</span>
                </button>
            </div>
        </div>

        <!-- Add padding at bottom for mobile sticky bar -->
        <div class="h-24 lg:hidden" />

        <!-- Image lightbox -->
        <ImageLightbox
            :images="productImages"
            :initial-index="selectedImage"
            :show="lightboxOpen"
            :alt="product.name"
            @close="lightboxOpen = false"
        />
    </component>
</template>
