<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
    is_flash_sale: boolean;
    buy_quantity: number | null;
    get_quantity: number | null;
    tiers: Array<{ min_cents: number; discount_bps: number }> | null;
    time_remaining_seconds: number;
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

const togglingId = ref<number | null>(null);

function formatDate(iso: string | null): string {
    if (!iso) { return '—'; }
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatCurrency(cents: number | null): string {
    if (!cents) { return '—'; }
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100);
}

function formatCountdown(seconds: number): string {
    if (seconds <= 0) { return 'Ended'; }
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    if (hours > 24) {
        const days = Math.floor(hours / 24);
        return `${days}d ${hours % 24}h`;
    }
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

function discountTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        fixed: 'Fixed',
        percentage: '%',
        bogo: 'BOGO',
        tiered: 'Tiered',
    };
    return labels[type] ?? type;
}

function discountTypeBadgeClass(type: string): string {
    const classes: Record<string, string> = {
        fixed: 'bg-sky-500/15 text-sky-400 border border-sky-500/20',
        percentage: 'bg-violet-500/15 text-violet-400 border border-violet-500/20',
        bogo: 'bg-pink-500/15 text-pink-400 border border-pink-500/20',
        tiered: 'bg-amber-500/15 text-amber-400 border border-amber-500/20',
    };
    return classes[type] ?? 'bg-navy-700/50 text-navy-400 border border-navy-600/30';
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

function discountSummary(promo: PromotionRow): string {
    if (promo.discount_type === 'bogo' && promo.buy_quantity && promo.get_quantity) {
        return `Buy ${promo.buy_quantity} Get ${promo.get_quantity} Free`;
    }
    if (promo.discount_type === 'tiered' && promo.tiers?.length) {
        const tiers = [...promo.tiers].sort((a, b) => b.min_cents - a.min_cents);
        const top = tiers[0];
        return `Up to ${(top.discount_bps / 100).toFixed(0)}% off`;
    }
    return promo.formatted_discount;
}

function toggleActive(promo: PromotionRow): void {
    togglingId.value = promo.id;
    router.patch(`/vendor/promotions/${promo.id}/toggle`, {}, {
        preserveScroll: true,
        onFinish: () => { togglingId.value = null; },
    });
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
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-navy-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-navy-800/30">
                        <tr
                            v-for="promo in promotions"
                            :key="promo.id"
                            class="hover:bg-navy-800/30 transition-colors"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-white">{{ promo.name }}</span>
                                    <!-- Flash sale badge -->
                                    <span
                                        v-if="promo.is_flash_sale && promo.is_valid"
                                        class="inline-flex items-center gap-1 rounded-full bg-red-500/15 border border-red-500/20 px-2 py-0.5 text-xs font-semibold text-red-400"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full bg-red-400 animate-pulse"></span>
                                        {{ formatCountdown(promo.time_remaining_seconds) }}
                                    </span>
                                </div>
                                <div class="text-xs text-navy-500 capitalize mt-0.5">{{ promo.scope }} · {{ promo.auto_apply ? 'Auto-apply' : 'Manual' }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span v-if="promo.code" class="font-mono text-xs bg-navy-700/50 border border-navy-600/30 rounded px-2 py-0.5 text-navy-200">
                                    {{ promo.code }}
                                </span>
                                <span v-else class="text-navy-600 text-xs">—</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="discountTypeBadgeClass(promo.discount_type)"
                                    >
                                        {{ discountTypeLabel(promo.discount_type) }}
                                    </span>
                                    <span class="text-accent-400 font-medium text-xs">{{ discountSummary(promo) }}</span>
                                </div>
                            </td>
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
                                <div class="flex items-center justify-end gap-3">
                                    <!-- Toggle active/inactive -->
                                    <button
                                        type="button"
                                        :disabled="togglingId === promo.id"
                                        class="text-xs font-medium transition-colors disabled:opacity-50"
                                        :class="promo.is_active ? 'text-orange-400 hover:text-orange-300' : 'text-accent-400 hover:text-accent-300'"
                                        @click="toggleActive(promo)"
                                    >
                                        {{ promo.is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                    <a
                                        :href="`/admin/promotions/${promo.id}/edit`"
                                        target="_blank"
                                        class="text-xs text-brand-400 hover:text-brand-300 transition-colors font-medium"
                                    >
                                        Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </VendorLayout>
</template>
