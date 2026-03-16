<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import { useLocale } from '@/Composables/useLocale';
import { useOrderUpdates } from '@/Composables/useOrderUpdates';
import { OrderStatus } from '@/types/models';

interface OrderSummary {
    id: number;
    order_number: string;
    status: string;
    total_cents: number;
    currency: string;
    items_count: number;
    created_at: string;
    first_item_name?: string;
}

interface Props {
    orders: {
        data: OrderSummary[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        links: {
            prev: string | null;
            next: string | null;
        };
    };
    filters?: {
        status?: string;
    };
}

const props = defineProps<Props>();
const { localePath } = useLocale();
const page = usePage();

const authUser = page.props.auth as { user?: { id: number } } | undefined;
if (authUser?.user?.id) {
    useOrderUpdates(authUser.user.id, () => {
        router.reload({ only: ['orders'] });
    });
}

const statusFilter = ref(props.filters?.status || '');
const hoveredOrderId = ref<number | null>(null);

const statusOptions = [
    { value: '', label: 'All Orders' },
    { value: OrderStatus.Pending, label: 'Pending' },
    { value: OrderStatus.AwaitingPayment, label: 'Awaiting Payment' },
    { value: OrderStatus.Paid, label: 'Paid' },
    { value: OrderStatus.Packed, label: 'Packed' },
    { value: OrderStatus.Shipped, label: 'Shipped' },
    { value: OrderStatus.Delivered, label: 'Delivered' },
    { value: OrderStatus.Fulfilled, label: 'Fulfilled' },
    { value: OrderStatus.Cancelled, label: 'Cancelled' },
    { value: OrderStatus.Refunded, label: 'Refunded' },
];

function formatPrice(cents: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatRelativeDate(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = now.getTime() - date.getTime();
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return 'Today';
    } else if (diffDays === 1) {
        return 'Yesterday';
    } else if (diffDays < 7) {
        return `${diffDays} days ago`;
    } else if (diffDays < 30) {
        const weeks = Math.floor(diffDays / 7);
        return `${weeks} week${weeks > 1 ? 's' : ''} ago`;
    } else {
        return formatDate(dateString);
    }
}

function applyFilters() {
    const params: Record<string, string> = {};
    if (statusFilter.value) {
        params.status = statusFilter.value;
    }
    router.get(localePath('/orders'), params, { preserveState: true, preserveScroll: true });
}

watch(statusFilter, () => {
    applyFilters();
});

const orderStats = computed(() => {
    const total = props.orders.meta.total;
    return {
        total,
        showing: props.orders.data.length,
    };
});
</script>

<template>
    <Head title="My Orders" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">My Orders</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Track and manage your orders
                    </p>
                </div>

                <!-- Status filter -->
                <div class="flex items-center gap-3">
                    <label for="status-filter" class="sr-only">Filter by status</label>
                    <select
                        id="status-filter"
                        v-model="statusFilter"
                        class="rounded-xl border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 pl-4 pr-10"
                    >
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="orders.data.length === 0" class="mt-12 text-center py-16">
                <div class="mx-auto h-24 w-24 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                    <svg class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                </div>
                <h3 class="mt-6 text-lg font-semibold text-gray-900 dark:text-white">No orders found</h3>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    {{ statusFilter ? 'No orders match your current filter.' : "You haven't placed any orders yet." }}
                </p>
                <div class="mt-6 flex items-center justify-center gap-4">
                    <button
                        v-if="statusFilter"
                        @click="statusFilter = ''"
                        class="rounded-xl border border-gray-300 dark:border-gray-600 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                    >
                        Clear Filter
                    </button>
                    <Link
                        :href="localePath('/products')"
                        class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors"
                    >
                        Start Shopping
                    </Link>
                </div>
            </div>

            <!-- Orders list -->
            <div v-else class="mt-8">
                <!-- Stats bar -->
                <div class="mb-6 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                    <p>Showing {{ orderStats.showing }} of {{ orderStats.total }} orders</p>
                </div>

                <!-- Mobile: Card view -->
                <div class="space-y-4 lg:hidden">
                    <Link
                        v-for="order in orders.data"
                        :key="order.id"
                        :href="localePath(`/orders/${order.id}`)"
                        class="block rounded-2xl bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-700 transition-all"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">
                                    {{ order.order_number }}
                                </p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatRelativeDate(order.created_at) }}
                                </p>
                            </div>
                            <StatusBadge type="order" :status="order.status" />
                        </div>

                        <div class="mt-4 flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-700">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ order.items_count }} {{ order.items_count === 1 ? 'item' : 'items' }}
                                </p>
                            </div>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ formatPrice(order.total_cents) }}
                            </p>
                        </div>

                        <div class="mt-4 flex items-center justify-end text-indigo-600 dark:text-indigo-400">
                            <span class="text-sm font-medium">View Details</span>
                            <svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </div>
                    </Link>
                </div>

                <!-- Desktop: Enhanced table -->
                <div class="hidden lg:block overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800/50">
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Order
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Date
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Items
                                </th>
                                <th scope="col" class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Total
                                </th>
                                <th scope="col" class="relative px-6 py-4">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="order in orders.data"
                                :key="order.id"
                                @mouseenter="hoveredOrderId = order.id"
                                @mouseleave="hoveredOrderId = null"
                                class="group hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors cursor-pointer"
                                @click="router.visit(`/orders/${order.id}`)"
                            >
                                <td class="whitespace-nowrap px-6 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/50">
                                            <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-white">
                                                {{ order.order_number }}
                                            </p>
                                            <p v-if="order.first_item_name" class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-[200px]">
                                                {{ order.first_item_name }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-5">
                                    <div>
                                        <p class="text-sm text-gray-900 dark:text-white">{{ formatDate(order.created_at) }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatRelativeDate(order.created_at) }}</p>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-5">
                                    <StatusBadge type="order" :status="order.status" />
                                </td>
                                <td class="whitespace-nowrap px-6 py-5 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                        {{ order.items_count }} {{ order.items_count === 1 ? 'item' : 'items' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-5 text-right">
                                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                                        {{ formatPrice(order.total_cents) }}
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-5 text-right">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 text-sm font-medium text-indigo-600 dark:text-indigo-400 transition-opacity',
                                            hoveredOrderId === order.id ? 'opacity-100' : 'opacity-0'
                                        ]"
                                    >
                                        View
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="orders.meta.last_page > 1" class="mt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Page {{ orders.meta.current_page }} of {{ orders.meta.last_page }}
                    </p>
                    <nav class="flex items-center gap-2">
                        <Link
                            v-if="orders.links.prev"
                            :href="orders.links.prev"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                            Previous
                        </Link>
                        <span v-else class="inline-flex items-center gap-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-4 py-2.5 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                            Previous
                        </span>
                        <Link
                            v-if="orders.links.next"
                            :href="orders.links.next"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            Next
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </Link>
                        <span v-else class="inline-flex items-center gap-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-4 py-2.5 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                            Next
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </span>
                    </nav>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
