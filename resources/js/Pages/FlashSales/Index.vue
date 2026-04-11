<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

interface FlashProduct {
    id: number;
    name: string;
    slug: string;
    price_cents: number;
    sale_price_cents: number | null;
    discounted_price_cents: number;
    image: string | null;
    in_stock: boolean;
}

interface FlashSale {
    id: number;
    name: string;
    description: string | null;
    discount_type: 'fixed' | 'percentage';
    discount_value: number;
    ends_at: string;
    seconds_remaining: number;
    products: FlashProduct[];
}

const props = defineProps<{ flashSales: FlashSale[] }>();
const page = usePage();
const locale = computed(() => (page.props as any).locale ?? 'en');

// Live countdown state: saleId → seconds remaining
const countdowns = ref<Record<number, number>>({});
let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    props.flashSales.forEach(s => {
        countdowns.value[s.id] = s.seconds_remaining;
    });

    timer = setInterval(() => {
        for (const id of Object.keys(countdowns.value)) {
            const current = countdowns.value[Number(id)];
            if (current > 0) {
                countdowns.value[Number(id)] = current - 1;
            }
        }
    }, 1000);
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }
});

function formatCountdown(seconds: number): string {
    if (seconds <= 0) { return '00:00:00'; }
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return [h, m, s].map(n => String(n).padStart(2, '0')).join(':');
}

function formatPrice(cents: number): string {
    return '$' + (cents / 100).toFixed(2);
}

function discountLabel(sale: FlashSale): string {
    return sale.discount_type === 'percentage'
        ? `${(sale.discount_value / 100).toFixed(0)}% OFF`
        : `$${(sale.discount_value / 100).toFixed(2)} OFF`;
}
</script>

<template>
    <Head title="Flash Sales" />

    <GuestLayout>
        <div class="max-w-6xl mx-auto px-4 py-10">
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white flex items-center justify-center gap-2">
                    ⚡ Flash Sales
                </h1>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    Limited-time deals — grab them before they're gone.
                </p>
            </div>

            <!-- Empty state -->
            <div
                v-if="flashSales.length === 0"
                class="rounded-2xl border border-dashed border-gray-200 dark:border-gray-700 p-20 text-center"
            >
                <p class="text-5xl mb-4">⚡</p>
                <p class="font-semibold text-gray-900 dark:text-white text-lg">No flash sales running right now</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Check back soon for limited-time deals.</p>
            </div>

            <!-- Flash sale sections -->
            <div v-else class="space-y-12">
                <section v-for="sale in flashSales" :key="sale.id">
                    <!-- Sale header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full bg-red-500 px-2.5 py-0.5 text-xs font-bold text-white uppercase tracking-wide">
                                    {{ discountLabel(sale) }}
                                </span>
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                    {{ sale.name }}
                                </h2>
                            </div>
                            <p v-if="sale.description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ sale.description }}
                            </p>
                        </div>

                        <!-- Countdown -->
                        <div class="flex items-center gap-2 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-2.5">
                            <span class="text-xs font-medium text-red-600 dark:text-red-400 uppercase tracking-wide">Ends in</span>
                            <span class="text-xl font-mono font-bold text-red-600 dark:text-red-400">
                                {{ formatCountdown(countdowns[sale.id] ?? 0) }}
                            </span>
                        </div>
                    </div>

                    <!-- Products grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        <Link
                            v-for="product in sale.products"
                            :key="product.id"
                            :href="`/${locale}/products/${product.slug}`"
                            class="group relative rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden hover:shadow-md transition-shadow"
                        >
                            <!-- Discount badge -->
                            <div class="absolute top-2 left-2 rounded-full bg-red-500 px-2 py-0.5 text-xs font-bold text-white z-10">
                                {{ discountLabel(sale) }}
                            </div>

                            <!-- Out of stock overlay -->
                            <div
                                v-if="!product.in_stock"
                                class="absolute inset-0 bg-white/60 dark:bg-black/60 flex items-center justify-center z-20 rounded-2xl"
                            >
                                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">Sold Out</span>
                            </div>

                            <!-- Image -->
                            <div class="aspect-square bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                <img
                                    v-if="product.image"
                                    :src="product.image"
                                    :alt="product.name"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-300 dark:text-gray-600">
                                    <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M13.5 12h.008v.008H13.5V12zm0 0H9m4.06-7.19l-4.125-4.125a1.802 1.802 0 00-2.557 0L3 8.25m0 0v11.25A2.25 2.25 0 005.25 21.75h13.5A2.25 2.25 0 0021 19.5V8.25m-18 0h18" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Info -->
                            <div class="p-3">
                                <p class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2">
                                    {{ product.name }}
                                </p>
                                <div class="mt-1.5 flex items-baseline gap-2">
                                    <span class="text-base font-bold text-red-600 dark:text-red-400">
                                        {{ formatPrice(product.discounted_price_cents) }}
                                    </span>
                                    <span class="text-xs text-gray-400 line-through">
                                        {{ formatPrice(product.price_cents) }}
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </GuestLayout>
</template>
