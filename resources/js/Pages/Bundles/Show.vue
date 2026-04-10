<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { useCurrency } from '@/Composables/useCurrency';
import { useApi } from '@/Composables/useApi';
import { useLocale } from '@/Composables/useLocale';

interface BundleItem {
    id: number;
    quantity: number;
    product: {
        id: number;
        name: string;
        slug: string;
        images: string[];
        price_cents: number;
        sale_price: number | null;
    };
    variant: { id: number; sku: string } | null;
}

interface Bundle {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_cents: number;
    compare_at_price_cents: number | null;
    savings_percent: number | null;
    images: string[];
    in_stock: boolean;
    items: BundleItem[];
}

const props = defineProps<{ bundle: Bundle }>();

const { formatPrice } = useCurrency();
const { post } = useApi();
const { localePath } = useLocale();

const adding = ref(false);
const added = ref(false);
const errorMsg = ref<string | null>(null);

async function addToCart() {
    adding.value = true;
    errorMsg.value = null;

    const result = await post(`/api/v1/bundles/${props.bundle.slug}/cart`);

    adding.value = false;

    if (result) {
        added.value = true;
        setTimeout(() => { added.value = false; }, 2500);
    } else {
        errorMsg.value = 'Could not add bundle to cart. Some items may be out of stock.';
    }
}
</script>

<template>
    <Head :title="bundle.name" />

    <GuestLayout>
        <div class="min-h-screen bg-slate-50 dark:bg-navy-950 py-10">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <nav class="mb-6 flex items-center gap-2 text-sm text-slate-500 dark:text-navy-400">
                    <Link :href="localePath('/')" class="hover:text-brand-600">Home</Link>
                    <span>/</span>
                    <Link :href="localePath('/bundles')" class="hover:text-brand-600">Bundles</Link>
                    <span>/</span>
                    <span class="text-slate-700 dark:text-white">{{ bundle.name }}</span>
                </nav>

                <div class="grid gap-8 lg:grid-cols-5">
                    <!-- Image / visual -->
                    <div class="lg:col-span-2">
                        <div class="aspect-square overflow-hidden rounded-2xl bg-gradient-to-br from-brand-500/10 to-accent-500/10 border border-brand-500/20 flex items-center justify-center">
                            <img
                                v-if="bundle.images.length"
                                :src="bundle.images[0]"
                                :alt="bundle.name"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="text-center p-8">
                                <svg class="mx-auto h-20 w-20 text-brand-400/50" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                </svg>
                                <p class="mt-3 text-sm font-medium text-brand-400">Bundle Set</p>
                            </div>
                        </div>

                        <!-- Savings badge -->
                        <div v-if="bundle.savings_percent" class="mt-4 flex items-center justify-center gap-2 rounded-xl bg-accent-500/10 border border-accent-500/20 px-4 py-3">
                            <svg class="h-5 w-5 text-accent-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185z" />
                            </svg>
                            <span class="text-sm font-semibold text-accent-600 dark:text-accent-400">
                                Save {{ bundle.savings_percent }}% vs buying separately
                            </span>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="lg:col-span-3 flex flex-col gap-6">
                        <div>
                            <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ bundle.name }}</h1>
                            <p v-if="bundle.description" class="mt-3 text-slate-600 dark:text-navy-300 leading-relaxed">
                                {{ bundle.description }}
                            </p>
                        </div>

                        <!-- Price block -->
                        <div class="flex items-baseline gap-3">
                            <span class="text-4xl font-bold text-slate-900 dark:text-white">
                                {{ formatPrice(bundle.price_cents) }}
                            </span>
                            <span v-if="bundle.compare_at_price_cents" class="text-xl text-slate-400 line-through">
                                {{ formatPrice(bundle.compare_at_price_cents) }}
                            </span>
                        </div>

                        <!-- What's included -->
                        <div class="rounded-2xl border border-slate-200 dark:border-navy-700 bg-white dark:bg-navy-900 p-5">
                            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-navy-400">
                                What's Included
                            </h2>
                            <ul class="flex flex-col gap-3">
                                <li
                                    v-for="item in bundle.items"
                                    :key="item.id"
                                    class="flex items-center gap-3"
                                >
                                    <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100 dark:bg-navy-800">
                                        <img
                                            v-if="item.product.images?.length"
                                            :src="item.product.images[0]"
                                            :alt="item.product.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <div v-else class="h-full w-full flex items-center justify-center">
                                            <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <Link
                                            :href="localePath(`/products/${item.product.slug}`)"
                                            class="text-sm font-medium text-slate-800 dark:text-white hover:text-brand-600 dark:hover:text-brand-400 truncate block"
                                        >
                                            {{ item.product.name }}
                                        </Link>
                                        <p v-if="item.variant" class="text-xs text-slate-500 dark:text-navy-400">
                                            SKU: {{ item.variant.sku }}
                                        </p>
                                    </div>
                                    <div class="shrink-0 flex items-center gap-2">
                                        <span class="text-xs text-slate-500 dark:text-navy-400">×{{ item.quantity }}</span>
                                        <span class="text-sm font-medium text-slate-700 dark:text-navy-200">
                                            {{ formatPrice(item.product.sale_price ?? item.product.price_cents) }}
                                        </span>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <!-- Error -->
                        <div v-if="errorMsg" class="rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                            {{ errorMsg }}
                        </div>

                        <!-- CTA -->
                        <div class="flex items-center gap-3">
                            <button
                                v-if="bundle.in_stock"
                                :disabled="adding || added"
                                class="flex-1 flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-4 text-base font-semibold text-white shadow-lg shadow-brand-500/30 hover:bg-brand-400 disabled:opacity-60 transition-all"
                                @click="addToCart"
                            >
                                <svg v-if="adding" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                <svg v-else-if="added" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                                </svg>
                                {{ added ? 'Added to Cart!' : adding ? 'Adding…' : 'Add Bundle to Cart' }}
                            </button>

                            <div v-else class="flex-1 rounded-xl border border-slate-200 dark:border-navy-700 px-6 py-4 text-center text-sm font-medium text-slate-500 dark:text-navy-400">
                                Out of Stock
                            </div>

                            <Link :href="localePath('/cart')" class="rounded-xl border border-slate-300 dark:border-navy-700 px-5 py-4 text-sm font-medium text-slate-700 dark:text-navy-200 hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors">
                                View Cart
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
