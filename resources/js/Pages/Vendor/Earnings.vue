<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';
import { useCurrency } from '@/Composables/useCurrency';

interface EarningRow {
    id: number;
    order_number: string | null;
    gross_amount_cents: number;
    platform_fee_cents: number;
    net_amount_cents: number;
    refunded_amount_cents: number;
    effective_net_cents: number;
    status: 'pending' | 'available' | 'paid_out';
    available_at: string | null;
    paid_out_at: string | null;
    created_at: string;
}

interface PaginatedEarnings {
    data: EarningRow[];
    links: { url: string | null; label: string; active: boolean }[];
    meta: { current_page: number; last_page: number; total: number };
}

interface Props {
    stats: {
        pending_cents: number;
        available_cents: number;
        paid_out_cents: number;
        this_month_cents: number;
    };
    earnings: PaginatedEarnings;
    platform_fee_rate: number;
}

const props = defineProps<Props>();

const { formatPrice } = useCurrency();

const statusConfig: Record<string, { label: string; class: string }> = {
    pending:   { label: 'Pending',    class: 'bg-amber-500/15 text-amber-400 border border-amber-500/20' },
    available: { label: 'Available',  class: 'bg-accent-500/15 text-accent-400 border border-accent-500/20' },
    paid_out:  { label: 'Paid Out',   class: 'bg-navy-700/60 text-navy-300 border border-navy-600/50' },
};

const kpis = [
    {
        label: 'This Month',
        value: props.stats.this_month_cents,
        color: 'text-brand-400',
        bg: 'bg-brand-500/10',
        border: 'border-brand-500/20',
        icon: 'calendar',
    },
    {
        label: 'Available',
        value: props.stats.available_cents,
        color: 'text-accent-400',
        bg: 'bg-accent-500/10',
        border: 'border-accent-500/20',
        icon: 'available',
    },
    {
        label: 'Pending (Hold)',
        value: props.stats.pending_cents,
        color: 'text-amber-400',
        bg: 'bg-amber-500/10',
        border: 'border-amber-500/20',
        icon: 'pending',
    },
    {
        label: 'Total Paid Out',
        value: props.stats.paid_out_cents,
        color: 'text-blue-400',
        bg: 'bg-blue-500/10',
        border: 'border-blue-500/20',
        icon: 'payout',
    },
];
</script>

<template>
    <Head title="Earnings" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Earnings</span>
            </div>
        </template>

        <div class="mb-6 flex items-start justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Earnings</h1>
                <p class="mt-1 text-sm text-navy-400">
                    Your net earnings after a {{ (platform_fee_rate * 100).toFixed(0) }}% platform fee.
                    Funds are held for 7 days before becoming available.
                </p>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 mb-6">
            <div
                v-for="kpi in kpis"
                :key="kpi.label"
                class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5"
            >
                <div :class="['flex h-10 w-10 items-center justify-center rounded-xl border mb-3', kpi.bg, kpi.border]">
                    <!-- Calendar icon -->
                    <svg v-if="kpi.icon === 'calendar'" :class="['h-5 w-5', kpi.color]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                    <!-- Check icon (available) -->
                    <svg v-else-if="kpi.icon === 'available'" :class="['h-5 w-5', kpi.color]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <!-- Clock icon (pending) -->
                    <svg v-else-if="kpi.icon === 'pending'" :class="['h-5 w-5', kpi.color]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <!-- Banknotes icon (payout) -->
                    <svg v-else :class="['h-5 w-5', kpi.color]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                    </svg>
                </div>
                <div class="text-2xl font-bold text-white">{{ formatPrice(kpi.value) }}</div>
                <div class="mt-0.5 text-xs text-navy-400">{{ kpi.label }}</div>
            </div>
        </div>

        <!-- Earnings table -->
        <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60">
            <div class="flex items-center justify-between p-5 border-b border-navy-800/60">
                <div>
                    <h2 class="text-sm font-semibold text-white">Earnings History</h2>
                    <p class="text-xs text-navy-400 mt-0.5">One record per paid order</p>
                </div>
                <div class="text-xs text-navy-500 bg-navy-800/60 rounded-lg px-2.5 py-1">
                    {{ earnings.meta.total }} records
                </div>
            </div>

            <div v-if="earnings.data.length === 0" class="p-10 text-center text-navy-500 text-sm">
                No earnings yet. They appear here when orders are paid.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-navy-800/60">
                            <th class="text-left px-5 py-3 text-xs font-medium text-navy-500">Order</th>
                            <th class="text-right px-5 py-3 text-xs font-medium text-navy-500">Gross</th>
                            <th class="text-right px-5 py-3 text-xs font-medium text-navy-500">Platform Fee</th>
                            <th class="text-right px-5 py-3 text-xs font-medium text-navy-500">Refunded</th>
                            <th class="text-right px-5 py-3 text-xs font-medium text-navy-500">Net</th>
                            <th class="text-center px-5 py-3 text-xs font-medium text-navy-500">Status</th>
                            <th class="text-right px-5 py-3 text-xs font-medium text-navy-500">Available</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-navy-800/40">
                        <tr v-for="row in earnings.data" :key="row.id" class="hover:bg-navy-800/30 transition-colors">
                            <td class="px-5 py-3 text-white font-medium">
                                <Link
                                    :href="route('vendor.orders.index')"
                                    class="hover:text-brand-400 transition-colors"
                                >
                                    {{ row.order_number ?? '—' }}
                                </Link>
                                <div class="text-xs text-navy-500 mt-0.5">{{ row.created_at }}</div>
                            </td>
                            <td class="px-5 py-3 text-right text-navy-300">{{ formatPrice(row.gross_amount_cents) }}</td>
                            <td class="px-5 py-3 text-right text-red-400">-{{ formatPrice(row.platform_fee_cents) }}</td>
                            <td class="px-5 py-3 text-right" :class="row.refunded_amount_cents > 0 ? 'text-amber-400' : 'text-navy-600'">
                                {{ row.refunded_amount_cents > 0 ? '-' + formatPrice(row.refunded_amount_cents) : '—' }}
                            </td>
                            <td class="px-5 py-3 text-right font-semibold text-white">
                                {{ formatPrice(row.effective_net_cents) }}
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium border"
                                    :class="statusConfig[row.status]?.class ?? 'bg-navy-700 text-navy-300 border-navy-600'"
                                >
                                    {{ statusConfig[row.status]?.label ?? row.status }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right text-navy-400 text-xs">
                                {{ row.available_at ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="earnings.meta.last_page > 1" class="flex items-center justify-center gap-1 p-4 border-t border-navy-800/60">
                <Link
                    v-for="link in earnings.links"
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
