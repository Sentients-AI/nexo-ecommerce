<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import VendorLayout from '@/Layouts/VendorLayout.vue';
import { useCurrency } from '@/Composables/useCurrency';

interface ProductOption {
    id: number;
    name: string;
    sku: string;
    price_cents: number;
    images: string[];
}

interface BundleItemRow {
    id: number;
    product_id: number;
    variant_id: number | null;
    quantity: number;
    product: { name: string };
}

interface BundleData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_cents: number;
    compare_at_price_cents: number | null;
    is_active: boolean;
    items: BundleItemRow[];
}

const props = defineProps<{ bundle: BundleData; products: ProductOption[] }>();

const { formatPrice } = useCurrency();

const form = useForm({
    name: props.bundle.name,
    description: props.bundle.description ?? '',
    price_cents: String(props.bundle.price_cents),
    compare_at_price_cents: props.bundle.compare_at_price_cents ? String(props.bundle.compare_at_price_cents) : '',
    is_active: props.bundle.is_active,
    items: props.bundle.items.map(i => ({
        product_id: i.product_id as number | string,
        variant_id: i.variant_id,
        quantity: i.quantity,
    })),
});

const suggestedCompareAt = computed(() => {
    let total = 0;
    for (const item of form.items) {
        if (!item.product_id) { continue; }
        const product = props.products.find(p => p.id === Number(item.product_id));
        if (product) { total += product.price_cents * item.quantity; }
    }
    return total;
});

function addItem() {
    form.items.push({ product_id: '', variant_id: null, quantity: 1 });
}

function removeItem(index: number) {
    form.items.splice(index, 1);
}

function useSuggestedPrice() {
    form.compare_at_price_cents = String(suggestedCompareAt.value);
}

function submit() {
    form.patch(`/vendor/bundles/${props.bundle.id}`);
}
</script>

<template>
    <Head :title="`Edit: ${bundle.name}`" />

    <VendorLayout>
        <template #header>
            <h1 class="text-xl font-bold text-white">Edit Bundle</h1>
        </template>

        <div class="p-6 max-w-3xl">
            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <!-- Basic info -->
                <div class="rounded-2xl border border-navy-700 bg-navy-900 p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-navy-400 mb-4">Bundle Details</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-navy-200 mb-1.5">Name <span class="text-red-400">*</span></label>
                            <input v-model="form.name" type="text" class="w-full rounded-xl border border-navy-600 bg-navy-800 px-4 py-2.5 text-white placeholder:text-navy-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none" />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-navy-200 mb-1.5">Description</label>
                            <textarea v-model="form.description" rows="3" class="w-full rounded-xl border border-navy-600 bg-navy-800 px-4 py-2.5 text-white placeholder:text-navy-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none resize-none" />
                        </div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-navy-600 text-brand-500 focus:ring-brand-500 bg-navy-800" />
                            <span class="text-sm font-medium text-navy-200">Active (visible on storefront)</span>
                        </label>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="rounded-2xl border border-navy-700 bg-navy-900 p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-navy-400 mb-4">Pricing</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-navy-200 mb-1.5">Bundle Price (cents) <span class="text-red-400">*</span></label>
                            <input v-model="form.price_cents" type="number" min="1" class="w-full rounded-xl border border-navy-600 bg-navy-800 px-4 py-2.5 text-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none" />
                            <p class="mt-1 text-xs text-navy-500">{{ form.price_cents ? formatPrice(Number(form.price_cents)) : '' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-navy-200 mb-1.5">Compare-at Price (cents)</label>
                            <div class="flex gap-2">
                                <input v-model="form.compare_at_price_cents" type="number" min="1" class="flex-1 rounded-xl border border-navy-600 bg-navy-800 px-4 py-2.5 text-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none" />
                                <button v-if="suggestedCompareAt > 0" type="button" @click="useSuggestedPrice" class="shrink-0 rounded-xl border border-brand-500/30 bg-brand-500/10 px-3 text-xs font-medium text-brand-400 hover:bg-brand-500/20 transition-colors">Auto</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products -->
                <div class="rounded-2xl border border-navy-700 bg-navy-900 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-navy-400">Products in Bundle</h2>
                        <button type="button" @click="addItem" class="text-xs font-medium text-brand-400 hover:text-brand-300 transition-colors flex items-center gap-1">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Add Product
                        </button>
                    </div>
                    <div class="flex flex-col gap-3">
                        <div v-for="(item, index) in form.items" :key="index" class="flex items-center gap-3 rounded-xl border border-navy-700 bg-navy-800/50 p-3">
                            <div class="flex-1">
                                <select v-model="item.product_id" class="w-full rounded-lg border border-navy-600 bg-navy-800 px-3 py-2 text-sm text-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
                                    <option value="">Select product…</option>
                                    <option v-for="product in products" :key="product.id" :value="product.id">
                                        {{ product.name }} ({{ formatPrice(product.price_cents) }})
                                    </option>
                                </select>
                            </div>
                            <div class="w-20">
                                <input v-model.number="item.quantity" type="number" min="1" max="99" class="w-full rounded-lg border border-navy-600 bg-navy-800 px-3 py-2 text-center text-sm text-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none" />
                            </div>
                            <button v-if="form.items.length > 2" type="button" @click="removeItem(index)" class="rounded-lg p-1.5 text-navy-500 hover:bg-red-500/15 hover:text-red-400 transition-colors">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <div v-else class="w-8" />
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3">
                    <a href="/vendor/bundles" class="rounded-xl border border-navy-600 px-5 py-2.5 text-sm font-medium text-navy-300 hover:bg-navy-800 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" :disabled="form.processing" class="rounded-xl bg-brand-500 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-400 disabled:opacity-60 transition-colors shadow-lg shadow-brand-500/30">
                        {{ form.processing ? 'Saving…' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </VendorLayout>
</template>
