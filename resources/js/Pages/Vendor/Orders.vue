<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';
import { useCurrency } from '@/Composables/useCurrency';

interface Customer {
    name: string;
    email: string;
}

interface OrderRow {
    id: number;
    order_number: string;
    status: string;
    subtotal_cents: number;
    discount_cents: number;
    shipping_cost_cents: number;
    total_cents: number;
    items_count: number;
    customer: Customer | null;
    created_at: string;
}

interface StatusInfo {
    value: string;
    label: string;
    count: number;
}

interface PaginatedOrders {
    data: OrderRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    orders: PaginatedOrders;
    statuses: StatusInfo[];
    status_filter: string | null;
}

const props = defineProps<Props>();

const { formatPrice: formatCurrency } = useCurrency();

const statusConfig: Record<string, { label: string; class: string }> = {
    pending:            { label: 'Pending',            class: 'bg-amber-500/15 text-amber-400 border border-amber-500/20' },
    paid:               { label: 'Paid',               class: 'bg-accent-500/15 text-accent-400 border border-accent-500/20' },
    awaiting_payment:   { label: 'Awaiting Payment',   class: 'bg-yellow-500/15 text-yellow-400 border border-yellow-500/20' },
    processing:         { label: 'Processing',         class: 'bg-brand-500/15 text-brand-400 border border-brand-500/20' },
    packed:             { label: 'Packed',             class: 'bg-indigo-500/15 text-indigo-400 border border-indigo-500/20' },
    shipped:            { label: 'Shipped',            class: 'bg-blue-500/15 text-blue-400 border border-blue-500/20' },
    delivered:          { label: 'Delivered',          class: 'bg-accent-500/15 text-accent-400 border border-accent-500/20' },
    fulfilled:          { label: 'Fulfilled',          class: 'bg-accent-500/15 text-accent-400 border border-accent-500/20' },
    cancelled:          { label: 'Cancelled',          class: 'bg-red-500/15 text-red-400 border border-red-500/20' },
    refunded:           { label: 'Refunded',           class: 'bg-slate-500/15 text-slate-400 border border-slate-500/20' },
    partially_refunded: { label: 'Part. Refunded',     class: 'bg-orange-500/15 text-orange-400 border border-orange-500/20' },
    failed:             { label: 'Failed',             class: 'bg-red-500/15 text-red-400 border border-red-500/20' },
};

function getStatusConfig(status: string) {
    return statusConfig[status] ?? { label: status, class: 'bg-navy-700 text-navy-300 border border-navy-600' };
}

const fulfillmentStatuses = ['packed', 'shipped', 'delivered', 'cancelled'];

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function filterByStatus(status: string | null): void {
    router.get('/vendor/orders', status ? { status } : {}, { preserveState: true });
}

// Status update modal
const showStatusModal = ref(false);
const selectedOrder = ref<OrderRow | null>(null);

function openStatusModal(order: OrderRow): void {
    selectedOrder.value = order;
    showStatusModal.value = true;
    statusForm.status = order.status;
    shipForm.reset();
}

const statusForm = useForm({ status: '' });
const shipForm = useForm({
    carrier: '',
    tracking_number: '',
    estimated_delivery_at: '',
});

const isShipMode = computed(() => statusForm.status === 'shipped');

function submitStatusUpdate(): void {
    if (!selectedOrder.value) { return; }

    if (isShipMode.value) {
        shipForm.post(`/vendor/orders/${selectedOrder.value.id}/ship`, {
            onSuccess: () => {
                showStatusModal.value = false;
                selectedOrder.value = null;
                shipForm.reset();
            },
        });
        return;
    }

    statusForm.patch(`/vendor/orders/${selectedOrder.value.id}/status`, {
        onSuccess: () => {
            showStatusModal.value = false;
            selectedOrder.value = null;
        },
    });
}
</script>

