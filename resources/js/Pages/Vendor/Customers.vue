<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';
import { useCurrency } from '@/Composables/useCurrency';

interface CustomerRow {
    id: number;
    name: string;
    email: string;
    orders_count: number;
    total_spent_cents: number;
    created_at: string;
}

interface PaginatedCustomers {
    data: CustomerRow[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    customers: PaginatedCustomers;
    search: string | null;
    stats: {
        total_customers: number;
        new_this_month: number;
    };
}

const props = defineProps<Props>();

const searchInput = ref(props.search ?? '');

const { formatPrice: formatCurrency } = useCurrency();

function applySearch(): void {
    router.get('/vendor/customers', searchInput.value ? { search: searchInput.value } : {}, { preserveState: true });
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function initials(name: string): string {
    return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
}
</script>

<template>
    <Head title="Customers" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Customers</span>
            </div>
        </template>

        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Customers</h1>
                <p class="mt-1 text-sm text-navy-400">
                    <span class="text-white font-medium">{{ stats.total_customers }}</span> customers ·
                    <span class="text-accent-400 font-medium">{{ stats.new_this_month }}</span> joined this month
                </p>
            </div>
        </div>

        <!-- Search -->
        <div class="mb-5 relative max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-navy-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input
                v-model="searchInput"
                @keyup.enter="applySearch"
                type="text"
                placeholder="Search customers…"
                class="w-full rounded-xl border border-navy-700/50 bg-navy-800/50 py-2.5 pl-9 pr-4 text-sm text-white placeholder-navy-500 focus:border-brand-500/50 focus:outline-none focus:ring-1 focus:ring-brand-500/30"
            />
        </div>

        <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 overflow-hidden">
            <div v-if="customers.data.length === 0" class="flex flex-col items-center justify-center py-20">
                <p class="text-sm text-navy-400">No customers found</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-navy-800/40">
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">Customer</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-navy-500">Orders</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-navy-500">Total Spent</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-navy-500">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-navy-800/30">
                        <tr
                            v-for="customer in customers.data"
                            :key="customer.id"
                            class="hover:bg-navy-800/30 transition-colors"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-500/20 text-brand-300 text-sm font-semibold">
                                        {{ initials(customer.name) }}
                                    </div>
                                    <div>
                                        <div class="font-medium text-white">{{ customer.name }}</div>
                                        <div class="text-xs text-navy-500">{{ customer.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-navy-700/50 text-xs font-semibold text-white">
                                    {{ customer.orders_count }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right text-accent-400 font-semibold">
                                {{ formatCurrency(customer.total_spent_cents) }}
                            </td>
                            <td class="px-5 py-3.5 text-right text-navy-400 text-xs">
                                {{ formatDate(customer.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="customers.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-navy-800/40">
                <p class="text-xs text-navy-500">Page {{ customers.current_page }} of {{ customers.last_page }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in customers.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        class="px-3 py-1 rounded-lg text-xs transition-colors"
                        :class="link.active
                            ? 'bg-brand-500/20 text-brand-300 border border-brand-500/30'
                            : link.url ? 'text-navy-400 hover:text-white hover:bg-navy-800' : 'text-navy-700 cursor-not-allowed'"
                    />
                </div>
            </div>
        </div>
    </VendorLayout>
</template>
