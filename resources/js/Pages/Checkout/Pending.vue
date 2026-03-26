<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CheckoutProgress from '@/Components/Checkout/CheckoutProgress.vue';
import Spinner from '@/Components/UI/Spinner.vue';
import { usePayments } from '@/Composables/usePayments';
import type { OrderApiResource } from '@/types/api';

interface Props {
    order: OrderApiResource;
    clientSecret: string | null;
    stripePublicKey: string;
}

const props = defineProps<Props>();

const { stripe, elements, loading: stripeLoading, error: stripeError, createElements, confirmPayment, paymentProcessing } = usePayments(props.stripePublicKey);

const paymentElementRef = ref<HTMLDivElement | null>(null);
const paymentElementMounted = ref(false);
const submitError = ref<string | null>(null);
const isSubmitting = ref(false);

const clientSecret = computed(() => {
    return props.clientSecret || sessionStorage.getItem('checkout_client_secret');
});

const isLoading = computed(() => stripeLoading.value || paymentProcessing.value || isSubmitting.value);

function formatPrice(cents: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

async function mountPaymentElement() {
    if (!clientSecret.value || !stripe.value || paymentElementMounted.value) {
        return;
    }

    await nextTick();

    const elementsInstance = createElements(clientSecret.value);

    if (!elementsInstance || !paymentElementRef.value) {
        submitError.value = 'Failed to initialize payment form';
        return;
    }

    const paymentElement = elementsInstance.create('payment', {
        layout: 'tabs',
    });

    paymentElement.mount(paymentElementRef.value);
    paymentElementMounted.value = true;
}

async function handleSubmitPayment() {
    if (!elements.value || !stripe.value) {
        submitError.value = 'Payment form not ready';
        return;
    }

    isSubmitting.value = true;
    submitError.value = null;

    const returnUrl = `${window.location.origin}/checkout/result?order_id=${props.order.id}`;

    const result = await confirmPayment(returnUrl);

    if (result.success) {
        if (result.paymentIntent?.status === 'succeeded') {
            sessionStorage.removeItem('checkout_client_secret');
            sessionStorage.removeItem('idempotency_checkout');
            router.visit(`/checkout/result?order_id=${props.order.id}`);
        }
    } else {
        submitError.value = result.error ?? 'Payment failed';
        isSubmitting.value = false;
    }
}

let pollInterval: ReturnType<typeof setInterval> | null = null;

function startPolling() {
    pollInterval = setInterval(() => {
        router.reload({ only: ['order'] });
    }, 5000);
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

onMounted(async () => {
    const waitForStripe = setInterval(() => {
        if (stripe.value && !stripeLoading.value) {
            clearInterval(waitForStripe);
            mountPaymentElement();
        }
    }, 100);

    startPolling();
    setTimeout(() => clearInterval(waitForStripe), 30000);
});

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <Head title="Checkout - Payment" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Progress indicator -->
            <CheckoutProgress :current-step="1" class="mb-8" />

            <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Complete Your Payment</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-400">
                Enter your payment details to complete your order.
            </p>

            <div class="mt-8 space-y-6">
                <!-- Order summary card -->
                <div class="rounded-2xl bg-white dark:bg-navy-900/60 shadow-sm border border-slate-100 dark:border-navy-800/60 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                                Order #{{ order.order_number }}
                            </h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                {{ order.items?.length ?? 0 }} items
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">
                                {{ formatPrice(order.total_cents) }}
                            </p>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-yellow-100 dark:bg-yellow-900/50 px-2.5 py-1 text-xs font-medium text-yellow-800 dark:text-yellow-200 mt-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-yellow-400 animate-pulse" />
                                Awaiting Payment
                            </span>
                        </div>
                    </div>

                    <!-- Price breakdown -->
                    <dl class="mt-6 space-y-2 text-sm border-t border-slate-200 dark:border-navy-700 pt-4">
                        <div class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">Subtotal</dt>
                            <dd class="text-slate-900 dark:text-white">{{ formatPrice(order.subtotal_cents) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">Tax</dt>
                            <dd class="text-slate-900 dark:text-white">{{ formatPrice(order.tax_cents) }}</dd>
                        </div>
                        <div v-if="order.shipping_cost_cents" class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">Shipping</dt>
                            <dd class="text-slate-900 dark:text-white">{{ formatPrice(order.shipping_cost_cents) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Payment form -->
                <div class="rounded-2xl bg-white dark:bg-navy-900/60 shadow-sm border border-slate-100 dark:border-navy-800/60 p-6">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-6">Payment Details</h2>

                    <!-- Loading state -->
                    <div v-if="stripeLoading" class="flex flex-col items-center justify-center py-12">
                        <Spinner size="lg" />
                        <p class="mt-4 text-slate-500 dark:text-slate-400">Loading secure payment form...</p>
                    </div>

                    <!-- Stripe error -->
                    <div v-else-if="stripeError" class="rounded-xl bg-red-50 dark:bg-red-900/20 p-4 border border-red-200 dark:border-red-800/60">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                            </svg>
                            <p class="text-sm text-red-700 dark:text-red-300">{{ stripeError }}</p>
                        </div>
                    </div>

                    <!-- Payment element container -->
                    <div v-else>
                        <div ref="paymentElementRef" class="min-h-[200px]" />

                        <!-- Error message -->
                        <Transition
                            enter-active-class="duration-200 ease-out"
                            enter-from-class="opacity-0 -translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="duration-150 ease-in"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 -translate-y-2"
                        >
                            <div v-if="submitError" class="mt-4 rounded-xl bg-red-50 dark:bg-red-900/20 p-4 border border-red-200 dark:border-red-800/60">
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                    </svg>
                                    <p class="text-sm text-red-700 dark:text-red-300">{{ submitError }}</p>
                                </div>
                            </div>
                        </Transition>

                        <!-- Submit button -->
                        <button
                            @click="handleSubmitPayment"
                            :disabled="isLoading || !paymentElementMounted"
                            class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-4 text-base font-semibold text-white shadow-lg shadow-brand-500/25 hover:bg-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <Spinner v-if="isLoading" size="sm" color="white" />
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            {{ isLoading ? 'Processing...' : `Pay ${formatPrice(order.total_cents)}` }}
                        </button>
                    </div>
                </div>

                <!-- Security notice -->
                <div class="flex items-center justify-center gap-6 text-xs text-slate-500 dark:text-slate-400">
                    <div class="flex items-center gap-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        Encrypted connection
                    </div>
                    <div class="flex items-center gap-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                        Powered by Stripe
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
