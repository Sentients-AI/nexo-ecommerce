<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CheckoutProgress from '@/Components/Checkout/CheckoutProgress.vue';
import Spinner from '@/Components/UI/Spinner.vue';
import { useCheckout } from '@/Composables/useCheckout';
import { usePayments } from '@/Composables/usePayments';
import type { CartApiResource } from '@/types/api';

function normalizeImages(images: string | string[] | null | undefined): string[] {
    if (!images) return [];
    if (Array.isArray(images)) return images;
    if (typeof images === 'string') return [images];
    return [];
}

interface Props {
    cart: CartApiResource;
    stripePublicKey: string;
}

const props = defineProps<Props>();

const { initiateCheckout, loading: checkoutLoading, error: checkoutError, clearError } = useCheckout();
const { stripe, elements, loading: stripeLoading, error: stripeError, createElements, confirmPayment, paymentProcessing } = usePayments(props.stripePublicKey);

const clientSecret = ref<string | null>(null);
const paymentElementRef = ref<HTMLDivElement | null>(null);
const isSubmitting = ref(false);
const submitError = ref<string | null>(null);
const itemsExpanded = ref(true);

const isLoading = computed(() => checkoutLoading.value || stripeLoading.value || paymentProcessing.value || isSubmitting.value);

function formatPrice(cents: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

const taxCents = computed(() => Math.round(props.cart.subtotal * 0.08));
const totalCents = computed(() => props.cart.subtotal + taxCents.value);

async function handleInitiateCheckout() {
    clearError();
    submitError.value = null;
    isSubmitting.value = true;

    const success = await initiateCheckout(props.cart.id);

    if (!success) {
        submitError.value = checkoutError.value?.message ?? 'Failed to initiate checkout';
        isSubmitting.value = false;
        return;
    }

    const orderData = await fetch('/api/v1/checkout', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            'Idempotency-Key': getIdempotencyKey('checkout'),
        },
        credentials: 'include',
        body: JSON.stringify({
            cart_id: props.cart.id,
            currency: 'USD',
        }),
    });

    if (!orderData.ok) {
        const errorData = await orderData.json();
        submitError.value = errorData.error?.message ?? 'Checkout failed';
        isSubmitting.value = false;
        return;
    }

    const result = await orderData.json();

    if (result.order && result.payment_intent) {
        sessionStorage.setItem('checkout_client_secret', result.payment_intent.client_secret);
        router.visit(`/checkout/pending?order_id=${result.order.id}`);
    } else {
        submitError.value = 'Invalid checkout response';
        isSubmitting.value = false;
    }
}

function getCsrfToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
}

function getIdempotencyKey(operation: string): string {
    const storageKey = `idempotency_${operation}`;
    let key = sessionStorage.getItem(storageKey);
    if (!key) {
        key = `${operation}_${Date.now()}_${Math.random().toString(36).substring(2, 11)}`;
        sessionStorage.setItem(storageKey, key);
    }
    return key;
}
</script>

<template>
    <Head title="Checkout - Review Order" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Progress indicator -->
            <CheckoutProgress :current-step="0" class="mb-8" />

            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Review Your Order</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Please review your items before proceeding to payment.
            </p>

            <div class="mt-8 lg:grid lg:grid-cols-12 lg:gap-x-8">
                <!-- Order items -->
                <div class="lg:col-span-7">
                    <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <!-- Header with collapse toggle -->
                        <button
                            @click="itemsExpanded = !itemsExpanded"
                            class="flex w-full items-center justify-between p-6 text-left"
                        >
                            <div class="flex items-center gap-3">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Order Items
                                </h2>
                                <span class="rounded-full bg-indigo-100 dark:bg-indigo-900/50 px-2.5 py-0.5 text-sm font-medium text-indigo-700 dark:text-indigo-300">
                                    {{ cart.items.length }}
                                </span>
                            </div>
                            <svg
                                class="h-5 w-5 text-gray-500 transition-transform"
                                :class="{ 'rotate-180': itemsExpanded }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <!-- Items list -->
                        <Transition
                            enter-active-class="duration-200 ease-out"
                            enter-from-class="opacity-0 -translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="duration-150 ease-in"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 -translate-y-2"
                        >
                            <div v-show="itemsExpanded" class="border-t border-gray-200 dark:border-gray-700">
                                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <li
                                        v-for="item in cart.items"
                                        :key="item.id"
                                        class="flex gap-4 p-6"
                                    >
                                        <div class="h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-700">
                                            <img
                                                v-if="normalizeImages(item.product?.images).length > 0"
                                                :src="normalizeImages(item.product?.images)[0]"
                                                :alt="item.product?.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <div
                                                v-else
                                                class="flex h-full items-center justify-center text-gray-400"
                                            >
                                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex flex-1 flex-col">
                                            <div class="flex justify-between">
                                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ item.product?.name ?? 'Product' }}
                                                </h3>
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                    {{ formatPrice(item.price * item.quantity) }}
                                                </p>
                                            </div>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                Qty: {{ item.quantity }} x {{ formatPrice(item.price) }}
                                            </p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </Transition>
                    </div>

                    <!-- Edit cart link -->
                    <div class="mt-4 text-center">
                        <Link
                            href="/cart"
                            class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                            Edit cart
                        </Link>
                    </div>
                </div>

                <!-- Payment summary -->
                <div class="mt-8 lg:col-span-5 lg:mt-0">
                    <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 p-6 sticky top-24">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Order Summary</h2>

                        <!-- Price breakdown -->
                        <dl class="mt-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Subtotal</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ formatPrice(cart.subtotal) }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Tax (8%)</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ formatPrice(taxCents) }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Shipping</dt>
                                <dd class="text-sm font-medium text-green-600 dark:text-green-400">
                                    Free
                                </dd>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-4">
                                <dt class="text-base font-semibold text-gray-900 dark:text-white">Total</dt>
                                <dd class="text-base font-semibold text-gray-900 dark:text-white">
                                    {{ formatPrice(totalCents) }}
                                </dd>
                            </div>
                        </dl>

                        <!-- Error message -->
                        <Transition
                            enter-active-class="duration-200 ease-out"
                            enter-from-class="opacity-0 -translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="duration-150 ease-in"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 -translate-y-2"
                        >
                            <div
                                v-if="submitError || checkoutError"
                                class="mt-4 rounded-lg bg-red-50 dark:bg-red-900/50 p-4 border border-red-200 dark:border-red-800"
                            >
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                    </svg>
                                    <p class="text-sm text-red-700 dark:text-red-200">
                                        {{ submitError || checkoutError?.message }}
                                    </p>
                                </div>
                            </div>
                        </Transition>

                        <!-- Checkout button -->
                        <button
                            @click="handleInitiateCheckout"
                            :disabled="isLoading"
                            class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-4 text-base font-semibold text-white shadow-lg hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <Spinner v-if="isLoading" size="sm" color="white" />
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                            </svg>
                            {{ isLoading ? 'Processing...' : 'Continue to Payment' }}
                        </button>

                        <p class="mt-4 text-xs text-center text-gray-500 dark:text-gray-400">
                            You'll be redirected to securely enter your payment details.
                        </p>

                        <!-- Security badges -->
                        <div class="mt-6 flex items-center justify-center gap-4 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                                SSL Secured
                            </div>
                            <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                                Stripe Protected
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
