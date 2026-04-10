<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { useCurrency } from '@/Composables/useCurrency';
import { useLocale } from '@/Composables/useLocale';

interface BundleRow {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_cents: number;
    compare_at_price_cents: number | null;
    savings_percent: number | null;
    images: string[];
    items: Array<{ product: { name: string; images: string[] }; quantity: number }>;
}

defineProps<{ bundles: BundleRow[] }>();

const { formatPrice } = useCurrency();
const { localePath } = useLocale();
</script>

<template>
    <Head title="Bundle Deals" />

    <GuestLayout>
        <div class="min-h-screen bg-slate-50 dark:bg-navy-950 py-10">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Bundle Deals</h1>
                    <p class="mt-2 text-slate-600 dark:text-navy-300">Curated sets at a discounted price — buy together and save.</p>
                </div>

                <!-- Empty state -->
                <div v-if="bundles.length === 0" class="flex flex-col items-center justify-center py-24 text-center">
                    <svg class="h-16 w-16 text-slate-300 dark:text-navy-700" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                    </svg>
                    <h2 class="mt-4 text-lg font-semibold text-slate-700 dark:text-navy-200">No bundles available yet</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-navy-400">Check back soon for curated bundle deals.</p>
                    <Link :href="localePath('/products')" class="mt-6 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-400 transition-colors">
                        Browse Products
                    </Link>
                </div>

                <!-- Bundle grid -->
                <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="bundle in bundles"
                        :key="bundle.id"
                        :href="localePath(`/bundles/${bundle.slug}`)"
                        class="group relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 dark:border-navy-700 bg-white dark:bg-navy-900 transition-all hover:shadow-lg hover:-translate-y-0.5"
                    >
                        <!-- Image -->
                        <div class="aspect-video overflow-hidden bg-gradient-to-br from-brand-500/10 to-accent-500/10 flex items-center justify-center">
                            <img
                                v-if="bundle.images.length"
                                :src="bundle.images[0]"
                                :alt="bundle.name"
                                class="h-full w-full object-cover transition-transform group-hover:scale-105"
                            />
                            <div v-else class="flex items-center gap-2 p-4 flex-wrap justify-center">
                                <div
                                    v-for="(item, i) in bundle.items.slice(0, 3)"
                                    :key="i"
                                    class="h-12 w-12 overflow-hidden rounded-lg bg-white dark:bg-navy-800 shadow-sm"
                                >
                                    <img
                                        v-if="item.product.images?.length"
                                        :src="item.product.images[0]"
                                        :alt="item.product.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <div v-else class="h-full w-full bg-slate-100 dark:bg-navy-700" />
                                </div>
                            </div>
                        </div>

                        <!-- Savings badge -->
                        <div v-if="bundle.savings_percent" class="absolute top-3 right-3">
                            <span class="rounded-full bg-accent-500 px-2.5 py-1 text-xs font-bold text-white shadow">
                                {{ bundle.savings_percent }}% OFF
                            </span>
                        </div>

                        <!-- Content -->
                        <div class="flex flex-1 flex-col gap-3 p-5">
                            <h2 class="text-base font-semibold text-slate-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors line-clamp-2">
                                {{ bundle.name }}
                            </h2>

                            <!-- Included items preview -->
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="(item, i) in bundle.items.slice(0, 4)"
                                    :key="i"
                                    class="rounded-full bg-slate-100 dark:bg-navy-800 px-2.5 py-0.5 text-xs text-slate-600 dark:text-navy-300"
                                >
                                    {{ item.quantity > 1 ? `${item.quantity}× ` : '' }}{{ item.product.name }}
                                </span>
                                <span
                                    v-if="bundle.items.length > 4"
                                    class="rounded-full bg-slate-100 dark:bg-navy-800 px-2.5 py-0.5 text-xs text-slate-500"
                                >
                                    +{{ bundle.items.length - 4 }} more
                                </span>
                            </div>

                            <!-- Pricing -->
                            <div class="mt-auto flex items-baseline gap-2">
                                <span class="text-xl font-bold text-slate-900 dark:text-white">
                                    {{ formatPrice(bundle.price_cents) }}
                                </span>
                                <span v-if="bundle.compare_at_price_cents" class="text-sm text-slate-400 line-through">
                                    {{ formatPrice(bundle.compare_at_price_cents) }}
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
