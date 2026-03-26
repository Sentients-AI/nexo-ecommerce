<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';

interface TenantData {
    id: number;
    name: string;
    slug: string;
    domain: string | null;
    email: string | null;
    description: string | null;
    is_active: boolean;
    trial_ends_at: string | null;
    subscribed_at: string | null;
    settings: Record<string, unknown>;
}

interface Props {
    tenant: TenantData | null;
    usage: {
        products: number;
        customers: number;
        orders: number;
    };
}

const props = defineProps<Props>();

function formatDate(iso: string | null): string {
    if (!iso) { return '—'; }
    return new Date(iso).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

const usageItems = [
    { key: 'products', label: 'Products', icon: 'products' },
    { key: 'customers', label: 'Customers', icon: 'customers' },
    { key: 'orders', label: 'Orders', icon: 'orders' },
] as const;
</script>

<template>
    <Head title="Settings" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Settings</span>
            </div>
        </template>

        <div class="mb-6">
            <h1 class="text-xl font-bold text-white">Store Settings</h1>
            <p class="mt-1 text-sm text-navy-400">Your store configuration and usage overview</p>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Store info -->
            <div class="lg:col-span-2 space-y-4">
                <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500/15 border border-brand-500/20">
                            <svg class="h-5 w-5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-white">Store Information</h2>
                            <p class="text-xs text-navy-400">Contact your administrator to update these settings</p>
                        </div>
                    </div>

                    <div v-if="!tenant" class="text-sm text-navy-500 italic py-4 text-center">
                        No tenant context available
                    </div>

                    <dl v-else class="space-y-4">
                        <div class="flex items-start gap-4 rounded-xl bg-navy-800/30 px-4 py-3">
                            <dt class="w-28 shrink-0 text-xs font-medium text-navy-500 pt-0.5">Store Name</dt>
                            <dd class="text-sm text-white font-medium">{{ tenant.name }}</dd>
                        </div>
                        <div class="flex items-start gap-4 rounded-xl bg-navy-800/30 px-4 py-3">
                            <dt class="w-28 shrink-0 text-xs font-medium text-navy-500 pt-0.5">Slug</dt>
                            <dd class="text-sm font-mono text-navy-200">{{ tenant.slug }}</dd>
                        </div>
                        <div class="flex items-start gap-4 rounded-xl bg-navy-800/30 px-4 py-3">
                            <dt class="w-28 shrink-0 text-xs font-medium text-navy-500 pt-0.5">Domain</dt>
                            <dd class="text-sm text-navy-200">{{ tenant.domain ?? '—' }}</dd>
                        </div>
                        <div class="flex items-start gap-4 rounded-xl bg-navy-800/30 px-4 py-3">
                            <dt class="w-28 shrink-0 text-xs font-medium text-navy-500 pt-0.5">Email</dt>
                            <dd class="text-sm text-navy-200">{{ tenant.email ?? '—' }}</dd>
                        </div>
                        <div class="flex items-start gap-4 rounded-xl bg-navy-800/30 px-4 py-3">
                            <dt class="w-28 shrink-0 text-xs font-medium text-navy-500 pt-0.5">Description</dt>
                            <dd class="text-sm text-navy-300">{{ tenant.description ?? '—' }}</dd>
                        </div>
                        <div class="flex items-start gap-4 rounded-xl bg-navy-800/30 px-4 py-3">
                            <dt class="w-28 shrink-0 text-xs font-medium text-navy-500 pt-0.5">Status</dt>
                            <dd>
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="tenant.is_active
                                        ? 'bg-accent-500/15 text-accent-400 border border-accent-500/20'
                                        : 'bg-red-500/15 text-red-400 border border-red-500/20'"
                                >
                                    {{ tenant.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Subscription info -->
                <div v-if="tenant" class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-6">
                    <h2 class="text-sm font-semibold text-white mb-4">Subscription</h2>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-navy-800/30 px-4 py-3">
                            <div class="text-xs text-navy-500 mb-1">Trial ends</div>
                            <div class="text-sm text-white">{{ formatDate(tenant.trial_ends_at) }}</div>
                        </div>
                        <div class="rounded-xl bg-navy-800/30 px-4 py-3">
                            <div class="text-xs text-navy-500 mb-1">Subscribed at</div>
                            <div class="text-sm text-white">{{ formatDate(tenant.subscribed_at) }}</div>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-navy-500">
                        To update subscription or billing, contact your account manager.
                    </p>
                </div>
            </div>

            <!-- Usage stats -->
            <div class="space-y-4">
                <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-6">
                    <h2 class="text-sm font-semibold text-white mb-4">Usage Overview</h2>
                    <div class="space-y-3">
                        <div
                            v-for="item in usageItems"
                            :key="item.key"
                            class="flex items-center justify-between rounded-xl bg-navy-800/30 px-4 py-3"
                        >
                            <span class="text-sm text-navy-400">{{ item.label }}</span>
                            <span class="text-lg font-bold text-white">{{ usage[item.key].toLocaleString() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Quick links -->
                <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-6">
                    <h2 class="text-sm font-semibold text-white mb-4">Admin Links</h2>
                    <div class="space-y-2">
                        <a
                            v-for="[label, href] in [
                                ['Manage Products', '/admin/products'],
                                ['Manage Promotions', '/admin/promotions'],
                                ['View All Orders', '/admin/orders'],
                                ['Variant Attributes', '/admin/variant-attribute-types'],
                            ]"
                            :key="label"
                            :href="href"
                            target="_blank"
                            class="flex items-center justify-between rounded-xl px-4 py-2.5 text-sm text-navy-300 hover:text-white hover:bg-navy-800/60 transition-all"
                        >
                            {{ label }}
                            <svg class="h-3.5 w-3.5 text-navy-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </VendorLayout>
</template>
