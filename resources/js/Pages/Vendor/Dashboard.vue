<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';

interface OrderRow {
    id: number;
    order_number: string;
    status: string;
    total_cents: number;
    created_at: string;
    user: { name: string; email: string } | null;
}

interface LowStockProduct {
    id: number;
    name: string;
    slug: string;
    stock: { quantity: number } | null;
}

interface ChartPoint {
    date: string;
    revenue: number;
}

interface Props {
    stats: {
        revenue_today: number;
        revenue_this_month: number;
        orders_today: number;
        pending_orders: number;
        total_products: number;
    };
    recent_orders: OrderRow[];
    low_stock_products: LowStockProduct[];
    chart_data: ChartPoint[];
}

const props = defineProps<Props>();

function formatCurrency(cents: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(cents / 100);
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const maxRevenue = computed(() =>
    Math.max(...props.chart_data.map(d => d.revenue), 1)
);

const statusConfig: Record<string, { label: string; class: string }> = {
    pending:    { label: 'Pending',    class: 'bg-amber-500/15 text-amber-400 border border-amber-500/20' },
    processing: { label: 'Processing', class: 'bg-brand-500/15 text-brand-400 border border-brand-500/20' },
    shipped:    { label: 'Shipped',    class: 'bg-blue-500/15 text-blue-400 border border-blue-500/20' },
    delivered:  { label: 'Delivered',  class: 'bg-accent-500/15 text-accent-400 border border-accent-500/20' },
    cancelled:  { label: 'Cancelled',  class: 'bg-red-500/15 text-red-400 border border-red-500/20' },
    refunded:   { label: 'Refunded',   class: 'bg-slate-500/15 text-slate-400 border border-slate-500/20' },
};

function getStatusConfig(status: string) {
    return statusConfig[status] ?? { label: status, class: 'bg-navy-700 text-navy-300 border border-navy-600' };
}

const kpis = computed(() => [
    {
        label: 'Revenue Today',
        value: formatCurrency(props.stats.revenue_today),
        icon: 'revenue',
        color: 'text-brand-400',
        bg: 'bg-brand-500/10',
        border: 'border-brand-500/20',
        trend: '+8.2%',
        trendUp: true,
    },
    {
        label: 'This Month',
        value: formatCurrency(props.stats.revenue_this_month),
        icon: 'calendar',
        color: 'text-accent-400',
        bg: 'bg-accent-500/10',
        border: 'border-accent-500/20',
        trend: '+12.5%',
        trendUp: true,
    },
    {
        label: 'Orders Today',
        value: String(props.stats.orders_today),
        icon: 'orders',
        color: 'text-blue-400',
        bg: 'bg-blue-500/10',
        border: 'border-blue-500/20',
        trend: `${props.stats.pending_orders} pending`,
        trendUp: null,
    },
    {
        label: 'Active Products',
        value: String(props.stats.total_products),
        icon: 'products',
        color: 'text-amber-400',
        bg: 'bg-amber-500/10',
        border: 'border-amber-500/20',
        trend: `${props.low_stock_products.length} low stock`,
        trendUp: props.low_stock_products.length === 0,
    },
]);
</script>

<template>
    <Head title="Vendor Dashboard" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Dashboard</span>
            </div>
        </template>

        <!-- Page heading -->
        <div class="mb-6">
            <h1 class="text-xl font-bold text-white">Command Center</h1>
            <p class="mt-1 text-sm text-navy-400">
                Real-time overview of your store performance
            </p>
        </div>

        <!-- ── KPI BENTO CARDS ── -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 mb-6">
            <div
                v-for="kpi in kpis"
                :key="kpi.label"
                class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border', kpi.bg, kpi.border]">
                        <!-- Revenue icon -->
                        <svg v-if="kpi.icon === 'revenue'" :class="['h-5 w-5', kpi.color]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Calendar icon -->
                        <svg v-else-if="kpi.icon === 'calendar'" :class="['h-5 w-5', kpi.color]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        <!-- Orders icon -->
                        <svg v-else-if="kpi.icon === 'orders'" :class="['h-5 w-5', kpi.color]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                        </svg>
                        <!-- Products icon -->
                        <svg v-else :class="['h-5 w-5', kpi.color]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                    </div>
                    <!-- Trend -->
                    <span
                        class="text-xs font-medium rounded-full px-2 py-0.5"
                        :class="kpi.trendUp === true
                            ? 'bg-accent-500/10 text-accent-400'
                            : kpi.trendUp === false
                                ? 'bg-amber-500/10 text-amber-400'
                                : 'bg-navy-700/50 text-navy-400'"
                    >
                        {{ kpi.trend }}
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-bold text-white">{{ kpi.value }}</div>
                    <div class="mt-0.5 text-xs text-navy-400">{{ kpi.label }}</div>
                </div>
            </div>
        </div>

        <!-- ── MAIN GRID: CHART + ALERTS ── -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 mb-6">
            <!-- Revenue chart (spans 2/3) -->
            <div class="bento lg:col-span-2 rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="text-sm font-semibold text-white">Revenue (7 days)</h2>
                        <p class="text-xs text-navy-400 mt-0.5">Daily revenue breakdown</p>
                    </div>
                    <div class="text-xs text-navy-500 bg-navy-800/60 rounded-lg px-2.5 py-1">Last 7 days</div>
                </div>

                <!-- Bar chart -->
                <div class="flex items-end gap-2 h-36">
                    <div
                        v-for="(point, i) in chart_data"
                        :key="i"
                        class="group flex-1 flex flex-col items-center gap-1"
                    >
                        <div
                            class="w-full rounded-t-lg transition-all duration-300 relative"
                            :class="i === chart_data.length - 1 ? 'bg-brand-500' : 'bg-navy-700 group-hover:bg-brand-500/70'"
                            :style="`height: ${Math.max((point.revenue / maxRevenue) * 100, 4)}%`"
                        >
                            <!-- Tooltip on hover -->
                            <div class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap bg-navy-800 border border-navy-700 rounded-lg px-2 py-1 text-xs text-white">
                                ${{ point.revenue.toFixed(0) }}
                            </div>
                        </div>
                        <span class="text-[10px] text-navy-500 group-hover:text-navy-300 transition-colors">{{ point.date }}</span>
                    </div>
                </div>
            </div>

            <!-- Low stock alerts (1/3) -->
            <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-white">Low Stock Alerts</h2>
                    <span
                        class="text-xs font-bold rounded-full px-2 py-0.5"
                        :class="low_stock_products.length > 0
                            ? 'bg-amber-500/15 text-amber-400'
                            : 'bg-accent-500/15 text-accent-400'"
                    >
                        {{ low_stock_products.length }}
                    </span>
                </div>

                <div v-if="low_stock_products.length === 0" class="flex flex-col items-center justify-center py-8 text-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent-500/15 mb-3">
                        <svg class="h-5 w-5 text-accent-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-xs text-navy-400">All products well stocked</p>
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="product in low_stock_products"
                        :key="product.id"
                        class="flex items-center gap-3 rounded-xl bg-navy-800/50 px-3 py-2.5"
                    >
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-500/15">
                            <svg class="h-3.5 w-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-white truncate">{{ product.name }}</p>
                            <p class="text-xs text-amber-400">{{ product.stock?.quantity ?? 0 }} remaining</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── RECENT ORDERS ── -->
        <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-navy-800/60">
                <div>
                    <h2 class="text-sm font-semibold text-white">Recent Orders</h2>
                    <p class="text-xs text-navy-400 mt-0.5">Latest customer orders</p>
                </div>
                <Link
                    href="/vendor/orders"
                    class="text-xs font-medium text-brand-400 hover:text-brand-300 transition-colors flex items-center gap-1"
                >
                    View all
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </Link>
            </div>

            <!-- Empty state -->
            <div v-if="recent_orders.length === 0" class="flex flex-col items-center justify-center py-16 text-center px-5">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-navy-800 mb-4">
                    <svg class="h-6 w-6 text-navy-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                    </svg>
                </div>
                <p class="text-sm text-navy-400">No orders yet</p>
            </div>

            <!-- Orders table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-navy-800/40">
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">Order</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">Customer</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">Status</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-navy-500">Total</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-navy-500">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-navy-800/30">
                        <tr
                            v-for="order in recent_orders"
                            :key="order.id"
                            class="hover:bg-navy-800/30 transition-colors"
                        >
                            <td class="px-5 py-3.5 font-medium text-white">
                                #{{ order.order_number }}
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="text-white text-xs font-medium">{{ order.user?.name ?? '—' }}</div>
                                <div class="text-navy-500 text-xs">{{ order.user?.email }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="getStatusConfig(order.status).class"
                                >
                                    {{ getStatusConfig(order.status).label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right text-accent-400 font-semibold">
                                {{ formatCurrency(order.total_cents) }}
                            </td>
                            <td class="px-5 py-3.5 text-right text-navy-400 text-xs">
                                {{ formatDate(order.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </VendorLayout>
</template>
