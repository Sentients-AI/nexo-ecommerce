<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';
import { useCurrency } from '@/Composables/useCurrency';

interface ReturnItem {
    id: number;
    product_name: string;
    quantity: number;
    reason: string;
    reason_label: string;
    unit_price_cents: number;
    subtotal_cents: number;
}

interface ReturnRow {
    id: number;
    status: 'pending' | 'approved' | 'rejected' | 'completed';
    notes: string | null;
    admin_notes: string | null;
    reviewed_at: string | null;
    reviewer: string | null;
    refund_id: number | null;
    created_at: string;
    order: { id: number; order_number: string; total_cents: number };
    customer: { name: string; email: string };
    items: ReturnItem[];
    total_refund_cents: number;
}

interface PaginatedReturns {
    data: ReturnRow[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    returns: PaginatedReturns;
    status_filter: string;
    counts: Record<string, number>;
}

const props = defineProps<Props>();
const { formatPrice } = useCurrency();

const tabs = [
    { key: 'pending', label: 'Pending' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'all', label: 'All' },
];

const statusConfig: Record<string, { label: string; class: string }> = {
    pending:   { label: 'Pending',   class: 'bg-amber-500/15 text-amber-400 border border-amber-500/20' },
    approved:  { label: 'Approved',  class: 'bg-accent-500/15 text-accent-400 border border-accent-500/20' },
    rejected:  { label: 'Rejected',  class: 'bg-red-500/15 text-red-400 border border-red-500/20' },
    completed: { label: 'Completed', class: 'bg-navy-700/60 text-navy-300 border border-navy-600/50' },
};

const expandedRow = ref<number | null>(null);
const actionRow = ref<number | null>(null);

function toggleExpand(id: number) {
    expandedRow.value = expandedRow.value === id ? null : id;
}

function makeActionForm(notes = '') {
    return useForm({ admin_notes: notes });
}

const approveForm = ref(makeActionForm());
const rejectForm = ref(makeActionForm());

function approve(row: ReturnRow) {
    approveForm.value.patch(`/vendor/returns/${row.id}/approve`, {
        onSuccess: () => { actionRow.value = null; },
    });
}

function reject(row: ReturnRow) {
    rejectForm.value.patch(`/vendor/returns/${row.id}/reject`, {
        onSuccess: () => { actionRow.value = null; },
    });
}

function setTab(key: string) {
    router.get('/vendor/returns', { status: key }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Return Requests" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Returns</span>
            </div>
        </template>

        <div class="mb-6">
            <h1 class="text-xl font-bold text-white">Return Requests</h1>
            <p class="mt-1 text-sm text-navy-400">Review and action customer return requests</p>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-4 bg-navy-900/60 border border-navy-800/60 rounded-xl p-1 w-fit">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                @click="setTab(tab.key)"
                :class="[
                    'px-4 py-1.5 rounded-lg text-sm font-medium transition-colors',
                    status_filter === tab.key
                        ? 'bg-brand-500 text-white'
                        : 'text-navy-400 hover:text-white',
                ]"
            >
                {{ tab.label }}
                <span
                    v-if="tab.key !== 'all' && counts[tab.key]"
                    class="ml-1.5 text-xs rounded-full px-1.5 py-0.5"
                    :class="status_filter === tab.key ? 'bg-white/20 text-white' : 'bg-navy-700 text-navy-300'"
                >
                    {{ counts[tab.key] }}
                </span>
            </button>
        </div>

        <!-- Table -->
        <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60">
            <div v-if="returns.data.length === 0" class="p-10 text-center text-navy-500 text-sm">
                No return requests found.
            </div>

            <div v-else class="divide-y divide-navy-800/40">
                <div
                    v-for="row in returns.data"
                    :key="row.id"
                    class="p-5"
                >
                    <!-- Row header -->
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="text-white font-semibold">{{ row.order.order_number }}</span>
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium border"
                                    :class="statusConfig[row.status]?.class"
                                >
                                    {{ statusConfig[row.status]?.label ?? row.status }}
                                </span>
                                <span class="text-xs text-navy-500">{{ row.created_at }}</span>
                            </div>
                            <p class="text-sm text-navy-400 mt-1">
                                {{ row.customer.name }} · {{ row.customer.email }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-white font-semibold">{{ formatPrice(row.total_refund_cents) }}</div>
                            <div class="text-xs text-navy-500">refund value</div>
                        </div>
                    </div>

                    <!-- Items summary (collapsed) -->
                    <button
                        @click="toggleExpand(row.id)"
                        class="mt-3 text-xs text-brand-400 hover:text-brand-300 transition-colors"
                    >
                        {{ expandedRow === row.id ? 'Hide' : 'Show' }} {{ row.items.length }} item(s)
                        <span class="ml-0.5">{{ expandedRow === row.id ? '▲' : '▼' }}</span>
                    </button>

                    <!-- Expanded items -->
                    <div v-if="expandedRow === row.id" class="mt-3 rounded-xl border border-navy-800/60 overflow-hidden">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="bg-navy-800/40">
                                    <th class="text-left px-4 py-2 text-navy-500 font-medium">Product</th>
                                    <th class="text-center px-4 py-2 text-navy-500 font-medium">Qty</th>
                                    <th class="text-left px-4 py-2 text-navy-500 font-medium">Reason</th>
                                    <th class="text-right px-4 py-2 text-navy-500 font-medium">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-navy-800/40">
                                <tr v-for="item in row.items" :key="item.id">
                                    <td class="px-4 py-2 text-navy-300">{{ item.product_name }}</td>
                                    <td class="px-4 py-2 text-center text-navy-300">{{ item.quantity }}</td>
                                    <td class="px-4 py-2 text-navy-400">{{ item.reason_label }}</td>
                                    <td class="px-4 py-2 text-right text-white">{{ formatPrice(item.subtotal_cents) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Customer notes -->
                    <p v-if="row.notes" class="mt-3 text-xs text-navy-400 italic">
                        "{{ row.notes }}"
                    </p>

                    <!-- Admin notes (if reviewed) -->
                    <p v-if="row.admin_notes" class="mt-2 text-xs text-navy-500">
                        Admin note: {{ row.admin_notes }}
                    </p>

                    <!-- Actions (pending only) -->
                    <div v-if="row.status === 'pending'" class="mt-4">
                        <div v-if="actionRow !== row.id" class="flex gap-2">
                            <button
                                @click="actionRow = row.id; approveForm = makeActionForm(); rejectForm = makeActionForm()"
                                class="rounded-lg bg-accent-600 hover:bg-accent-500 text-white text-xs font-semibold px-4 py-2 transition-colors"
                            >
                                Approve & Issue Refund
                            </button>
                            <button
                                @click="actionRow = row.id; approveForm = makeActionForm(); rejectForm = makeActionForm()"
                                class="rounded-lg bg-navy-700 hover:bg-navy-600 text-navy-200 text-xs font-semibold px-4 py-2 transition-colors"
                            >
                                Reject
                            </button>
                        </div>

                        <div v-else class="space-y-3">
                            <textarea
                                v-model="approveForm.admin_notes"
                                placeholder="Admin notes (optional)…"
                                rows="2"
                                class="block w-full rounded-xl border border-navy-700 bg-navy-800 text-sm px-4 py-2 text-white placeholder-navy-500 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 resize-none"
                            />
                            <div class="flex gap-2">
                                <button
                                    @click="approve(row)"
                                    :disabled="approveForm.processing"
                                    class="rounded-lg bg-accent-600 hover:bg-accent-500 text-white text-xs font-semibold px-4 py-2 transition-colors disabled:opacity-50"
                                >
                                    {{ approveForm.processing ? 'Processing…' : 'Confirm Approve' }}
                                </button>
                                <button
                                    @click="reject(row)"
                                    :disabled="rejectForm.processing"
                                    class="rounded-lg bg-red-700 hover:bg-red-600 text-white text-xs font-semibold px-4 py-2 transition-colors disabled:opacity-50"
                                >
                                    {{ rejectForm.processing ? 'Processing…' : 'Confirm Reject' }}
                                </button>
                                <button
                                    @click="actionRow = null"
                                    class="rounded-lg text-navy-400 hover:text-white text-xs font-medium px-3 py-2 transition-colors"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="returns.last_page > 1" class="flex items-center justify-center gap-1 p-4 border-t border-navy-800/60">
                <Link
                    v-for="link in returns.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                        link.active
                            ? 'bg-brand-500 text-white'
                            : link.url
                                ? 'text-navy-400 hover:bg-navy-800 hover:text-white'
                                : 'text-navy-600 cursor-default pointer-events-none',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </VendorLayout>
</template>
