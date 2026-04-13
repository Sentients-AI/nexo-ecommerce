<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductCard from '@/Components/Products/ProductCard.vue';
import Badge from '@/Components/UI/Badge.vue';
import { useLocale } from '@/Composables/useLocale';
import type { ProductApiResource, CategoryApiResource } from '@/types/api';

interface FlashSaleProduct {
    id: number;
    name: string;
    slug: string;
    price_cents: number;
    image: string | null;
    in_stock: boolean;
    discounted_price_cents: number;
}

interface FlashSale {
    id: number;
    name: string;
    discount_type: 'fixed' | 'percentage';
    discount_value: number;
    ends_at: string;
    seconds_remaining: number;
    products: FlashSaleProduct[];
}

interface StorefrontData {
    name: string;
    description: string | null;
    banner_path: string | null;
    logo_path: string | null;
    accent_color: string | null;
    social_links: Record<string, string>;
}

interface Props {
    featuredProducts?: ProductApiResource[];
    categories?: CategoryApiResource[];
    flashSales?: FlashSale[];
    storefront?: StorefrontData | null;
}

const props = withDefaults(defineProps<Props>(), {
    featuredProducts: () => [],
    categories: () => [],
    flashSales: () => [],
    storefront: null,
});

const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user !== null);
const Layout = computed(() => isAuthenticated.value ? AuthenticatedLayout : GuestLayout);
const { localePath } = useLocale();

// Flash sale countdown state: saleId → seconds remaining
const flashCountdowns = ref<Record<number, number>>({});
let flashTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    props.flashSales.forEach(s => {
        flashCountdowns.value[s.id] = s.seconds_remaining;
    });
    if (props.flashSales.length > 0) {
        flashTimer = setInterval(() => {
            for (const id of Object.keys(flashCountdowns.value)) {
                const current = flashCountdowns.value[Number(id)];
                if (current > 0) {
                    flashCountdowns.value[Number(id)] = current - 1;
                }
            }
        }, 1000);
    }
});

onUnmounted(() => {
    if (flashTimer) {
        clearInterval(flashTimer);
    }
});

function formatFlashCountdown(seconds: number): string {
    if (seconds <= 0) { return '00:00:00'; }
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return [h, m, s].map(n => String(n).padStart(2, '0')).join(':');
}

function discountLabel(sale: FlashSale): string {
    return sale.discount_type === 'percentage'
        ? `${(sale.discount_value / 100).toFixed(0)}% OFF`
        : `$${(sale.discount_value / 100).toFixed(2)} OFF`;
}

function flashPrice(cents: number): string {
    return '$' + (cents / 100).toFixed(2);
}

const email = ref('');
const isSubscribed = ref(false);

function handleSubscribe(e: Event) {
    e.preventDefault();
    if (email.value) {
        isSubscribed.value = true;
        email.value = '';
    }
}

const benefits = [
    {
        icon: 'truck',
        title: 'Free Shipping',
        description: 'On orders over $50',
        color: 'bg-brand-500/10 dark:bg-brand-900/30',
        iconColor: 'text-brand-500 dark:text-brand-400',
    },
    {
        icon: 'shield',
        title: 'Secure Payment',
        description: '100% secure checkout',
        color: 'bg-accent-500/10 dark:bg-accent-900/30',
        iconColor: 'text-accent-500 dark:text-accent-400',
    },
    {
        icon: 'refresh',
        title: 'Easy Returns',
        description: '30-day return policy',
        color: 'bg-amber-500/10 dark:bg-amber-900/30',
        iconColor: 'text-amber-500 dark:text-amber-400',
    },
    {
        icon: 'support',
        title: '24/7 Support',
        description: 'Dedicated support team',
        color: 'bg-rose-500/10 dark:bg-rose-900/30',
        iconColor: 'text-rose-500 dark:text-rose-400',
    },
];

const stats = [
    { label: 'Products', value: '10K+' },
    { label: 'Happy Customers', value: '50K+' },
    { label: 'Brands', value: '200+' },
    { label: 'Orders Shipped', value: '1M+' },
];

const productTab = ref<'featured' | 'new' | 'sale'>('featured');

const trendingItems = [
    '🔥 Trending: Wireless Earbuds',
    '⚡ Flash Deal: 40% off Electronics',
    '🌟 New: Summer Collection',
    '💎 Premium: Designer Accessories',
    '🎯 Top Rated: Smart Home Devices',
    '🚀 New Brand: TechPro Launch',
];

