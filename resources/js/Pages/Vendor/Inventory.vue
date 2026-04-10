<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';

interface Movement {
    type: string;
    quantity: number;
    reason: string | null;
    created_at: string;
}

interface StockInfo {
    id: number;
    quantity_available: number;
    quantity_reserved: number;
    movements: Movement[];
}

interface VariantRow {
    id: number;
    sku: string;
    attributes: { type: string; value: string }[];
    stock: StockInfo | null;
}

interface ProductRow {
    id: number;
    name: string;
    sku: string;
    stock: StockInfo | null;
    variants: VariantRow[];
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
    low_stock_filter: boolean;
    stats: {
        low_stock_count: number;
        out_of_stock_count: number;
    };
}

const props = defineProps<Props>();

const searchInput = ref(props.search ?? '');

function applyFilters(): void {
    router.get('/vendor/inventory', {
        ...(searchInput.value ? { search: searchInput.value } : {}),
        ...(props.low_stock_filter ? { low_stock: '1' } : {}),
    }, { preserveState: true });
}

function toggleLowStock(): void {
    router.get('/vendor/inventory', {
        ...(searchInput.value ? { search: searchInput.value } : {}),
        ...(!props.low_stock_filter ? { low_stock: '1' } : {}),
    }, { preserveState: true });
}

// Inline stock edit
const editingStockId = ref<number | null>(null);
const stockForm = useForm({ quantity_available: 0 });

// Movement history toggle
const expandedMovements = ref<Set<number>>(new Set());

function toggleMovements(stockId: number): void {
    if (expandedMovements.value.has(stockId)) {
        expandedMovements.value.delete(stockId);
    } else {
        expandedMovements.value.add(stockId);
    }
}

function formatMovementDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function startEdit(stockId: number, currentQty: number): void {
    editingStockId.value = stockId;
    stockForm.quantity_available = currentQty;
}

function cancelEdit(): void {
    editingStockId.value = null;
}

function saveStock(stockId: number): void {
    stockForm.patch(`/vendor/inventory/${stockId}`, {
        onSuccess: () => { editingStockId.value = null; },
    });
}

function stockBadgeClass(qty: number): string {
    if (qty <= 0) { return 'bg-red-500/15 text-red-400 border border-red-500/20'; }
    if (qty <= 5) { return 'bg-amber-500/15 text-amber-400 border border-amber-500/20'; }
    return 'bg-accent-500/15 text-accent-400 border border-accent-500/20';
}

function stockLabel(qty: number): string {
    if (qty <= 0) { return 'Out of stock'; }
    if (qty <= 5) { return `${qty} left`; }
    return String(qty);
}
</script>

