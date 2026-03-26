<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CheckoutProgress from '@/Components/Checkout/CheckoutProgress.vue';
import { useLocale } from '@/Composables/useLocale';
import type { OrderApiResource } from '@/types/api';

interface Props {
    order: OrderApiResource;
    success: boolean;
}

const props = defineProps<Props>();

const showConfetti = ref(false);
const { localePath } = useLocale();

function formatPrice(cents: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

onMounted(() => {
    if (props.success) {
        showConfetti.value = true;
        setTimeout(() => {
            showConfetti.value = false;
        }, 3000);
    }
});
</script>

<template>
    <Head :title="success ? 'Order Confirmed' : 'Order Status'" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Progress indicator -->
            <CheckoutProgress :current-step="2" class="mb-8" />

            <!-- Success state -->
            <div v-if="success" class="text-center">
                <!-- Animated checkmark -->
                <div class="relative mx-auto h-24 w-24">
                    <div class="absolute inset-0 animate-ping rounded-full bg-accent-400 opacity-20" />
                    <div class="relative flex h-24 w-24 items-center justify-center rounded-full bg-accent-100 dark:bg-accent-900/30">
                        <svg
                            class="h-12 w-12 text-accent-600 dark:text-accent-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4.5 12.75l6 6 9-13.5"
                                style="stroke-dasharray: 24; stroke-dashoffset: 24; animation: draw 0.5s ease-out 0.3s forwards;"
                            />
                        </svg>
                    </div>
                </div>

                <h1 class="mt-6 text-3xl font-bold text-slate-900 dark:text-white">Order Confirmed!</h1>
                <p class="mt-2 text-lg text-slate-600 dark:text-slate-400">
                    Thank you for your purchase.
                </p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    A confirmation email has been sent to your email address.
                </p>
            </div>

            <!-- Failed state -->
            <div v-else class="text-center">
                <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <svg class="h-12 w-12 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h1 class="mt-6 text-3xl font-bold text-slate-900 dark:text-white">Payment Issue</h1>
                <p class="mt-2 text-lg text-slate-600 dark:text-slate-400">
                    There was a problem with your payment.
                </p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Please try again or contact support if the issue persists.
                </p>
            </div>

            <!-- Order details card -->
            <div class="mt-8 rounded-2xl bg-white dark:bg-navy-900/60 shadow-sm border border-slate-100 dark:border-navy-800/60 overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-5 border-b border-slate-100 dark:border-navy-800/60 bg-slate-50 dark:bg-navy-900/80">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                                Order #{{ order.order_number }}
                            </h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                Placed on {{ formatDate(order.created_at) }}
                            </p>
                        </div>
                        <span
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium',
                                success
                                    ? 'bg-accent-100 text-accent-800 dark:bg-accent-900/50 dark:text-accent-200'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200'
                            ]"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="success ? 'bg-accent-500' : 'bg-amber-500'"
                            />
                            {{ order.status }}
                        </span>
                    </div>
                </div>

                <!-- Order items -->
                <ul class="divide-y divide-slate-100 dark:divide-navy-800/60">
                    <li
                        v-for="item in order.items"
                        :key="item.id"
                        class="flex items-center gap-4 px-6 py-4"
                    >
                        <div class="h-16 w-16 shrink-0 rounded-xl bg-slate-100 dark:bg-navy-800 flex items-center justify-center">
                            <svg class="h-8 w-8 text-slate-400 dark:text-navy-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-medium text-slate-900 dark:text-white truncate">
                                {{ item.product_name }}
                            </h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ item.quantity }} x {{ formatPrice(item.unit_price_cents) }}
                            </p>
                        </div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">
                            {{ formatPrice(item.total_cents) }}
                        </p>
                    </li>
                </ul>

                <!-- Price summary -->
                <div class="px-6 py-5 bg-slate-50 dark:bg-navy-900/80">
                    <dl class="space-y-2 text-sm">
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
                        <div class="flex justify-between pt-3 border-t border-slate-200 dark:border-navy-700">
                            <dt class="text-base font-semibold text-slate-900 dark:text-white">Total</dt>
                            <dd class="text-base font-semibold text-slate-900 dark:text-white">{{ formatPrice(order.total_cents) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
                <Link
                    :href="localePath(`/orders/${order.id}`)"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-800/60 px-6 py-3 text-base font-medium text-slate-700 dark:text-slate-300 shadow-sm hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    View Order Details
                </Link>
                <Link
                    :href="localePath('/products')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-3 text-base font-medium text-white shadow-sm shadow-brand-500/25 hover:bg-brand-400 transition-colors"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                    Continue Shopping
                </Link>
            </div>

            <!-- What's next section (success only) -->
            <div v-if="success" class="mt-12 rounded-2xl bg-brand-50 dark:bg-brand-900/20 border border-brand-100 dark:border-brand-800/40 p-6">
                <h3 class="text-lg font-semibold text-brand-900 dark:text-brand-100">What's next?</h3>
                <ul class="mt-4 space-y-4">
                    <li class="flex items-start gap-3">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-500 text-white text-xs font-bold">1</div>
                        <div>
                            <p class="text-sm font-medium text-brand-900 dark:text-brand-100">Order Processing</p>
                            <p class="text-sm text-brand-700 dark:text-brand-300">We're preparing your order for shipment.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-500 text-white text-xs font-bold">2</div>
                        <div>
                            <p class="text-sm font-medium text-brand-900 dark:text-brand-100">Shipping Notification</p>
                            <p class="text-sm text-brand-700 dark:text-brand-300">You'll receive an email with tracking information once shipped.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-500 text-white text-xs font-bold">3</div>
                        <div>
                            <p class="text-sm font-medium text-brand-900 dark:text-brand-100">Delivery</p>
                            <p class="text-sm text-brand-700 dark:text-brand-300">Your order will arrive at your doorstep.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes draw {
    to {
        stroke-dashoffset: 0;
    }
}
</style>
