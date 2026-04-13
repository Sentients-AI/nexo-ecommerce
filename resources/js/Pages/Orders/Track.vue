<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface TrackingResult {
    found: boolean;
    order_number?: string;
    status?: string;
    carrier?: string | null;
    tracking_number?: string | null;
    tracking_url?: string | null;
    shipped_at?: string | null;
    items?: { name: string; quantity: number }[];
}

interface Props {
    result: TrackingResult | null;
}

const props = defineProps<Props>();

const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user !== null);
const Layout = computed(() => isAuthenticated.value ? AuthenticatedLayout : GuestLayout);

const form = useForm({
    order_number: '',
    email: '',
});

function submit() {
    form.post(page.url.split('?')[0]);
}

const statusLabels: Record<string, { label: string; color: string }> = {
    pending: { label: 'Pending', color: 'text-amber-500' },
    awaiting_payment: { label: 'Awaiting Payment', color: 'text-amber-500' },
    paid: { label: 'Paid', color: 'text-blue-500' },
    packed: { label: 'Packed', color: 'text-blue-500' },
    shipped: { label: 'Shipped', color: 'text-brand-400' },
    delivered: { label: 'Delivered', color: 'text-accent-400' },
    fulfilled: { label: 'Fulfilled', color: 'text-accent-400' },
    cancelled: { label: 'Cancelled', color: 'text-red-400' },
    refunded: { label: 'Refunded', color: 'text-slate-400' },
    partially_refunded: { label: 'Partially Refunded', color: 'text-slate-400' },
    failed: { label: 'Failed', color: 'text-red-500' },
};

function statusInfo(status: string) {
    return statusLabels[status] ?? { label: status, color: 'text-slate-400' };
}
</script>

<template>
    <Head title="Track Your Order" />

    <component :is="Layout">
        <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="text-center mb-10">
                <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Track Your Order</h1>
                <p class="mt-2 text-slate-600 dark:text-slate-400">Enter your order number and email address to see your shipment status</p>
            </div>

            <!-- Lookup form -->
            <div class="rounded-2xl border border-slate-200 dark:border-navy-800/60 bg-white dark:bg-navy-900/60 p-8 mb-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-navy-300 mb-1.5">Order Number</label>
                        <input
                            v-model="form.order_number"
                            type="text"
                            placeholder="e.g. ORD-ABC12345"
                            class="block w-full rounded-xl border border-slate-200 dark:border-navy-700 bg-white dark:bg-navy-800 px-4 py-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-navy-500 focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                        />
                        <p v-if="form.errors.order_number" class="mt-1 text-xs text-red-500">{{ form.errors.order_number }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-navy-300 mb-1.5">Email Address</label>
                        <input
                            v-model="form.email"
                            type="email"
                            placeholder="you@example.com"
                            class="block w-full rounded-xl border border-slate-200 dark:border-navy-700 bg-white dark:bg-navy-800 px-4 py-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-navy-500 focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-semibold py-2.5 text-sm transition-colors disabled:opacity-50"
                    >
                        {{ form.processing ? 'Looking up…' : 'Track Order' }}
                    </button>
                </form>
            </div>

            <!-- Result: not found -->
            <div v-if="result && !result.found" class="rounded-2xl border border-red-200 dark:border-red-900/40 bg-red-50 dark:bg-red-900/20 p-6 text-center">
                <svg class="mx-auto h-10 w-10 text-red-400 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <p class="text-sm font-semibold text-red-700 dark:text-red-300">Order not found</p>
                <p class="text-xs text-red-600 dark:text-red-400 mt-1">Please check your order number and email address and try again.</p>
            </div>

            <!-- Result: found -->
            <div v-if="result && result.found" class="rounded-2xl border border-slate-200 dark:border-navy-800/60 bg-white dark:bg-navy-900/60 overflow-hidden">
                <!-- Status header -->
                <div class="p-6 border-b border-slate-100 dark:border-navy-800/60">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-slate-500 dark:text-navy-500 font-medium uppercase tracking-wide">Order</p>
                            <p class="text-lg font-bold text-slate-900 dark:text-white font-mono">{{ result.order_number }}</p>
                        </div>
                        <span :class="['text-sm font-semibold', statusInfo(result.status!).color]">
                            {{ statusInfo(result.status!).label }}
                        </span>
                    </div>
                </div>

                <!-- Shipping info -->
                <div v-if="result.tracking_number" class="p-6 border-b border-slate-100 dark:border-navy-800/60">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-navy-500 mb-3">Shipment Details</h3>
                    <div class="space-y-2">
                        <div v-if="result.carrier" class="flex justify-between text-sm">
                            <span class="text-slate-600 dark:text-navy-400">Carrier</span>
                            <span class="font-medium text-slate-900 dark:text-white capitalize">{{ result.carrier }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600 dark:text-navy-400">Tracking Number</span>
                            <span class="font-mono text-slate-900 dark:text-white">{{ result.tracking_number }}</span>
                        </div>
                        <div v-if="result.shipped_at" class="flex justify-between text-sm">
                            <span class="text-slate-600 dark:text-navy-400">Shipped</span>
                            <span class="text-slate-900 dark:text-white">{{ result.shipped_at }}</span>
                        </div>
                    </div>
                    <a
                        v-if="result.tracking_url"
                        :href="result.tracking_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold px-4 py-2 transition-colors"
                    >
                        Track on carrier website
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </a>
                </div>

                <!-- No tracking yet -->
                <div v-else class="p-6 border-b border-slate-100 dark:border-navy-800/60">
                    <p class="text-sm text-slate-500 dark:text-navy-500">No tracking information available yet. Please check back once your order has shipped.</p>
                </div>

                <!-- Items -->
                <div v-if="result.items && result.items.length > 0" class="p-6">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-navy-500 mb-3">Items in Order</h3>
                    <ul class="space-y-1.5">
                        <li v-for="(item, i) in result.items" :key="i" class="flex items-center justify-between text-sm">
                            <span class="text-slate-700 dark:text-navy-300">{{ item.name }}</span>
                            <span class="text-slate-500 dark:text-navy-500">× {{ item.quantity }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </component>
</template>