<template>
    <Head title="Inventory" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Inventory</span>
            </div>
        </template>

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Inventory</h1>
                <p class="mt-1 text-sm text-navy-400">Manage stock levels across all products and variants</p>
            </div>

            <!-- Alert badges -->
            <div class="flex gap-3">
                <div class="flex items-center gap-2 rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-2.5">
                    <span class="text-lg font-bold text-amber-400">{{ stats.low_stock_count }}</span>
                    <span class="text-xs text-amber-300/70">Low stock</span>
                </div>
                <div class="flex items-center gap-2 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-2.5">
                    <span class="text-lg font-bold text-red-400">{{ stats.out_of_stock_count }}</span>
                    <span class="text-xs text-red-300/70">Out of stock</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-navy-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input
                    v-model="searchInput"
                    @keyup.enter="applyFilters"
                    type="text"
                    placeholder="Search products…"
                    class="w-full rounded-xl border border-navy-700/50 bg-navy-800/50 py-2.5 pl-9 pr-4 text-sm text-white placeholder-navy-500 focus:border-brand-500/50 focus:outline-none focus:ring-1 focus:ring-brand-500/30"
                />
            </div>
            <button
                @click="toggleLowStock"
                class="rounded-xl px-4 py-2.5 text-sm font-medium transition-all border"
                :class="low_stock_filter
                    ? 'bg-amber-500/15 text-amber-300 border-amber-500/30'
                    : 'text-navy-400 border-navy-700/50 hover:text-white hover:bg-navy-800/70'"
            >
                Low stock only
            </button>
        </div>

        <!-- Products list -->
        <div class="space-y-3">
            <div
                v-if="products.data.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-navy-800/60 bg-navy-900/60 py-20"
            >
                <p class="text-sm text-navy-400">No products found</p>
            </div>

            <div
                v-for="product in products.data"
                :key="product.id"
                class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 overflow-hidden"
            >
                <!-- Product header -->
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-navy-800/30">
                    <div class="flex items-center gap-3">
                        <div>
                            <span class="font-medium text-white text-sm">{{ product.name }}</span>
                            <span class="ml-2 text-xs font-mono text-navy-500">{{ product.sku }}</span>
                        </div>
                    </div>
                </div>

                <!-- Product-level stock (no variants) -->
                <div v-if="product.variants.length === 0" class="px-5 py-3">
                    <div v-if="product.stock" class="space-y-2">
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-navy-400 min-w-24">Base stock</span>
                            <div class="flex items-center gap-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="stockBadgeClass(product.stock.quantity_available)"
                                >
                                    {{ stockLabel(product.stock.quantity_available) }}
                                </span>
                                <span class="text-xs text-navy-500">{{ product.stock.quantity_reserved }} reserved</span>
                            </div>

                            <!-- Inline edit -->
                            <div v-if="editingStockId === product.stock.id" class="flex items-center gap-2 ml-auto">
                                <input
                                    v-model.number="stockForm.quantity_available"
                                    type="number"
                                    min="0"
                                    class="w-24 rounded-lg border border-navy-600/50 bg-navy-800 px-3 py-1.5 text-sm text-white focus:border-brand-500/50 focus:outline-none"
                                />
                                <button @click="saveStock(product.stock!.id)" class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-400 transition-colors">Save</button>
                                <button @click="cancelEdit" class="rounded-lg border border-navy-700/50 px-3 py-1.5 text-xs text-navy-400 hover:text-white transition-colors">Cancel</button>
                            </div>
                            <div v-else class="flex items-center gap-3 ml-auto">
                                <button
                                    v-if="product.stock.movements.length > 0"
                                    @click="toggleMovements(product.stock!.id)"
                                    class="text-xs text-navy-500 hover:text-navy-300 transition-colors"
                                >
                                    {{ expandedMovements.has(product.stock.id) ? 'Hide' : 'History' }}
                                    ({{ product.stock.movements.length }})
                                </button>
                                <button
                                    @click="startEdit(product.stock.id, product.stock.quantity_available)"
                                    class="text-xs text-brand-400 hover:text-brand-300 transition-colors"
                                >
                                    Edit
                                </button>
                            </div>
                        </div>

                        <!-- Movement history -->
                        <div
                            v-if="expandedMovements.has(product.stock.id) && product.stock.movements.length > 0"
                            class="mt-2 rounded-lg border border-navy-700/40 bg-navy-950/60 divide-y divide-navy-800/40"
                        >
                            <div
                                v-for="(mv, i) in product.stock.movements"
                                :key="i"
                                class="flex items-center justify-between px-3 py-2"
                            >
                                <div>
                                    <span class="text-xs text-navy-400">{{ mv.reason ?? mv.type }}</span>
                                    <span class="ml-2 text-xs text-navy-600">{{ formatMovementDate(mv.created_at) }}</span>
                                </div>
                                <span
                                    class="text-xs font-semibold"
                                    :class="mv.type === 'in' ? 'text-accent-400' : 'text-red-400'"
                                >
                                    {{ mv.type === 'in' ? '+' : '-' }}{{ mv.quantity }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-xs text-navy-500 italic">No stock record</div>
                </div>

                <!-- Variant-level stock -->
                <div v-else class="divide-y divide-navy-800/30">
                    <div
                        v-for="variant in product.variants"
                        :key="variant.id"
                        class="px-5 py-3 space-y-2"
                    >
                        <div class="flex items-center gap-4">
                            <div class="min-w-0 flex-1">
                                <span class="text-xs font-mono text-navy-400">{{ variant.sku }}</span>
                                <div class="flex gap-1 mt-0.5">
                                    <span
                                        v-for="attr in variant.attributes"
                                        :key="attr.type"
                                        class="text-xs rounded bg-navy-700/50 px-1.5 py-0.5 text-navy-300"
                                    >
                                        {{ attr.value }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="variant.stock" class="flex items-center gap-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="stockBadgeClass(variant.stock.quantity_available)"
                                >
                                    {{ stockLabel(variant.stock.quantity_available) }}
                                </span>
                                <span class="text-xs text-navy-500">{{ variant.stock.quantity_reserved }} reserved</span>
                            </div>

                            <!-- Inline edit for variant -->
                            <div v-if="variant.stock && editingStockId === variant.stock.id" class="flex items-center gap-2">
                                <input
                                    v-model.number="stockForm.quantity_available"
                                    type="number"
                                    min="0"
                                    class="w-24 rounded-lg border border-navy-600/50 bg-navy-800 px-3 py-1.5 text-sm text-white focus:border-brand-500/50 focus:outline-none"
                                />
                                <button @click="saveStock(variant.stock!.id)" class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-400 transition-colors">Save</button>
                                <button @click="cancelEdit" class="rounded-lg border border-navy-700/50 px-3 py-1.5 text-xs text-navy-400 hover:text-white transition-colors">Cancel</button>
                            </div>
                            <div v-else-if="variant.stock" class="flex items-center gap-3">
                                <button
                                    v-if="variant.stock.movements.length > 0"
                                    @click="toggleMovements(variant.stock!.id)"
                                    class="text-xs text-navy-500 hover:text-navy-300 transition-colors"
                                >
                                    {{ expandedMovements.has(variant.stock.id) ? 'Hide' : 'History' }}
                                    ({{ variant.stock.movements.length }})
                                </button>
                                <button
                                    @click="startEdit(variant.stock.id, variant.stock.quantity_available)"
                                    class="text-xs text-brand-400 hover:text-brand-300 transition-colors"
                                >
                                    Edit
                                </button>
                            </div>
                            <span v-else class="ml-auto text-xs text-navy-600 italic">No stock</span>
                        </div>

                        <!-- Movement history for variant -->
                        <div
                            v-if="variant.stock && expandedMovements.has(variant.stock.id) && variant.stock.movements.length > 0"
                            class="rounded-lg border border-navy-700/40 bg-navy-950/60 divide-y divide-navy-800/40"
                        >
                            <div
                                v-for="(mv, i) in variant.stock.movements"
                                :key="i"
                                class="flex items-center justify-between px-3 py-2"
                            >
                                <div>
                                    <span class="text-xs text-navy-400">{{ mv.reason ?? mv.type }}</span>
                                    <span class="ml-2 text-xs text-navy-600">{{ formatMovementDate(mv.created_at) }}</span>
                                </div>
                                <span
                                    class="text-xs font-semibold"
                                    :class="mv.type === 'in' ? 'text-accent-400' : 'text-red-400'"
                                >
                                    {{ mv.type === 'in' ? '+' : '-' }}{{ mv.quantity }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="products.last_page > 1" class="mt-4 flex items-center justify-between">
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
    </VendorLayout>
</template>
