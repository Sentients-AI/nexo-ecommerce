<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';

interface PromotionRow {
    id: number;
    name: string;
    code: string | null;
    discount_type: string;
    discount_value: number;
    scope: string;
    auto_apply: boolean;
    is_active: boolean;
    usage_count: number;
    usage_limit: number | null;
    minimum_order_cents: number | null;
    maximum_discount_cents: number | null;
    starts_at: string | null;
    ends_at: string | null;
    formatted_discount: string;
    is_valid: boolean;
}

interface Props {
    promotions: PromotionRow[];
    active_count: number;
    expired_count: number;
}

const props = defineProps<Props>();

function formatDate(iso: string | null): string {
    if (!iso) { return '—'; }
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatCurrency(cents: number | null): string {
    if (!cents) { return '—'; }
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100);
}

function statusClass(promo: PromotionRow): string {
    if (!promo.is_active) { return 'bg-navy-700/50 text-navy-400 border border-navy-600/30'; }
    if (!promo.is_valid) { return 'bg-orange-500/15 text-orange-400 border border-orange-500/20'; }
    return 'bg-accent-500/15 text-accent-400 border border-accent-500/20';
}

function statusLabel(promo: PromotionRow): string {
    if (!promo.is_active) { return 'Disabled'; }
    if (!promo.is_valid) { return 'Expired'; }
    return 'Active';
}
</script>

<template>
    <Head title="Promotions" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Promotions</span>
            </div>
        </template>

        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Promotions</h1>
                <p class="mt-1 text-sm text-navy-400">
                    <span class="text-accent-400 font-medium">{{ active_count }}</span> active ·
                    <span class="text-orange-400 font-medium">{{ expired_count }}</span> expired
                </p>
            </div>
            <a
                href="/admin/promotions/create"
                target="_blank"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-400 transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Create Promo
            </a>
        </div>

        <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 overflow-hidden">
            <div v-if="promotions.length === 0" class="flex flex-col items-center justify-center py-20">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-navy-800 mb-4">
                    <svg class="h-6 w-6 text-navy-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                    </svg>
                </div>
                <p class="text-sm text-navy-400">No promotions yet</p>
                <a href="/admin/promotions/create" target="_blank" class="mt-4 text-sm text-brand-400 hover:text-brand-300 transition-colors">
                    Create your first promotion
                </a>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-navy-800/40">
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">Promotion</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">Code</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">Discount</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-navy-500">Usage</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">Validity</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-navy-500">Status</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-navy-500">Edit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-navy-800/30">
                        <tr
                            v-for="promo in promotions"
                            :key="promo.id"
                            class="hover:bg-navy-800/30 transition-colors"
                        >
                            <td class="px-5 py-3.5">
                                <div class="font-medium text-white">{{ promo.name }}</div>
                                <div class="text-xs text-navy-500 capitalize">{{ promo.scope }} · {{ promo.auto_apply ? 'Auto-apply' : 'Manual' }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span v-if="promo.code" class="font-mono text-xs bg-navy-700/50 border border-navy-600/30 rounded px-2 py-0.5 text-navy-200">
                                    {{ promo.code }}
                                </span>
                                <span v-else class="text-navy-600 text-xs">—</span>
                            </td>
                            <td class="px-5 py-3.5 text-accent-400 font-medium text-xs">{{ promo.formatted_discount }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="text-white text-xs">{{ promo.usage_count }}</span>
                                <span class="text-navy-500 text-xs">{{ promo.usage_limit ? ` / ${promo.usage_limit}` : '' }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="text-xs text-navy-400">
                                    <span v-if="promo.starts_at">From {{ formatDate(promo.starts_at) }}</span>
                                    <span v-if="promo.ends_at"> until {{ formatDate(promo.ends_at) }}</span>
                                    <span v-if="!promo.starts_at && !promo.ends_at" class="text-navy-600">No expiry</span>
                                </div>
                                <div v-if="promo.minimum_order_cents" class="text-xs text-navy-500 mt-0.5">
                                    Min. {{ formatCurrency(promo.minimum_order_cents) }}
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass(promo)">
                                    {{ statusLabel(promo) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a
                                    :href="`/admin/promotions/${promo.id}/edit`"
                                    target="_blank"
                                    class="text-xs text-brand-400 hover:text-brand-300 transition-colors font-medium"
                                >
                                    Edit
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </VendorLayout>
</template>
