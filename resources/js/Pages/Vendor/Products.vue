<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';
import { useCurrency } from '@/Composables/useCurrency';

interface ProductRow {
    id: number;
    name: string;
    sku: string;
    slug: string;
    price_cents: number;
    sale_price: number | null;
    is_active: boolean;
    is_featured: boolean;
    category: { name: string } | null;
    stock: { quantity_available: number } | null;
    variants_count: number;
    images: string[] | null;
}

interface PaginatedProducts {
    data: ProductRow[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    products: PaginatedProducts;
    search: string | null;
    active_filter: string | null;
    stats: {
        total_active: number;
        total_inactive: number;
    };
}

const props = defineProps<Props>();

const { formatPrice: formatCurrency } = useCurrency();

const searchInput = ref(props.search ?? '');
const selectedIds = ref<Set<number>>(new Set());
const bulkProcessing = ref(false);

const allSelected = computed(() =>
    props.products.data.length > 0 &&
    props.products.data.every(p => selectedIds.value.has(p.id)),
);

const someSelected = computed(() => selectedIds.value.size > 0);

function toggleAll(): void {
    if (allSelected.value) {
        props.products.data.forEach(p => selectedIds.value.delete(p.id));
    } else {
        props.products.data.forEach(p => selectedIds.value.add(p.id));
    }
}

function toggleOne(id: number): void {
    if (selectedIds.value.has(id)) {
        selectedIds.value.delete(id);
    } else {
        selectedIds.value.add(id);
    }
}

function clearSelection(): void {
    selectedIds.value.clear();
}

function runBulkAction(action: 'activate' | 'deactivate' | 'delete'): void {
    if (selectedIds.value.size === 0) { return; }

    if (action === 'delete' && !confirm(`Delete ${selectedIds.value.size} product(s)? This cannot be undone.`)) {
        return;
    }

    bulkProcessing.value = true;

    router.post('/vendor/products/bulk-action', {
        action,
        ids: [...selectedIds.value],
    }, {
        onSuccess: () => { selectedIds.value.clear(); },
        onFinish: () => { bulkProcessing.value = false; },
    });
}

function applyFilters(): void {
    router.get('/vendor/products', {
        ...(searchInput.value ? { search: searchInput.value } : {}),
        ...(props.active_filter !== null ? { active: props.active_filter } : {}),
    }, { preserveState: true });
}

function setActiveFilter(val: string | null): void {
    router.get('/vendor/products', {
        ...(searchInput.value ? { search: searchInput.value } : {}),
        ...(val !== null ? { active: val } : {}),
    }, { preserveState: true });
}

function stockClass(qty: number | undefined): string {
    if (qty === undefined || qty === null) { return 'text-navy-500'; }
    if (qty <= 0) { return 'text-red-400'; }
    if (qty <= 5) { return 'text-amber-400'; }
    return 'text-accent-400';
}
</script>

<template>
    <Head title="Products" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Products</span>
            </div>
        </template>

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Products</h1>
                <p class="mt-1 text-sm text-navy-400">
                    <span class="text-accent-400">{{ stats.total_active }}</span> active ·
                    <span class="text-navy-400">{{ stats.total_inactive }}</span> inactive
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Link
                    href="/vendor/products/import"
                    class="inline-flex items-center gap-2 rounded-xl border border-navy-700 px-4 py-2 text-sm font-medium text-navy-300 hover:border-navy-600 hover:text-white transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                    Import CSV
                </Link>
                <a
                    href="/vendor/products/create"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-400 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add Product
                </a>
            </div>
        </div>

        <!-- Filters row -->
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-navy-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input
                    v-model="searchInput"
                    @keyup.enter="applyFilters"
                    type="text"
                    placeholder="Search by name or SKU…"
                    class="w-full rounded-xl border border-navy-700/50 bg-navy-800/50 py-2.5 pl-9 pr-4 text-sm text-white placeholder-navy-500 focus:border-brand-500/50 focus:outline-none focus:ring-1 focus:ring-brand-500/30"
                />
            </div>

            <div class="flex gap-2">
                <button
                    v-for="[val, label] in [[null, 'All'], ['1', 'Active'], ['0', 'Inactive']]"
                    :key="String(val)"
                    @click="setActiveFilter(val)"
                    class="rounded-xl px-4 py-2 text-sm font-medium transition-all"
                    :class="active_filter === val
                        ? 'bg-brand-500/15 text-brand-300 border border-brand-500/20'
                        : 'text-navy-400 border border-navy-700/50 hover:text-white hover:bg-navy-800/70'"
                >
                    {{ label }}
                </button>
            </div>
        </div>

