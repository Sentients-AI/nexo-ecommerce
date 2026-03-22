<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import QuantityStepper from '@/Components/UI/QuantityStepper.vue';
import Spinner from '@/Components/UI/Spinner.vue';
import { useCart } from '@/Composables/useCart';
import { useApi } from '@/Composables/useApi';
import { useLocale } from '@/Composables/useLocale';
import type { CartApiResource } from '@/types/api';

function normalizeImages(images: string | string[] | null | undefined): string[] {
    if (!images) return [];
    if (Array.isArray(images)) return images;
    if (typeof images === 'string') return [images];
    return [];
}

interface Props {
    cart: CartApiResource;
}

const props = defineProps<Props>();

const page = usePage();
const { updateItem, removeItem, loading, error, clearError, formatPrice } = useCart();
const { post: apiPost } = useApi();

const isAuthenticated = computed(() => page.props.auth?.user !== null);
const Layout = computed(() => isAuthenticated.value ? AuthenticatedLayout : GuestLayout);
const { localePath } = useLocale();

const promoCode = ref('');
const promoError = ref('');
const promoApplied = ref(false);
const promoLoading = ref(false);
const appliedPromotion = ref<{ name: string; discount_cents: number } | null>(null);

const isEmpty = computed(() => props.cart.items.length === 0);

async function handleQuantityChange(itemId: number, newQuantity: number) {
    clearError();
    const success = await updateItem(itemId, newQuantity);
    if (success) {
        router.reload({ only: ['cart'] });
    }
}

async function handleRemove(itemId: number) {
    clearError();
    const success = await removeItem(itemId);
    if (success) {
        router.reload({ only: ['cart'] });
    }
}

function getMaxQuantity(item: CartApiResource['items'][0]): number {
    if (!item.product?.stock) {
        return 10;
    }
    return Math.min(item.product.stock.available ?? item.product.stock.quantity, 10);
}

async function handleApplyPromo() {
    promoError.value = '';

    if (!promoCode.value.trim()) {
        promoError.value = 'Please enter a promo code';
        return;
    }

    promoLoading.value = true;

    const result = await apiPost<{
        valid: boolean;
        message?: string;
        promotion?: { name: string };
        discount_cents?: number;
    }>('/api/v1/cart/validate-promotion', {
        code: promoCode.value.trim().toUpperCase(),
        cart_id: props.cart.id,
    });

    promoLoading.value = false;

    if (!result) {
        promoError.value = 'Could not apply promo code. Please try again.';
        return;
    }

    if (!result.valid) {
        promoError.value = result.message ?? 'Invalid promo code';
        promoApplied.value = false;
        appliedPromotion.value = null;
        return;
    }

    promoApplied.value = true;
    appliedPromotion.value = {
        name: result.promotion!.name,
        discount_cents: result.discount_cents!,
    };
}

function removePromo() {
    promoCode.value = '';
    promoApplied.value = false;
    promoError.value = '';
    appliedPromotion.value = null;
}

const discountedTotal = computed(() => {
    const subtotalCents = Math.round(props.cart.subtotal * 100);
    const discount = appliedPromotion.value?.discount_cents ?? 0;
    return Math.max(0, subtotalCents - discount) / 100;
});
</script>