const tabProducts = computed(() => {
    if (!props.featuredProducts.length) { return []; }
    if (productTab.value === 'sale') {
        return props.featuredProducts.filter(p => p.sale_price_cents).slice(0, 8);
    }
    return props.featuredProducts.slice(0, 8);
});
</script>

<template>
    <Head title="Home" />

    <component :is="Layout">
        <!-- ========================================================
             STOREFRONT BANNER (when tenant has set one)
             ======================================================== -->
        <div v-if="storefront?.banner_path" class="relative w-full overflow-hidden" style="max-height: 280px;">
            <img :src="storefront.banner_path" :alt="storefront.name" class="w-full object-cover" style="max-height: 280px;" />
            <div class="absolute inset-0 bg-gradient-to-t from-navy-950/80 to-transparent" />
            <div v-if="storefront.description" class="absolute bottom-0 left-0 right-0 p-6">
                <p class="max-w-2xl text-sm text-white/90">{{ storefront.description }}</p>
            </div>
        </div>

        <!-- ========================================================
             HERO SECTION
             ======================================================== -->
        <section class="relative overflow-hidden bg-navy-950">
            <!-- Animated gradient orbs -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute -top-40 -right-40 h-96 w-96 rounded-full bg-brand-600/20 blur-3xl animate-float"></div>
                <div class="absolute -bottom-40 -left-40 h-96 w-96 rounded-full bg-accent-600/15 blur-3xl" style="animation: float 4s ease-in-out 1s infinite;"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-64 w-64 rounded-full bg-brand-500/10 blur-3xl" style="animation: float 5s ease-in-out 0.5s infinite;"></div>
            </div>

            <!-- Subtle grid overlay -->
            <div class="absolute inset-0 opacity-[0.04]"
                style="background-image: linear-gradient(rgba(255,255,255,.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.6) 1px, transparent 1px); background-size: 60px 60px;">
            </div>

            <div class="relative mx-auto max-w-7xl px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <!-- Left: Text + CTA -->
                    <div class="text-center lg:text-left">
                        <!-- Badge -->
                        <div class="inline-flex items-center gap-2 rounded-full bg-brand-500/15 border border-brand-500/30 px-4 py-1.5 mb-6">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-accent-500"></span>
                            </span>
                            <span class="text-xs font-semibold text-brand-300 tracking-wide uppercase">New arrivals every week</span>
                        </div>

                        <h1 class="text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl leading-tight">
                            Discover
                            <span class="gradient-text"> Amazing</span>
                            <br />Products
                        </h1>
                        <p class="mt-6 max-w-xl text-lg text-navy-300 leading-relaxed mx-auto lg:mx-0">
                            Shop our curated collection of high-quality products at unbeatable prices. Free shipping on orders over $50.
                        </p>

                        <div class="mt-10 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                            <Link
                                :href="localePath('/products')"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 hover:bg-brand-400 px-8 py-3.5 text-base font-semibold text-white shadow-lg shadow-brand-500/25 transition-all hover:shadow-brand-500/40 hover:-translate-y-0.5"
                            >
                                Shop Now
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </Link>
                            <Link
                                v-if="!isAuthenticated"
                                :href="localePath('/register')"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl border border-navy-700 hover:border-navy-600 bg-navy-800/50 hover:bg-navy-800 px-8 py-3.5 text-base font-semibold text-white transition-all"
                            >
                                Create Account
                            </Link>
                        </div>

                        <!-- Stats row -->
                        <div class="mt-12 grid grid-cols-2 sm:grid-cols-4 gap-6">
                            <div v-for="stat in stats" :key="stat.label" class="text-center lg:text-left">
                                <div class="text-2xl font-bold text-white">{{ stat.value }}</div>
                                <div class="mt-0.5 text-xs text-navy-400">{{ stat.label }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Floating bento stat cards -->
                    <div class="hidden lg:grid grid-cols-2 gap-4">
                        <!-- Card 1: Revenue -->
                        <div class="bento col-span-2 glass border-navy-800/60 p-5">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-semibold text-navy-400 uppercase tracking-wider">Today's Orders</span>
                                <span class="flex items-center gap-1 text-xs text-accent-400 font-medium">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                                    </svg>
                                    +12.5%
                                </span>
                            </div>
                            <!-- Mini bar chart -->
                            <div class="flex items-end gap-1 h-12">
                                <div v-for="(h, i) in [40, 65, 45, 80, 55, 90, 70]" :key="i"
                                    class="flex-1 rounded-sm bg-brand-500/30 transition-all"
                                    :style="`height: ${h}%`"
                                    :class="i === 6 ? 'bg-brand-500' : ''"
                                ></div>
                            </div>
                        </div>

                        <!-- Card 2: New arrival -->
                        <div class="bento glass border-navy-800/60 p-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent-500/20 mb-3">
                                <svg class="h-5 w-5 text-accent-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                </svg>
                            </div>
                            <div class="text-lg font-bold text-white">New Arrivals</div>
                            <div class="text-xs text-navy-400 mt-1">48 products added</div>
                        </div>

                        <!-- Card 3: Rating -->
                        <div class="bento glass border-navy-800/60 p-4">
                            <div class="flex items-center gap-1 mb-2">
                                <svg v-for="i in 5" :key="i" class="h-4 w-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            </div>
                            <div class="text-2xl font-bold text-white">4.9</div>
                            <div class="text-xs text-navy-400 mt-0.5">50K+ reviews</div>
                        </div>

                        <!-- Card 4: Shipping badge -->
                        <div class="bento col-span-2 glass border-navy-800/60 p-4 flex items-center gap-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-500/20">
                                <svg class="h-5 w-5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-white">Free Shipping</div>
                                <div class="text-xs text-navy-400">On all orders over $50 · Fast delivery guaranteed</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom fade -->
            <div class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-white dark:from-slate-50 to-transparent pointer-events-none dark:hidden"></div>
            <div class="hidden dark:block absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-navy-950 to-transparent pointer-events-none"></div>
        </section>

        <!-- ========================================================
             TRENDING TICKER
             ======================================================== -->
        <div class="overflow-hidden bg-navy-900 border-y border-navy-800 py-2.5">
            <div class="animate-marquee flex items-center gap-10">
                <span
                    v-for="(item, i) in [...trendingItems, ...trendingItems]"
                    :key="i"
                    class="whitespace-nowrap text-sm font-medium text-navy-300 shrink-0"
                >
                    {{ item }}
                    <span class="mx-4 text-navy-700">•</span>
                </span>
            </div>
        </div>

        <!-- ========================================================
             BENEFITS BENTO SECTION
             ======================================================== -->
        <section class="py-12 bg-white dark:bg-navy-950 border-b border-slate-100 dark:border-navy-900">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
                    <div
                        v-for="benefit in benefits"
                        :key="benefit.title"
                        class="bento flex items-center gap-3 rounded-xl border border-slate-100 dark:border-navy-800 bg-white dark:bg-navy-900/50 p-4 shadow-sm"
                    >
                        <div :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-xl', benefit.color]">
                            <svg v-if="benefit.icon === 'truck'" :class="['h-5 w-5', benefit.iconColor]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                            </svg>
                            <svg v-else-if="benefit.icon === 'shield'" :class="['h-5 w-5', benefit.iconColor]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                            <svg v-else-if="benefit.icon === 'refresh'" :class="['h-5 w-5', benefit.iconColor]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <svg v-else :class="['h-5 w-5', benefit.iconColor]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ benefit.title }}</h3>
                            <p class="text-xs text-slate-500 dark:text-navy-400">{{ benefit.description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================
             FLASH SALES
             ======================================================== -->
        <section v-if="flashSales.length > 0" class="py-12 sm:py-16 bg-white dark:bg-navy-950">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div v-for="sale in flashSales" :key="sale.id" class="mb-10 last:mb-0">
                    <!-- Sale header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">⚡</span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full bg-red-500 px-2.5 py-0.5 text-xs font-bold text-white uppercase tracking-wide">
                                        {{ discountLabel(sale) }}
                                    </span>
                                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ sale.name }}</h2>
                                </div>
                                <p class="text-sm text-slate-500 dark:text-navy-400 mt-0.5">Limited-time deal — grab it before it's gone</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-2">
                                <span class="text-xs font-medium text-red-600 dark:text-red-400 uppercase tracking-wide">Ends in</span>
                                <span class="text-lg font-mono font-bold text-red-600 dark:text-red-400 tabular-nums">
                                    {{ formatFlashCountdown(flashCountdowns[sale.id] ?? 0) }}
                                </span>
                            </div>
                            <Link
                                :href="localePath('/flash-sales')"
                                class="text-sm font-semibold text-brand-600 dark:text-brand-400 hover:text-brand-500 transition-colors whitespace-nowrap"
                            >
                                See all →
                            </Link>
                        </div>
                    </div>

                    <!-- Product strip -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <Link
                            v-for="product in sale.products"
                            :key="product.id"
                            :href="localePath(`/products/${product.slug}`)"
                            class="group relative rounded-2xl border border-slate-100 dark:border-navy-800 bg-white dark:bg-navy-900/60 overflow-hidden hover:shadow-md transition-all"
                        >
                            <!-- Discount badge -->
                            <div class="absolute top-2 left-2 rounded-full bg-red-500 px-2 py-0.5 text-xs font-bold text-white z-10">
                                {{ discountLabel(sale) }}
                            </div>

                            <!-- Sold out overlay -->
                            <div
                                v-if="!product.in_stock"
                                class="absolute inset-0 bg-white/60 dark:bg-black/60 flex items-center justify-center z-20 rounded-2xl"
                            >
                                <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Sold Out</span>
                            </div>

                            <!-- Image -->
                            <div class="aspect-square bg-slate-100 dark:bg-navy-800 overflow-hidden">
                                <img
                                    v-if="product.image"
                                    :src="product.image"
                                    :alt="product.name"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center text-slate-300 dark:text-navy-600">
                                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M13.5 12h.008v.008H13.5V12zm0 0H9m4.06-7.19l-4.125-4.125a1.802 1.802 0 00-2.557 0L3 8.25m0 0v11.25A2.25 2.25 0 005.25 21.75h13.5A2.25 2.25 0 0021 19.5V8.25m-18 0h18" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Info -->
                            <div class="p-3">
                                <p class="text-sm font-medium text-slate-900 dark:text-white line-clamp-2">{{ product.name }}</p>
                                <div class="mt-1.5 flex items-baseline gap-2">
                                    <span class="text-base font-bold text-red-600 dark:text-red-400">
                                        {{ flashPrice(product.discounted_price_cents) }}
                                    </span>
                                    <span class="text-xs text-slate-400 line-through">
                                        {{ flashPrice(product.price_cents) }}
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================
             PRODUCTS (TABBED)
             ======================================================== -->
        <section v-if="featuredProducts.length > 0" class="py-16 sm:py-24 bg-white dark:bg-navy-950">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Header row -->
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-6 mb-10">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-brand-500 dark:text-brand-400 mb-2">Curated Collection</p>
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">
                            Shop Products
                        </h2>
                    </div>

                    <!-- Tabs -->
                    <div class="flex items-center gap-1 rounded-xl bg-slate-100 dark:bg-navy-900/60 p-1 self-start sm:self-auto">
                        <button
                            v-for="tab in [
                                { key: 'featured', label: 'Featured' },
                                { key: 'new', label: 'New Arrivals' },
                                { key: 'sale', label: 'On Sale' },
                            ]"
                            :key="tab.key"
                            class="relative px-4 py-1.5 rounded-lg text-sm font-medium transition-all"
                            :class="productTab === tab.key
                                ? 'bg-white dark:bg-navy-800 text-slate-900 dark:text-white shadow-sm'
                                : 'text-slate-500 dark:text-navy-400 hover:text-slate-700 dark:hover:text-navy-200'"
                            @click="productTab = tab.key as typeof productTab"
                        >
                            {{ tab.label }}
                            <Badge
                                v-if="tab.key === 'sale'"
                                variant="danger"
                                size="xs"
                                class="ml-1.5"
                            >
                                Hot
                            </Badge>
                        </button>
                    </div>
                </div>

                <!-- Product grid with tab transition -->
                <Transition
                    mode="out-in"
                    enter-active-class="duration-200 ease-out"
                    enter-from-class="opacity-0 translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="duration-100 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        :key="productTab"
                        class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                    >
                        <template v-if="tabProducts.length > 0">
                            <ProductCard
                                v-for="product in tabProducts"
                                :key="product.id"
                                :product="product"
                            />
                        </template>
                        <template v-else>
                            <!-- Empty tab state -->
                            <div class="col-span-full py-16 text-center">
                                <p class="text-slate-400 dark:text-navy-500 text-sm">No products in this category yet.</p>
                                <button
                                    class="mt-3 text-sm font-medium text-brand-500 hover:text-brand-400 transition-colors"
                                    @click="productTab = 'featured'"
                                >
                                    View featured instead
                                </button>
                            </div>
                        </template>
                    </div>
                </Transition>

                <!-- View all CTA -->
                <div class="mt-10 flex justify-center">
                    <Link
                        :href="localePath(productTab === 'sale' ? '/products?sale=1' : productTab === 'new' ? '/products?sort=newest' : '/products?featured=1')"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-navy-700 bg-white dark:bg-navy-900/50 px-6 py-2.5 text-sm font-semibold text-slate-700 dark:text-navy-200 hover:border-brand-300 dark:hover:border-brand-700 hover:text-brand-600 dark:hover:text-brand-400 shadow-sm transition-all"
                    >
                        View all products
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </Link>
                </div>
            </div>
        </section>

        <!-- ========================================================
             CATEGORIES
             ======================================================== -->
        <section v-if="categories.length > 0" class="py-16 sm:py-24 bg-slate-50 dark:bg-navy-900/50">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <p class="text-xs font-semibold uppercase tracking-widest text-brand-500 dark:text-brand-400 mb-2">Browse by</p>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">
                        Shop by Category
                    </h2>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 lg:gap-4">
                    <Link
                        v-for="category in categories"
                        :key="category.id"
                        :href="localePath(`/products?category=${category.slug}`)"
                        class="group bento flex flex-col items-center justify-center rounded-2xl bg-white dark:bg-navy-800/60 p-6 border border-slate-100 dark:border-navy-700/50 hover:border-brand-200 dark:hover:border-brand-800/50 shadow-sm hover:shadow-md transition-all duration-200"
                    >
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 dark:bg-brand-900/30 group-hover:bg-brand-100 dark:group-hover:bg-brand-900/50 transition-colors mb-3">
                            <svg class="h-7 w-7 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors text-center">
                            {{ category.name }}
                        </h3>
                    </Link>
                </div>
            </div>
        </section>

        <!-- ========================================================
             CTA (shown when no featured products)
             ======================================================== -->
        <section v-if="featuredProducts.length === 0" class="py-16 sm:py-24 bg-white dark:bg-navy-950">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-3xl bg-navy-950 dark:bg-navy-900 border border-navy-800 px-6 py-16 sm:px-12 sm:py-20 text-center">
                    <div class="absolute inset-0 overflow-hidden pointer-events-none">
                        <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-brand-600/20 blur-3xl"></div>
                        <div class="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-accent-600/15 blur-3xl"></div>
                    </div>
                    <div class="relative">
                        <h2 class="text-2xl font-bold text-white sm:text-3xl">Ready to explore?</h2>
                        <p class="mx-auto mt-4 max-w-xl text-navy-300">
                            Browse our complete collection and find exactly what you're looking for.
                        </p>
                        <Link
                            :href="localePath('/products')"
                            class="mt-8 inline-flex items-center gap-2 rounded-xl bg-brand-500 hover:bg-brand-400 px-8 py-3.5 text-base font-semibold text-white shadow-lg shadow-brand-500/25 transition-all hover:shadow-brand-500/40"
                        >
                            Browse Products
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================
             NEWSLETTER
             ======================================================== -->
        <section class="py-16 sm:py-24 bg-navy-950 dark:bg-navy-950">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-3xl border border-navy-800/60 bg-navy-900/50 px-6 py-12 sm:px-12 text-center">
                    <div class="absolute inset-0 overflow-hidden pointer-events-none">
                        <div class="absolute -top-12 right-1/4 h-48 w-48 rounded-full bg-brand-600/15 blur-3xl"></div>
                        <div class="absolute -bottom-12 left-1/4 h-48 w-48 rounded-full bg-accent-600/10 blur-3xl"></div>
                    </div>
                    <div class="relative">
                        <h2 class="text-2xl font-bold text-white sm:text-3xl">Stay in the loop</h2>
                        <p class="mx-auto mt-4 max-w-xl text-navy-300">
                            Subscribe for exclusive deals, new arrivals, and special offers.
                        </p>

                        <Transition
                            enter-active-class="duration-300 ease-out"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="duration-200 ease-in"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95"
                            mode="out-in"
                        >
                            <div v-if="isSubscribed" class="mt-8">
                                <div class="inline-flex items-center gap-2 rounded-full bg-accent-500/20 border border-accent-500/30 px-5 py-2.5 text-accent-300 font-medium">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    Thanks for subscribing!
                                </div>
                            </div>
                            <form v-else @submit="handleSubscribe" class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                                <input
                                    v-model="email"
                                    type="email"
                                    required
                                    placeholder="Enter your email"
                                    class="w-full sm:w-80 rounded-xl border border-navy-700 bg-navy-800/60 px-4 py-3 text-white placeholder-navy-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-colors"
                                />
                                <button
                                    type="submit"
                                    class="w-full sm:w-auto rounded-xl bg-brand-500 hover:bg-brand-400 px-6 py-3 font-semibold text-white transition-all shadow-lg shadow-brand-500/20"
                                >
                                    Subscribe
                                </button>
                            </form>
                        </Transition>

                        <p class="mt-4 text-xs text-navy-500">
                            We respect your privacy. Unsubscribe at any time.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </component>
</template>