        <!-- Bulk action toolbar -->
        <transition
            enter-active-class="transition-all duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="someSelected"
                class="mb-4 flex items-center justify-between rounded-xl border border-brand-500/20 bg-brand-500/10 px-5 py-3"
            >
                <span class="text-sm font-medium text-brand-300">
                    {{ selectedIds.size }} product{{ selectedIds.size === 1 ? '' : 's' }} selected
                </span>
                <div class="flex items-center gap-2">
                    <button
                        @click="runBulkAction('activate')"
                        :disabled="bulkProcessing"
                        class="rounded-lg border border-accent-500/30 bg-accent-500/10 px-3 py-1.5 text-xs font-medium text-accent-400 transition-colors hover:bg-accent-500/20 disabled:opacity-50"
                    >
                        Activate
                    </button>
                    <button
                        @click="runBulkAction('deactivate')"
                        :disabled="bulkProcessing"
                        class="rounded-lg border border-navy-600/50 bg-navy-800/60 px-3 py-1.5 text-xs font-medium text-navy-300 transition-colors hover:bg-navy-700/60 hover:text-white disabled:opacity-50"
                    >
                        Deactivate
                    </button>
                    <button
                        @click="runBulkAction('delete')"
                        :disabled="bulkProcessing"
                        class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-400 transition-colors hover:bg-red-500/20 disabled:opacity-50"
                    >
                        Delete
                    </button>
                    <button
                        @click="clearSelection"
                        class="ml-1 text-xs text-navy-500 hover:text-navy-300 transition-colors"
                    >
                        Clear
                    </button>
                </div>
            </div>
        </transition>

        <!-- Products table -->
        <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 overflow-hidden">
            <div v-if="products.data.length === 0" class="flex flex-col items-center justify-center py-20">
                <p class="text-sm text-navy-400">No products found</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-navy-800/40">
                            <th class="px-5 py-3 w-10">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    :indeterminate="someSelected && !allSelected"
                                    @change="toggleAll"
                                    class="h-4 w-4 rounded border-navy-600 bg-navy-800 accent-brand-500 cursor-pointer"
                                />
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">Product</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">SKU</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-navy-500">Category</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-navy-500">Price</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-navy-500">Stock</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-navy-500">Variants</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-navy-500">Status</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-navy-500">Edit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-navy-800/30">
                        <tr
                            v-for="product in products.data"
                            :key="product.id"
                            class="hover:bg-navy-800/30 transition-colors"
                            :class="{ 'bg-brand-500/5': selectedIds.has(product.id) }"
                        >
                            <td class="px-5 py-3.5 w-10">
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.has(product.id)"
                                    @change="toggleOne(product.id)"
                                    class="h-4 w-4 rounded border-navy-600 bg-navy-800 accent-brand-500 cursor-pointer"
                                />
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 shrink-0 rounded-lg bg-navy-700/50 overflow-hidden">
                                        <img v-if="product.images?.[0]" :src="product.images[0]" :alt="product.name" class="h-full w-full object-cover" />
                                        <div v-else class="h-full w-full flex items-center justify-center">
                                            <svg class="h-4 w-4 text-navy-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-medium text-white">{{ product.name }}</div>
                                        <span v-if="product.is_featured" class="text-xs text-amber-400">Featured</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-navy-400 text-xs font-mono">{{ product.sku }}</td>
                            <td class="px-5 py-3.5 text-navy-300 text-xs">{{ product.category?.name ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="text-white font-medium">{{ formatCurrency(product.sale_price ?? product.price_cents) }}</div>
                                <div v-if="product.sale_price" class="text-xs text-navy-500 line-through">{{ formatCurrency(product.price_cents) }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span :class="['text-xs font-semibold', stockClass(product.stock?.quantity_available)]">
                                    {{ product.variants_count > 0 ? '—' : (product.stock?.quantity_available ?? '—') }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-center text-navy-300 text-xs">{{ product.variants_count || '—' }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="product.is_active
                                        ? 'bg-accent-500/15 text-accent-400 border border-accent-500/20'
                                        : 'bg-navy-700/50 text-navy-400 border border-navy-600/30'"
                                >
                                    {{ product.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a
                                    :href="`/vendor/products/${product.id}/edit`"
                                    class="text-xs text-brand-400 hover:text-brand-300 transition-colors font-medium"
                                >
                                    Edit
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="products.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-navy-800/40">
                <p class="text-xs text-navy-500">Page {{ products.current_page }} of {{ products.last_page }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in products.links"
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
