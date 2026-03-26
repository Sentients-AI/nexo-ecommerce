<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';

interface DailyPoint {
    date: string;
    revenue: number;
    orders: number;
}

interface MonthlyPoint {
    month: string;
    revenue: number;
    orders: number;
}

interface TopProduct {
    product_id: number;
    product_name: string;
    product_sku: string;
    revenue_cents: number;
    units_sold: number;
}

interface Props {
    daily_revenue: DailyPoint[];
    monthly_revenue: MonthlyPoint[];
    top_products: TopProduct[];
    status_distribution: Record<string, number>;
    stats: {
        total_revenue_cents: number;
        total_orders: number;
        avg_order_value_cents: number;
        revenue_this_month_cents: number;
        month_growth_percent: number;
    };
}

const props = defineProps<Props>();

function formatCurrency(cents: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(cents / 100);
}

const maxDailyRevenue = computed(() =>
    Math.max(...props.daily_revenue.map(d => d.revenue), 1)
);

const maxMonthlyRevenue = computed(() =>
    Math.max(...props.monthly_revenue.map(d => d.revenue), 1)
);

const maxTopProductRevenue = computed(() =>
    Math.max(...props.top_products.map(p => p.revenue_cents), 1)
);

const totalStatusOrders = computed(() =>
    Object.values(props.status_distribution).reduce((a, b) => a + b, 0)
);

const statusColors: Record<string, string> = {
    pending: 'bg-amber-400',
    paid: 'bg-accent-400',
    packed: 'bg-indigo-400',
    shipped: 'bg-blue-400',
    delivered: 'bg-accent-400',
    fulfilled: 'bg-accent-400',
    cancelled: 'bg-red-400',
    refunded: 'bg-slate-400',
    failed: 'bg-red-400',
    awaiting_payment: 'bg-yellow-400',
    partially_refunded: 'bg-orange-400',
};

const kpis = computed(() => [
    {
        label: 'Total Revenue',
        value: formatCurrency(props.stats.total_revenue_cents),
        icon: 'revenue',
        color: 'text-brand-400',
        bg: 'bg-brand-500/10',
        border: 'border-brand-500/20',
    },
    {
        label: 'Total Orders',
        value: props.stats.total_orders.toLocaleString(),
        icon: 'orders',
        color: 'text-blue-400',
        bg: 'bg-blue-500/10',
        border: 'border-blue-500/20',
    },
    {
        label: 'Avg. Order Value',
        value: formatCurrency(props.stats.avg_order_value_cents),
        icon: 'avg',
        color: 'text-amber-400',
        bg: 'bg-amber-500/10',
        border: 'border-amber-500/20',
    },
    {
        label: 'This Month',
        value: formatCurrency(props.stats.revenue_this_month_cents),
        trend: props.stats.month_growth_percent,
        icon: 'month',
        color: 'text-accent-400',
        bg: 'bg-accent-500/10',
        border: 'border-accent-500/20',
    },
]);
</script>

