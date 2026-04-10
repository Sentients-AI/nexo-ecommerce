<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';
import Breadcrumb from '@/Components/UI/Breadcrumb.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { useCurrency } from '@/Composables/useCurrency';

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
        revenue_yesterday: number;
        revenue_this_month: number;
        revenue_last_month: number;
        orders_today: number;
        orders_yesterday: number;
        pending_orders: number;
        total_products: number;
    };
    recent_orders: OrderRow[];
    low_stock_products: LowStockProduct[];
    chart_data: ChartPoint[];
}

const props = defineProps<Props>();
const { formatPrice: formatCurrency } = useCurrency();

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

const maxRevenue = computed(() =>
    Math.max(...props.chart_data.map(d => d.revenue), 1),
);

// Build SVG area path for revenue chart (200×80 viewport)
const chartPath = computed(() => {
    const data = props.chart_data;
    if (!data.length) { return ''; }
    const W = 200;
    const H = 80;
    const max = maxRevenue.value;
    const pts = data.map((d, i) => {
        const x = (i / (data.length - 1)) * W;
        const y = H - Math.max((d.revenue / max) * (H - 8), 2);
        return `${x},${y}`;
    });
    const line = `M${pts.join('L')}`;
    const area = `${line}L${(data.length - 1) / (data.length - 1) * W},${H}L0,${H}Z`;
    return { line, area };
});

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

function pctChange(current: number, previous: number): string {
    if (previous === 0) { return current > 0 ? '+100%' : '—'; }
    const pct = ((current - previous) / previous) * 100;
    return (pct >= 0 ? '+' : '') + pct.toFixed(1) + '%';
}

function trendUp(current: number, previous: number): boolean | null {
    if (previous === 0 && current === 0) { return null; }
    return current >= previous;
}

const kpis = computed(() => [
    {
        label: 'Revenue Today',
        value: formatCurrency(props.stats.revenue_today),
        icon: 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        color: 'text-brand-400',
        iconBg: 'bg-brand-500/10 border-brand-500/20',
        trend: pctChange(props.stats.revenue_today, props.stats.revenue_yesterday),
        trendLabel: 'vs yesterday',
        isUp: trendUp(props.stats.revenue_today, props.stats.revenue_yesterday),
    },
    {
        label: 'This Month',
        value: formatCurrency(props.stats.revenue_this_month),
        icon: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
        color: 'text-accent-400',
        iconBg: 'bg-accent-500/10 border-accent-500/20',
        trend: pctChange(props.stats.revenue_this_month, props.stats.revenue_last_month),
        trendLabel: 'vs last month',
        isUp: trendUp(props.stats.revenue_this_month, props.stats.revenue_last_month),
    },
    {
        label: 'Orders Today',
        value: String(props.stats.orders_today),
        icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z',
        color: 'text-blue-400',
        iconBg: 'bg-blue-500/10 border-blue-500/20',
        trend: pctChange(props.stats.orders_today, props.stats.orders_yesterday),
        trendLabel: 'vs yesterday',
        isUp: trendUp(props.stats.orders_today, props.stats.orders_yesterday),
    },
    {
        label: 'Active Products',
        value: String(props.stats.total_products),
        icon: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
        color: 'text-amber-400',
        iconBg: 'bg-amber-500/10 border-amber-500/20',
        trend: props.low_stock_products.length > 0 ? `${props.low_stock_products.length} low stock` : 'All stocked',
        trendLabel: '',
        isUp: props.low_stock_products.length === 0,
    },
]);