<template>
    <Head title="Live Orders" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Live Orders</span>
            </div>
        </template>

        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Live Orders</h1>
                <p class="mt-1 text-sm text-navy-400">{{ orders.total }} orders total</p>
            </div>
            <a
                :href="status_filter ? `/vendor/orders/export?status=${status_filter}` : '/vendor/orders/export'"
                class="inline-flex items-center gap-2 rounded-xl border border-navy-700/60 bg-navy-800/60 px-4 py-2 text-sm font-medium text-navy-300 transition-colors hover:bg-navy-700/60 hover:text-white"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Export CSV
            </a>
        </div>

        <!-- Status filter tabs -->
        <div class="mb-5 flex gap-2 overflow-x-auto pb-1">
            <button
                @click="filterByStatus(null)"
                class="shrink-0 rounded-xl px-4 py-2 text-sm font-medium transition-all"
                :class="!status_filter
                    ? 'bg-brand-500/15 text-brand-300 border border-brand-500/20'
                    : 'text-navy-400 hover:text-white hover:bg-navy-800/70'"
            >
                All <span class="ml-1.5 text-xs text-navy-500">{{ orders.total }}</span>
            </button>
            <button
                v-for="s in statuses"
                :key="s.value"
                @click="filterByStatus(s.value)"
                class="shrink-0 rounded-xl px-4 py-2 text-sm font-medium transition-all"
                :class="status_filter === s.value
                    ? 'bg-brand-500/15 text-brand-300 border border-brand-500/20'
                    : 'text-navy-400 hover:text-white hover:bg-navy-800/70'"
            >
                {{ s.label }} <span class="ml-1.5 text-xs opacity-60">{{ s.count }}</span>
            </button>
        </div>

        <!-- Orders table -->
        <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 overflow-hidden">
            <div v-if="orders.data.length === 0" class="flex flex-col items-center justify-center py-20">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-navy-800 mb-4">
                    <svg class="h-6 w-6 text-navy-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                    </svg>
                </div>
                <p class="text-sm text-navy-400">No orders found</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-navy-800/40">
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">Order</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">Customer</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">Status</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-navy-500">Items</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-navy-500">Total</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-navy-500">Date</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-navy-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-navy-800/30">
                        <tr
                            v-for="order in orders.data"
                            :key="order.id"
                            class="hover:bg-navy-800/30 transition-colors"
                        >
                            <td class="px-5 py-3.5 font-medium text-white">#{{ order.order_number }}</td>
                            <td class="px-5 py-3.5">
                                <div class="text-white text-xs font-medium">{{ order.customer?.name ?? '—' }}</div>
                                <div class="text-navy-500 text-xs">{{ order.customer?.email }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="getStatusConfig(order.status).class">
                                    {{ getStatusConfig(order.status).label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-center text-navy-300">{{ order.items_count }}</td>
                            <td class="px-5 py-3.5 text-right text-accent-400 font-semibold">{{ formatCurrency(order.total_cents) }}</td>
                            <td class="px-5 py-3.5 text-right text-navy-400 text-xs whitespace-nowrap">{{ formatDate(order.created_at) }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <button
                                    @click="openStatusModal(order)"
                                    class="text-xs text-brand-400 hover:text-brand-300 transition-colors font-medium"
                                >
                                    Update
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="orders.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-navy-800/40">
                <p class="text-xs text-navy-500">
                    Page {{ orders.current_page }} of {{ orders.last_page }}
                </p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in orders.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        class="px-3 py-1 rounded-lg text-xs transition-colors"
                        :class="link.active
                            ? 'bg-brand-500/20 text-brand-300 border border-brand-500/30'
                            : link.url
                                ? 'text-navy-400 hover:text-white hover:bg-navy-800'
                                : 'text-navy-700 cursor-not-allowed'"
                    />
                </div>
            </div>
        </div>

        <!-- Status update modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showStatusModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-navy-950/80 backdrop-blur-sm" @click.self="showStatusModal = false">
                    <div class="w-full max-w-sm rounded-2xl border border-navy-700/60 bg-navy-900 p-6 shadow-xl">
                        <h3 class="text-base font-semibold text-white mb-1">Update Order Status</h3>
                        <p class="text-sm text-navy-400 mb-5">Order #{{ selectedOrder?.order_number }}</p>

                        <div class="grid grid-cols-2 gap-2 mb-5">
                            <button
                                v-for="status in fulfillmentStatuses"
                                :key="status"
                                @click="statusForm.status = status"
                                class="rounded-xl px-3 py-2.5 text-sm font-medium transition-all border"
                                :class="statusForm.status === status
                                    ? 'bg-brand-500/20 text-brand-300 border-brand-500/40'
                                    : 'text-navy-400 border-navy-700/50 hover:text-white hover:bg-navy-800/70'"
                            >
                                {{ getStatusConfig(status).label }}
                            </button>
                        </div>

                        <!-- Tracking form (shown when "shipped" is selected) -->
                        <Transition
                            enter-active-class="duration-200 ease-out"
                            enter-from-class="opacity-0 -translate-y-1"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="duration-150 ease-in"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 -translate-y-1"
                        >
                            <div v-if="isShipMode" class="mb-5 space-y-3 rounded-xl border border-blue-500/20 bg-blue-500/5 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-blue-400">Shipment Details</p>

                                <div>
                                    <label class="mb-1 block text-xs text-navy-400">Carrier <span class="text-red-400">*</span></label>
                                    <input
                                        v-model="shipForm.carrier"
                                        type="text"
                                        placeholder="e.g. FedEx, UPS, DHL"
                                        class="w-full rounded-lg border border-navy-700/60 bg-navy-800 px-3 py-2 text-sm text-white placeholder-navy-500 focus:border-brand-500/50 focus:outline-none"
                                    />
                                    <p v-if="shipForm.errors.carrier" class="mt-1 text-xs text-red-400">{{ shipForm.errors.carrier }}</p>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs text-navy-400">Tracking Number <span class="text-red-400">*</span></label>
                                    <input
                                        v-model="shipForm.tracking_number"
                                        type="text"
                                        placeholder="e.g. 1Z999AA10123456784"
                                        class="w-full rounded-lg border border-navy-700/60 bg-navy-800 px-3 py-2 text-sm text-white placeholder-navy-500 focus:border-brand-500/50 focus:outline-none"
                                    />
                                    <p v-if="shipForm.errors.tracking_number" class="mt-1 text-xs text-red-400">{{ shipForm.errors.tracking_number }}</p>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs text-navy-400">Estimated Delivery Date</label>
                                    <input
                                        v-model="shipForm.estimated_delivery_at"
                                        type="date"
                                        class="w-full rounded-lg border border-navy-700/60 bg-navy-800 px-3 py-2 text-sm text-white focus:border-brand-500/50 focus:outline-none"
                                    />
                                    <p v-if="shipForm.errors.estimated_delivery_at" class="mt-1 text-xs text-red-400">{{ shipForm.errors.estimated_delivery_at }}</p>
                                </div>

                                <p v-if="shipForm.errors.ship" class="text-xs text-red-400">{{ shipForm.errors.ship }}</p>
                            </div>
                        </Transition>

                        <div class="flex gap-3">
                            <button
                                @click="showStatusModal = false"
                                class="flex-1 rounded-xl border border-navy-700/50 py-2.5 text-sm font-medium text-navy-400 hover:text-white hover:bg-navy-800 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                @click="submitStatusUpdate"
                                :disabled="(isShipMode ? shipForm.processing : statusForm.processing) || !statusForm.status"
                                class="flex-1 rounded-xl bg-brand-500 py-2.5 text-sm font-medium text-white hover:bg-brand-400 disabled:opacity-50 transition-colors"
                            >
                                <span v-if="isShipMode">{{ shipForm.processing ? 'Shipping…' : 'Mark as Shipped' }}</span>
                                <span v-else>{{ statusForm.processing ? 'Saving…' : 'Save' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </VendorLayout>
</template>