<template>
    <Head title="Shopping Cart" />

    <component :is="Layout">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Shopping Cart</h1>

            <!-- Error message -->
            <Transition
                enter-active-class="duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div v-if="error" class="mt-4 rounded-lg bg-red-50 dark:bg-red-900/50 p-4 border border-red-200 dark:border-red-800">
                    <div class="flex items-center gap-3">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-sm text-red-700 dark:text-red-200">{{ error.message }}</p>
                        <button
                            v-if="error.retryable"
                            @click="clearError"
                            class="ml-auto text-sm font-medium text-red-600 hover:text-red-500 dark:text-red-400"
                        >
                            Dismiss
                        </button>
                    </div>
                </div>
            </Transition>

            <!-- Empty cart -->
            <div v-if="isEmpty" class="mt-12 text-center py-16">
                <div class="mx-auto h-32 w-32 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-8">
                    <svg class="h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Your cart is empty</h2>
                <p class="mt-2 text-gray-500 dark:text-gray-400">Looks like you haven't added any items yet.</p>
                <Link
                    :href="localePath('/products')"
                    class="mt-8 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                    Start Shopping
                </Link>
            </div>

            <!-- Cart with items -->
            <div v-else class="mt-8 lg:grid lg:grid-cols-12 lg:gap-x-12">
                <!-- Cart items -->
                <div class="lg:col-span-7">
                    <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li
                            v-for="item in cart.items"
                            :key="item.id"
                            class="py-6"
                        >
                            <div class="flex gap-4 sm:gap-6">
                                <!-- Product image -->
                                <Link
                                    v-if="item.product"
                                    :href="localePath(`/products/${item.product.slug}`)"
                                    class="h-24 w-24 sm:h-32 sm:w-32 shrink-0 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800"
                                >
                                    <img
                                        v-if="normalizeImages(item.product?.images).length > 0"
                                        :src="normalizeImages(item.product?.images)[0]"
                                        :alt="item.product?.name"
                                        class="h-full w-full object-cover object-center hover:opacity-80 transition-opacity"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full items-center justify-center text-gray-400"
                                    >
                                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                </Link>

                                <!-- Product details -->
                                <div class="flex flex-1 flex-col">
                                    <div class="flex justify-between">
                                        <div>
                                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                                <Link
                                                    v-if="item.product"
                                                    :href="localePath(`/products/${item.product.slug}`)"
                                                    class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                >
                                                    {{ item.product.name }}
                                                </Link>
                                                <span v-else>Product unavailable</span>
                                            </h3>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                {{ formatPrice(item.price) }} each
                                            </p>
                                        </div>
                                        <p class="text-base font-semibold text-gray-900 dark:text-white">
                                            {{ formatPrice(item.price * item.quantity) }}
                                        </p>
                                    </div>

                                    <div class="mt-4 flex flex-1 items-end justify-between">
                                        <!-- Quantity stepper -->
                                        <QuantityStepper
                                            :model-value="item.quantity"
                                            @update:model-value="(val) => handleQuantityChange(item.id, val)"
                                            :min="1"
                                            :max="getMaxQuantity(item)"
                                            :disabled="loading"
                                            size="sm"
                                        />

                                        <!-- Remove button -->
                                        <button
                                            @click="handleRemove(item.id)"
                                            :disabled="loading"
                                            class="flex items-center gap-1 text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Order summary -->
                <div class="mt-8 lg:mt-0 lg:col-span-5">
                    <div class="rounded-2xl bg-gray-50 dark:bg-gray-800 p-6 lg:p-8 sticky top-24">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Order Summary</h2>

                        <!-- Promo code -->
                        <div class="mt-6">
                            <label for="promo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Promo code
                            </label>
                            <div v-if="!promoApplied" class="mt-2 flex gap-2">
                                <input
                                    id="promo"
                                    v-model="promoCode"
                                    type="text"
                                    placeholder="Enter code"
                                    :disabled="promoLoading"
                                    @keyup.enter="handleApplyPromo"
                                    class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50"
                                />
                                <button
                                    @click="handleApplyPromo"
                                    :disabled="promoLoading"
                                    class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors disabled:opacity-50"
                                >
                                    <span v-if="promoLoading">...</span>
                                    <span v-else>Apply</span>
                                </button>
                            </div>
                            <div v-else class="mt-2 flex items-center justify-between rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-sm font-medium text-green-700 dark:text-green-300">{{ appliedPromotion?.name }}</span>
                                </div>
                                <button @click="removePromo" class="text-sm text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200">
                                    Remove
                                </button>
                            </div>
                            <p v-if="promoError" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ promoError }}</p>
                        </div>

                        <!-- Summary -->
                        <dl class="mt-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Subtotal</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ formatPrice(cart.subtotal) }}</dd>
                            </div>
                            <div v-if="appliedPromotion" class="flex items-center justify-between">
                                <dt class="text-sm text-green-600 dark:text-green-400">Discount ({{ appliedPromotion.name }})</dt>
                                <dd class="text-sm font-medium text-green-600 dark:text-green-400">-{{ formatPrice(appliedPromotion.discount_cents / 100) }}</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Shipping</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-white">Calculated at checkout</dd>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-4">
                                <dt class="text-base font-semibold text-gray-900 dark:text-white">Total</dt>
                                <dd class="text-base font-semibold text-gray-900 dark:text-white">{{ formatPrice(discountedTotal * 100) }}</dd>
                            </div>
                        </dl>

                        <!-- Checkout button -->
                        <div class="mt-6 space-y-3">
                            <Link
                                v-if="isAuthenticated"
                                :href="localePath('/checkout')"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-4 text-base font-semibold text-white shadow-lg hover:bg-indigo-500 transition-colors"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                                Proceed to Checkout
                            </Link>
                            <Link
                                v-else
                                :href="localePath('/login')"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-4 text-base font-semibold text-white shadow-lg hover:bg-indigo-500 transition-colors"
                            >
                                Sign in to Checkout
                            </Link>
                            <Link
                                :href="localePath('/products')"
                                class="flex w-full items-center justify-center rounded-xl border border-gray-300 dark:border-gray-600 px-6 py-3 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                Continue Shopping
                            </Link>
                        </div>

                        <!-- Trust badges -->
                        <div class="mt-6 flex items-center justify-center gap-4 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                                Secure checkout
                            </div>
                            <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                Easy returns
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
