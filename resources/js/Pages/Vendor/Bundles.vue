<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import VendorLayout from '@/Layouts/VendorLayout.vue';
import { useCurrency } from '@/Composables/useCurrency';

interface BundleRow {
    id: number;
    name: string;
    slug: string;
    price_cents: number;
    compare_at_price_cents: number | null;
    savings_percent: number | null;
    is_active: boolean;
    items_count: number;
    created_at: string | null;
}

defineProps<{ bundles: BundleRow[] }>();

const { formatPrice } = useCurrency();
const deletingId = ref<number | null>(null);

function deleteBundle(id: number, name: string) {
    if (!confirm(`Delete bundle "${name}"? This cannot be undone.`)) { return; }
    deletingId.value = id;
    router.delete(`/vendor/bundles/${id}`, {
        onFinish: () => { deletingId.value = null; },
    });
}
</script>

<template>
    <Head title="Bundles" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-white">Bundles</h1>
                    <p class="text-sm text-navy-400 mt-0.5">Curated product sets at a discounted price</p>
                </div>
                <Link
                    href="/vendor/bundles/create"
                    class="flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-400 transition-colors shadow-lg shadow-brand-500/30"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    New Bundle
                </Link>
            </div>
        </template>

        <div class="p-6">
            <!-- Empty state -->
            <div v-if="bundles.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-navy-800 border border-navy-700">
                    <svg class="h-8 w-8 text-navy-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                    </svg>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-white">No bundles yet</h2>
                <p class="mt-1 text-sm text-navy-400">Create your first bundle to offer curated deals to customers.</p>
                <Link href="/vendor/bundles/create" class="mt-6 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-400 transition-colors">
                    Create Bundle
                </Link>
            </div>

            <!-- Table -->
            <div v-else class="overflow-hidden rounded-2xl border border-navy-700 bg-navy-900">
                <table class="min-w-full divide-y divide-navy-700">
                    <thead class="bg-navy-800/50">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-navy-400">Bundle</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-navy-400">Price</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-navy-400">Savings</th>
                            <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-navy-400">Products</th>
                            <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-navy-400">Status</th>
                            <th class="px-4 py-3.5" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-navy-800">
                        <tr v-for="bundle in bundles" :key="bundle.id" class="hover:bg-navy-800/30 transition-colors">
                            <td class="px-6 py-4">
                                <p class="font-medium text-white">{{ bundle.name }}</p>
                                <p class="text-xs text-navy-400 mt-0.5">{{ bundle.slug }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-semibold text-white">{{ formatPrice(bundle.price_cents) }}</p>
                                <p v-if="bundle.compare_at_price_cents" class="text-xs text-navy-400 line-through">
                                    {{ formatPrice(bundle.compare_at_price_cents) }}
                                </p>
                            </td>
                            <td class="px-4 py-4">
                                <span v-if="bundle.savings_percent" class="inline-flex items-center rounded-full bg-accent-500/15 border border-accent-500/20 px-2.5 py-0.5 text-xs font-semibold text-accent-400">
                                    {{ bundle.savings_percent }}% off
                                </span>
                                <span v-else class="text-navy-500">—</span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="text-sm text-navy-300">{{ bundle.items_count }}</span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span
                                    :class="bundle.is_active
                                        ? 'bg-accent-500/15 text-accent-400 border-accent-500/20'
                                        : 'bg-navy-700/50 text-navy-400 border-navy-600/30'"
                                    class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                >
                                    {{ bundle.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="`/vendor/bundles/${bundle.id}/edit`"
                                        class="rounded-lg p-1.5 text-navy-400 hover:bg-navy-700 hover:text-white transition-colors"
                                        title="Edit"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </Link>
                                    <button
                                        :disabled="deletingId === bundle.id"
                                        class="rounded-lg p-1.5 text-navy-400 hover:bg-red-500/15 hover:text-red-400 transition-colors disabled:opacity-50"
                                        title="Delete"
                                        @click="deleteBundle(bundle.id, bundle.name)"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </VendorLayout>
</template>