const quickActions = [
    { label: 'Add Product',      href: '/vendor/products/create',   icon: 'M12 4.5v15m7.5-7.5h-15', color: 'bg-brand-500/10 border-brand-500/20 text-brand-400 hover:bg-brand-500/20' },
    { label: 'Create Promo',     href: '/vendor/promotions/create', icon: 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3zM6 6h.008v.008H6V6z', color: 'bg-amber-500/10 border-amber-500/20 text-amber-400 hover:bg-amber-500/20' },
    { label: 'View Orders',      href: '/vendor/orders',            icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z', color: 'bg-accent-500/10 border-accent-500/20 text-accent-400 hover:bg-accent-500/20' },
    { label: 'Import Products',  href: '/vendor/products/import',   icon: 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5', color: 'bg-navy-700/40 border-navy-700 text-navy-400 hover:bg-navy-700/70' },
];
</script>

<template>
    <Head title="Vendor Dashboard" />

    <VendorLayout>
        <template #header>
            <Breadcrumb
                :items="[{ label: 'Command Center', href: '/vendor/dashboard' }, { label: 'Dashboard' }]"
                theme="dark"
            />
        </template>

        <!-- Page heading -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Command Center</h1>
                <p class="mt-0.5 text-sm text-navy-400">Real-time overview of your store performance</p>
            </div>
            <!-- Pending orders alert -->
            <div
                v-if="stats.pending_orders > 0"
                class="hidden sm:flex items-center gap-2 rounded-xl bg-amber-500/10 border border-amber-500/20 px-4 py-2"
            >
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75" />
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500" />
                </span>
                <span class="text-sm font-semibold text-amber-300">{{ stats.pending_orders }} pending orders</span>
                <Link href="/vendor/orders?status=pending" class="text-xs text-amber-400 hover:text-amber-300 underline underline-offset-2">
                    Review →
                </Link>
            </div>
        </div>

        <!-- ── KPI CARDS ── -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 mb-6">
            <div
                v-for="kpi in kpis"
                :key="kpi.label"
                class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5 hover:border-navy-700/80 transition-colors"
            >
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border"
                        :class="kpi.iconBg"
                    >
                        <svg class="h-4.5 w-4.5" :class="kpi.color" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="kpi.icon" />
                        </svg>
                    </div>
                    <span
                        class="text-[11px] font-medium rounded-full px-2 py-0.5 leading-5"
                        :class="kpi.isUp === true
                            ? 'bg-accent-500/10 text-accent-400'
                            : kpi.isUp === false
                                ? 'bg-red-500/10 text-red-400'
                                : 'bg-navy-700/50 text-navy-400'"
                    >
                        {{ kpi.trend }}
                    </span>
                </div>
                <div class="text-2xl font-bold text-white tabular-nums">{{ kpi.value }}</div>
                <div class="mt-0.5 flex items-center gap-1 text-xs text-navy-500">
                    <span>{{ kpi.label }}</span>
                    <span v-if="kpi.trendLabel" class="text-navy-700">·</span>
                    <span v-if="kpi.trendLabel">{{ kpi.trendLabel }}</span>
                </div>
            </div>
        </div>

        <!-- ── QUICK ACTIONS ── -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <Link
                v-for="action in quickActions"
                :key="action.label"
                :href="action.href"
                class="flex items-center gap-2.5 rounded-xl border px-4 py-3 text-sm font-medium transition-all"
                :class="action.color"
            >
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="action.icon" />
                </svg>
                {{ action.label }}
            </Link>
        </div>

        <!-- ── CHART + ALERTS ── -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 mb-6">
            <!-- Revenue area chart -->
            <div class="bento lg:col-span-2 rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-sm font-semibold text-white">Revenue Trend</h2>
                        <p class="text-xs text-navy-500 mt-0.5">Last 7 days</p>
                    </div>
                    <div class="text-lg font-bold text-white">
                        {{ formatCurrency(chart_data.reduce((s, d) => s + d.revenue, 0)) }}
                    </div>
                </div>

                <!-- SVG area chart -->
                <div class="relative">
                    <svg
                        viewBox="0 0 200 80"
                        class="w-full h-28"
                        preserveAspectRatio="none"
                    >
                        <!-- Area fill -->
                        <defs>
                            <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="oklch(0.58 0.24 284)" stop-opacity="0.3" />
                                <stop offset="100%" stop-color="oklch(0.58 0.24 284)" stop-opacity="0.02" />
                            </linearGradient>
                        </defs>
                        <path
                            v-if="chartPath.area"
                            :d="chartPath.area"
                            fill="url(#areaGrad)"
                        />
                        <path
                            v-if="chartPath.line"
                            :d="chartPath.line"
                            fill="none"
                            stroke="oklch(0.58 0.24 284)"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <!-- Data points -->
                        <circle
                            v-for="(d, i) in chart_data"
                            :key="i"
                            :cx="(i / (chart_data.length - 1)) * 200"
                            :cy="80 - Math.max((d.revenue / maxRevenue) * 72, 2)"
                            r="2.5"
                            fill="oklch(0.58 0.24 284)"
                            class="opacity-0 hover:opacity-100 transition-opacity"
                        />
                    </svg>

                    <!-- X-axis labels -->
                    <div class="flex justify-between mt-1">
                        <span
                            v-for="(d, i) in chart_data"
                            :key="i"
                            class="text-[10px] text-navy-600"
                        >
                            {{ d.date }}
                        </span>
                    </div>
                </div>

                <!-- Bar chart (supplemental) -->
                <div class="flex items-end gap-1.5 h-16 mt-4 pt-4 border-t border-navy-800/40">
                    <div
                        v-for="(point, i) in chart_data"
                        :key="i"
                        class="group flex-1 flex flex-col items-center gap-1"
                    >
                        <div class="relative w-full">
                            <div
                                class="w-full rounded-t-md transition-all duration-500"
                                :class="i === chart_data.length - 1 ? 'bg-brand-500' : 'bg-navy-700 group-hover:bg-brand-500/60'"
                                :style="`height: ${Math.max((point.revenue / maxRevenue) * 48, 3)}px`"
                            />
                            <div class="absolute -top-7 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap bg-navy-800 border border-navy-700 rounded-lg px-2 py-1 text-[10px] text-white z-10">
                                {{ formatCurrency(point.revenue) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Low stock alerts -->
            <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-white">Stock Alerts</h2>
                    <Link
                        href="/vendor/inventory"
                        class="text-xs text-brand-400 hover:text-brand-300 transition-colors"
                    >
                        Manage
                    </Link>
                </div>

                <EmptyState
                    v-if="low_stock_products.length === 0"
                    title="All products stocked"
                    description="No low stock alerts right now"
                    theme="dark"
                >
                    <template #icon>
                        <svg class="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                </EmptyState>

                <div v-else class="space-y-2">
                    <Link
                        v-for="product in low_stock_products"
                        :key="product.id"
                        :href="`/vendor/inventory?search=${product.slug}`"
                        class="flex items-center gap-3 rounded-xl bg-navy-800/50 px-3 py-2.5 hover:bg-navy-800 transition-colors"
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
                        <svg class="h-3.5 w-3.5 text-navy-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </Link>
                </div>
            </div>
        </div>

        <!-- ── RECENT ORDERS ── -->
        <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-navy-800/60">
                <div>
                    <h2 class="text-sm font-semibold text-white">Recent Orders</h2>
                    <p class="text-xs text-navy-500 mt-0.5">Latest customer orders across your store</p>
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

            <EmptyState
                v-if="recent_orders.length === 0"
                title="No orders yet"
                description="Orders will appear here once customers start purchasing"
                theme="dark"
            >
                <template #icon>
                    <svg class="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                    </svg>
                </template>
                <template #action>
                    <Link
                        href="/vendor/products"
                        class="rounded-xl bg-brand-500/10 border border-brand-500/20 px-4 py-2 text-sm font-medium text-brand-300 hover:bg-brand-500/20 transition-colors"
                    >
                        Manage Products
                    </Link>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-navy-800/40">
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-navy-500">Order</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-navy-500">Customer</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-navy-500">Status</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-navy-500">Total</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-navy-500">Date</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-navy-500" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-navy-800/30">
                        <tr
                            v-for="order in recent_orders"
                            :key="order.id"
                            class="hover:bg-navy-800/30 transition-colors group"
                        >
                            <td class="px-5 py-3.5 font-mono text-xs font-semibold text-white">
                                #{{ order.order_number }}
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-navy-700 text-navy-300 text-[10px] font-bold">
                                        {{ order.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-white">{{ order.user?.name ?? 'Guest' }}</div>
                                        <div class="text-[11px] text-navy-500">{{ order.user?.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="getStatusConfig(order.status).class"
                                >
                                    {{ getStatusConfig(order.status).label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right text-accent-400 font-semibold tabular-nums">
                                {{ formatCurrency(order.total_cents) }}
                            </td>
                            <td class="px-5 py-3.5 text-right text-navy-500 text-xs whitespace-nowrap">
                                {{ formatDate(order.created_at) }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <Link
                                    :href="`/vendor/orders/${order.id}`"
                                    class="opacity-0 group-hover:opacity-100 rounded-lg px-2.5 py-1 text-xs font-medium text-navy-400 hover:text-white hover:bg-navy-700 transition-all"
                                >
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </VendorLayout>
</template>