<template>
    <Head title="Analytics" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Analytics</span>
            </div>
        </template>

        <div class="mb-6">
            <h1 class="text-xl font-bold text-white">Analytics</h1>
            <p class="mt-1 text-sm text-navy-400">Store performance overview</p>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 mb-6">
            <div
                v-for="kpi in kpis"
                :key="kpi.label"
                class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5"
            >
                <div :class="['flex h-10 w-10 items-center justify-center rounded-xl border mb-3', kpi.bg, kpi.border]">
                    <svg v-if="kpi.icon === 'revenue'" :class="['h-5 w-5', kpi.color]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg v-else-if="kpi.icon === 'orders'" :class="['h-5 w-5', kpi.color]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                    </svg>
                    <svg v-else :class="['h-5 w-5', kpi.color]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                </div>
                <div class="text-2xl font-bold text-white">{{ kpi.value }}</div>
                <div class="mt-0.5 text-xs text-navy-400">{{ kpi.label }}</div>
                <div
                    v-if="kpi.trend !== undefined"
                    class="mt-1 text-xs font-medium"
                    :class="kpi.trend >= 0 ? 'text-accent-400' : 'text-red-400'"
                >
                    {{ kpi.trend >= 0 ? '+' : '' }}{{ kpi.trend }}% vs last month
                </div>
            </div>
        </div>

        <!-- Daily revenue chart (30 days) -->
        <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5 mb-4">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-sm font-semibold text-white">Daily Revenue (30 days)</h2>
                    <p class="text-xs text-navy-400 mt-0.5">Revenue per day excluding cancelled/failed orders</p>
                </div>
            </div>
            <div class="flex items-end gap-0.5 h-40">
                <div
                    v-for="(point, i) in daily_revenue"
                    :key="i"
                    class="group flex-1 flex flex-col items-center gap-1"
                >
                    <div
                        class="w-full rounded-t-sm transition-all duration-300 relative"
                        :class="i === daily_revenue.length - 1 ? 'bg-brand-500' : 'bg-navy-700 group-hover:bg-brand-500/60'"
                        :style="`height: ${Math.max((point.revenue / maxDailyRevenue) * 100, 2)}%`"
                    >
                        <div class="absolute -top-9 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap bg-navy-800 border border-navy-700 rounded-lg px-2 py-1 text-xs text-white z-10">
                            ${{ point.revenue.toFixed(0) }}<br /><span class="text-navy-400">{{ point.orders }} orders</span>
                        </div>
                    </div>
                    <span v-if="i % 5 === 0" class="text-[9px] text-navy-600 rotate-45 origin-left">{{ point.date }}</span>
                </div>
            </div>
        </div>

        <!-- Bottom grid: top products + monthly + status -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Top products -->
            <div class="bento lg:col-span-2 rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5">
                <h2 class="text-sm font-semibold text-white mb-4">Top Products by Revenue</h2>
                <div v-if="top_products.length === 0" class="text-xs text-navy-500 italic py-4 text-center">No order data yet</div>
                <div v-else class="space-y-3">
                    <div
                        v-for="(product, index) in top_products"
                        :key="product.product_id"
                        class="flex items-center gap-3"
                    >
                        <span class="text-xs font-bold text-navy-600 w-5 shrink-0 text-center">{{ index + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="text-xs font-medium text-white truncate">{{ product.product_name }}</span>
                                <span class="text-xs font-semibold text-accent-400 shrink-0">{{ formatCurrency(product.revenue_cents) }}</span>
                            </div>
                            <div class="h-1.5 bg-navy-700/50 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-brand-500"
                                    :style="`width: ${(product.revenue_cents / maxTopProductRevenue) * 100}%`"
                                />
                            </div>
                            <div class="mt-1 text-xs text-navy-500">{{ product.units_sold }} units sold</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order status distribution -->
            <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5">
                <h2 class="text-sm font-semibold text-white mb-4">Order Status</h2>
                <div v-if="totalStatusOrders === 0" class="text-xs text-navy-500 italic py-4 text-center">No orders yet</div>
                <div v-else class="space-y-2.5">
                    <div
                        v-for="[status, count] in Object.entries(status_distribution)"
                        :key="status"
                        class="flex items-center gap-3"
                    >
                        <div :class="['h-2.5 w-2.5 rounded-full shrink-0', statusColors[status] ?? 'bg-navy-500']" />
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="text-xs text-navy-300 capitalize">{{ status.replace('_', ' ') }}</span>
                                <span class="text-xs font-semibold text-white">{{ count }}</span>
                            </div>
                            <div class="h-1 bg-navy-700/50 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full"
                                    :class="statusColors[status] ?? 'bg-navy-500'"
                                    :style="`width: ${(count / totalStatusOrders) * 100}%`"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </VendorLayout>
</template>
