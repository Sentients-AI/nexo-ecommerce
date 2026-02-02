<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Spinner from '@/Components/UI/Spinner.vue';
import { OrderStatus, RefundStatus } from '@/types/models';

interface OrderItem {
    id: number;
    product_id: number;
    product_name: string;
    product_sku: string;
    product_image?: string;
    quantity: number;
    unit_price_cents: number;
    total_cents: number;
}

interface Refund {
    id: number;
    amount_cents: number;
    status: string;
    reason: string | null;
    created_at: string;
}

interface Order {
    id: number;
    order_number: string;
    status: string;
    subtotal_cents: number;
    tax_cents: number;
    shipping_cost_cents: number;
    total_cents: number;
    refunded_amount_cents: number;
    currency: string;
    is_refundable: boolean;
    remaining_refundable_amount: number;
    items: OrderItem[];
    payment_intent: {
        id: number;
        status: string;
        amount_cents: number;
    } | null;
    refunds: Refund[];
    created_at: string;
    updated_at: string;
}

interface Props {
    order: Order;
}

const props = defineProps<Props>();

const isReordering = ref(false);

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
    });
}

function formatDateTime(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatStatus(status: string): string {
    return status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function getRefundStatusClass(status: string): string {
    const baseClasses = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium';

    switch (status) {
        case RefundStatus.Succeeded:
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200`;
        case RefundStatus.Processing:
        case RefundStatus.Approved:
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200`;
        case RefundStatus.Requested:
        case RefundStatus.PendingApproval:
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-200`;
        case RefundStatus.Failed:
        case RefundStatus.Rejected:
        case RefundStatus.Cancelled:
            return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200`;
    }
}

const orderTimeline = computed(() => {
    const events: Array<{ title: string; description: string; date: string; completed: boolean; icon: string }> = [
        {
            title: 'Order Placed',
            description: 'Your order has been received',
            date: props.order.created_at,
            completed: true,
            icon: 'receipt',
        },
    ];

    // Add payment event
    if (props.order.payment_intent) {
        const paymentCompleted = props.order.payment_intent.status === 'succeeded';
        events.push({
            title: paymentCompleted ? 'Payment Confirmed' : 'Payment ' + formatStatus(props.order.payment_intent.status),
            description: paymentCompleted ? 'Your payment has been processed' : 'Awaiting payment confirmation',
            date: props.order.updated_at,
            completed: paymentCompleted,
            icon: 'credit-card',
        });
    }

    // Add fulfillment events based on status
    const fulfillmentStatuses = [
        { status: OrderStatus.Packed, title: 'Order Packed', description: 'Your order has been packed and is ready' },
        { status: OrderStatus.Shipped, title: 'Shipped', description: 'Your order is on its way' },
        { status: OrderStatus.Delivered, title: 'Delivered', description: 'Your order has been delivered' },
    ];

    const currentStatusIndex = fulfillmentStatuses.findIndex(s => s.status === props.order.status);
    const paidStatuses = [OrderStatus.Paid, OrderStatus.Fulfilled, ...fulfillmentStatuses.map(s => s.status)];
    const isPaidOrBeyond = paidStatuses.includes(props.order.status as OrderStatus);

    if (isPaidOrBeyond) {
        fulfillmentStatuses.forEach((item, index) => {
            events.push({
                title: item.title,
                description: item.description,
                date: props.order.updated_at,
                completed: index <= currentStatusIndex,
                icon: index === 0 ? 'package' : index === 1 ? 'truck' : 'check-circle',
            });
        });
    }

    return events;
});

const canReorder = computed(() => {
    return props.order.items.length > 0;
});

async function handleReorder() {
    if (isReordering.value) return;
    isReordering.value = true;

    // Add all items to cart
    // This would typically call an API endpoint that adds all order items to cart
    router.post(`/orders/${props.order.id}/reorder`, {}, {
        onFinish: () => {
            isReordering.value = false;
        },
        onError: () => {
            isReordering.value = false;
        },
    });
}

function getTimelineIcon(iconName: string) {
    const icons: Record<string, string> = {
        'receipt': 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z',
        'credit-card': 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
        'package': 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
        'truck': 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
        'check-circle': 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    };
    return icons[iconName] || icons['receipt'];
}

const netTotal = computed(() => {
    return props.order.total_cents - props.order.refunded_amount_cents;
});
</script>

<template>
    <Head :title="`Order ${order.order_number}`" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <Link
                        href="/orders"
                        class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                        Back to Orders
                    </Link>
                    <h1 class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">
                        Order {{ order.order_number }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Placed on {{ formatDateTime(order.created_at) }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <StatusBadge type="order" :status="order.status" size="lg" />
                </div>
            </div>

            <div class="mt-8 lg:grid lg:grid-cols-12 lg:gap-x-8">
                <!-- Order details -->
                <div class="lg:col-span-8 space-y-6">
                    <!-- Order items -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden shadow-sm">
                        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-800/50">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Order Items
                            </h2>
                        </div>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <li
                                v-for="item in order.items"
                                :key="item.id"
                                class="flex items-center gap-4 px-6 py-5 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                            >
                                <!-- Product thumbnail -->
                                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-700">
                                    <img
                                        v-if="item.product_image"
                                        :src="item.product_image"
                                        :alt="item.product_name"
                                        class="h-full w-full object-cover"
                                    />
                                    <div v-else class="flex h-full w-full items-center justify-center">
                                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                        </svg>
                                    </div>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <Link
                                        :href="`/products/${item.product_id}`"
                                        class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                    >
                                        {{ item.product_name }}
                                    </Link>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        SKU: {{ item.product_sku }}
                                    </p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ item.quantity }} x {{ formatPrice(item.unit_price_cents) }}
                                    </p>
                                </div>

                                <p class="text-base font-semibold text-gray-900 dark:text-white">
                                    {{ formatPrice(item.total_cents) }}
                                </p>
                            </li>
                        </ul>
                    </div>

                    <!-- Refunds section -->
                    <div v-if="order.refunds.length > 0" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden shadow-sm">
                        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-800/50">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Refunds
                            </h2>
                        </div>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <li
                                v-for="refund in order.refunds"
                                :key="refund.id"
                                class="px-6 py-5"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3">
                                            <p class="text-base font-semibold text-gray-900 dark:text-white">
                                                {{ formatPrice(refund.amount_cents) }}
                                            </p>
                                            <span :class="getRefundStatusClass(refund.status)">
                                                <span
                                                    class="h-1.5 w-1.5 rounded-full"
                                                    :class="{
                                                        'bg-green-500': refund.status === RefundStatus.Succeeded,
                                                        'bg-blue-500': [RefundStatus.Processing, RefundStatus.Approved].includes(refund.status as RefundStatus),
                                                        'bg-yellow-500': [RefundStatus.Requested, RefundStatus.PendingApproval].includes(refund.status as RefundStatus),
                                                        'bg-red-500': [RefundStatus.Failed, RefundStatus.Rejected, RefundStatus.Cancelled].includes(refund.status as RefundStatus),
                                                        'bg-gray-500': ![RefundStatus.Succeeded, RefundStatus.Processing, RefundStatus.Approved, RefundStatus.Requested, RefundStatus.PendingApproval, RefundStatus.Failed, RefundStatus.Rejected, RefundStatus.Cancelled].includes(refund.status as RefundStatus),
                                                    }"
                                                />
                                                {{ formatStatus(refund.status) }}
                                            </span>
                                        </div>
                                        <p v-if="refund.reason" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                            {{ refund.reason }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                            Requested on {{ formatDate(refund.created_at) }}
                                        </p>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Actions bar -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Reorder button -->
                        <button
                            v-if="canReorder"
                            @click="handleReorder"
                            :disabled="isReordering"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <Spinner v-if="isReordering" size="sm" color="white" />
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            {{ isReordering ? 'Adding to Cart...' : 'Reorder' }}
                        </button>

                        <!-- Request refund -->
                        <Link
                            v-if="order.is_refundable && order.remaining_refundable_amount > 0"
                            :href="`/orders/${order.id}/refund`"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                            </svg>
                            Request Refund
                        </Link>

                        <!-- Download invoice (placeholder) -->
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                            Download Invoice
                        </button>
                    </div>

                    <p v-if="order.is_refundable && order.remaining_refundable_amount > 0" class="text-sm text-gray-500 dark:text-gray-400">
                        Up to {{ formatPrice(order.remaining_refundable_amount) }} available for refund
                    </p>
                </div>

                <!-- Summary sidebar -->
                <div class="mt-8 lg:col-span-4 lg:mt-0 space-y-6">
                    <!-- Order summary -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden shadow-sm">
                        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-800/50">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Order Summary</h2>
                        </div>
                        <div class="px-6 py-5">
                            <dl class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <dt class="text-gray-500 dark:text-gray-400">Subtotal</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ formatPrice(order.subtotal_cents) }}</dd>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <dt class="text-gray-500 dark:text-gray-400">Tax</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ formatPrice(order.tax_cents) }}</dd>
                                </div>
                                <div v-if="order.shipping_cost_cents" class="flex justify-between text-sm">
                                    <dt class="text-gray-500 dark:text-gray-400">Shipping</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ formatPrice(order.shipping_cost_cents) }}</dd>
                                </div>
                                <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-3">
                                    <dt class="text-base font-semibold text-gray-900 dark:text-white">Total</dt>
                                    <dd class="text-base font-semibold text-gray-900 dark:text-white">{{ formatPrice(order.total_cents) }}</dd>
                                </div>
                                <div v-if="order.refunded_amount_cents > 0" class="flex justify-between text-sm text-red-600 dark:text-red-400">
                                    <dt>Refunded</dt>
                                    <dd>-{{ formatPrice(order.refunded_amount_cents) }}</dd>
                                </div>
                                <div v-if="order.refunded_amount_cents > 0" class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-3">
                                    <dt class="text-base font-semibold text-gray-900 dark:text-white">Net Total</dt>
                                    <dd class="text-base font-semibold text-gray-900 dark:text-white">{{ formatPrice(netTotal) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Enhanced Timeline -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden shadow-sm">
                        <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-800/50">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Order Timeline</h2>
                        </div>
                        <div class="px-6 py-5">
                            <ol class="relative">
                                <li
                                    v-for="(event, index) in orderTimeline"
                                    :key="index"
                                    class="relative pb-6 last:pb-0"
                                >
                                    <!-- Connecting line -->
                                    <div
                                        v-if="index < orderTimeline.length - 1"
                                        :class="[
                                            'absolute left-4 top-8 -ml-px h-full w-0.5',
                                            event.completed && orderTimeline[index + 1]?.completed
                                                ? 'bg-green-500'
                                                : 'bg-gray-200 dark:bg-gray-700'
                                        ]"
                                    />

                                    <div class="relative flex items-start gap-4">
                                        <!-- Icon circle -->
                                        <div
                                            :class="[
                                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white dark:ring-gray-800 transition-all',
                                                event.completed
                                                    ? 'bg-green-500 text-white'
                                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-400'
                                            ]"
                                        >
                                            <svg
                                                v-if="event.completed"
                                                class="h-4 w-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="2.5"
                                                stroke="currentColor"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                            <svg
                                                v-else
                                                class="h-4 w-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.5"
                                                stroke="currentColor"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" :d="getTimelineIcon(event.icon)" />
                                            </svg>
                                        </div>

                                        <!-- Content -->
                                        <div class="flex-1 min-w-0 pt-0.5">
                                            <p
                                                :class="[
                                                    'text-sm font-medium',
                                                    event.completed
                                                        ? 'text-gray-900 dark:text-white'
                                                        : 'text-gray-500 dark:text-gray-400'
                                                ]"
                                            >
                                                {{ event.title }}
                                            </p>
                                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                {{ event.description }}
                                            </p>
                                            <p v-if="event.completed" class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                                {{ formatDate(event.date) }}
                                            </p>
                                        </div>
                                    </div>
                                </li>
                            </ol>
                        </div>
                    </div>

                    <!-- Need help? -->
                    <div class="rounded-2xl border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50 dark:bg-indigo-900/20 p-6">
                        <h3 class="text-sm font-semibold text-indigo-900 dark:text-indigo-100">Need help?</h3>
                        <p class="mt-2 text-sm text-indigo-700 dark:text-indigo-300">
                            If you have any questions about your order, our support team is here to help.
                        </p>
                        <Link
                            href="/contact"
                            class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500"
                        >
                            Contact Support
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
