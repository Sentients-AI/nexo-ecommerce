<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import MobileNav from '@/Components/Layout/MobileNav.vue';
import CartBadge from '@/Components/Layout/CartBadge.vue';
import Alert from '@/Components/UI/Alert.vue';
import BottomNav from '@/Components/Layout/BottomNav.vue';
import { useLocale } from '@/Composables/useLocale';

const page = usePage();
const flash = computed(() => page.props.flash);
const { t, localePath } = useLocale();

const mobileNavOpen = ref(false);
const isScrolled = ref(false);
const announcementDismissed = ref(false);

const announcements = [
    '🚚 Free shipping on orders over $50',
    '✨ New arrivals added every week',
    '🔒 Secure checkout powered by Stripe',
    '↩️ Easy 30-day returns on all orders',
    '🎁 Exclusive deals for new members',
];

function handleScroll() {
    isScrolled.value = window.scrollY > 20;
}

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <div class="min-h-screen flex flex-col bg-white dark:bg-navy-950">

        <!-- Announcement bar -->
        <Transition
            enter-active-class="duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-full"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-full"
        >
            <div
                v-if="!announcementDismissed"
                class="relative overflow-hidden bg-brand-600 text-white text-xs font-medium"
            >
                <div class="relative flex overflow-hidden py-2">
                    <!-- Marquee track (duplicated for seamless loop) -->
                    <div class="animate-marquee flex items-center gap-12 shrink-0 pr-12">
                        <span
                            v-for="(item, i) in [...announcements, ...announcements]"
                            :key="i"
                            class="whitespace-nowrap"
                        >
                            {{ item }}
                        </span>
                    </div>
                </div>
                <button
                    class="absolute right-3 top-1/2 -translate-y-1/2 rounded p-0.5 hover:bg-white/20 transition-colors"
                    aria-label="Dismiss"
                    @click="announcementDismissed = true"
                >
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </Transition>

        <!-- Navigation -->
        <nav
            class="sticky top-0 z-40 transition-all duration-300"
            :class="isScrolled
                ? 'glass-light dark:glass-dark shadow-sm'
                : 'bg-white/80 dark:bg-navy-950/80 backdrop-blur-sm'"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <!-- Left: Logo + Mobile menu + Nav links -->
                    <div class="flex items-center gap-3">
                        <button
                            class="sm:hidden rounded-lg p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 dark:text-navy-400 dark:hover:text-white dark:hover:bg-navy-800 transition-colors"
                            @click="mobileNavOpen = true"
                        >
                            <span class="sr-only">Open menu</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>

                        <!-- Logo -->
                        <Link :href="localePath('/')" class="flex items-center gap-2 group">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 shadow-sm group-hover:bg-brand-400 transition-colors">
                                <svg class="h-4.5 w-4.5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                                </svg>
                            </div>
                            <span class="text-base font-bold text-slate-900 dark:text-white tracking-tight">Store</span>
                        </Link>

                        <!-- Desktop nav links -->
                        <div class="hidden sm:flex sm:items-center sm:gap-1 sm:ml-2">
                            <Link
                                :href="localePath('/products')"
                                class="px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-navy-300 dark:hover:text-white dark:hover:bg-navy-800/70 transition-colors"
                            >
                                {{ t('nav.products') }}
                            </Link>
                            <Link
                                :href="localePath('/products?sale=1')"
                                class="relative px-3 py-2 rounded-lg text-sm font-medium text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:text-rose-400 dark:hover:text-rose-300 dark:hover:bg-rose-900/20 transition-colors"
                            >
                                Sale
                                <span class="absolute -top-0.5 -right-0.5 flex size-1.5 rounded-full bg-rose-500" />
                            </Link>
                        </div>
                    </div>

                    <!-- Right: Cart + Auth -->
                    <div class="flex items-center gap-2">
                        <CartBadge />
                        <div class="hidden sm:flex sm:items-center sm:gap-2 sm:ml-2">
                            <Link
                                :href="localePath('/login')"
                                class="px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-navy-300 dark:hover:text-white dark:hover:bg-navy-800/70 transition-colors"
                            >
                                {{ t('nav.sign_in') }}
                            </Link>
                            <Link
                                :href="localePath('/register')"
                                class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-brand-500 hover:bg-brand-400 shadow-sm transition-all hover:shadow-brand-500/30 hover:shadow-md"
                            >
                                {{ t('nav.sign_up') }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Mobile navigation -->
        <MobileNav :show="mobileNavOpen" @close="mobileNavOpen = false" />

        <!-- Flash messages -->
        <div v-if="flash.success || flash.error" class="mx-auto max-w-7xl w-full px-4 sm:px-6 lg:px-8 mt-4 space-y-2">
            <Alert v-if="flash.success" variant="success" :dismissible="true">
                {{ flash.success }}
            </Alert>
            <Alert v-if="flash.error" variant="danger" :dismissible="true">
                {{ flash.error }}
            </Alert>
        </div>

        <!-- Main content -->
        <main class="flex-1 pb-16 sm:pb-0">
            <slot />
        </main>

        <!-- Mobile bottom navigation -->
        <BottomNav />

        <!-- Footer -->
        <footer class="bg-slate-900 dark:bg-navy-950 border-t border-slate-800/60 dark:border-navy-900">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Brand -->
                    <div class="lg:col-span-1">
                        <Link :href="localePath('/')" class="flex items-center gap-2 group">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-500 group-hover:bg-brand-400 transition-colors">
                                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                                </svg>
                            </div>
                            <span class="text-base font-bold text-white">Store</span>
                        </Link>
                        <p class="mt-4 text-sm text-slate-400 leading-relaxed max-w-xs">
                            {{ t('footer.tagline') }}
                        </p>
                        <!-- Social links -->
                        <div class="mt-5 flex items-center gap-3">
                            <a href="#" class="flex size-8 items-center justify-center rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors" aria-label="Twitter">
                                <svg class="size-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.736l7.73-8.835L1.254 2.25H8.08l4.26 5.632 5.905-5.632zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            </a>
                            <a href="#" class="flex size-8 items-center justify-center rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors" aria-label="Instagram">
                                <svg class="size-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            </a>
                            <a href="#" class="flex size-8 items-center justify-center rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors" aria-label="Facebook">
                                <svg class="size-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </a>
                        </div>
                    </div>

                    <!-- Shop -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ t('footer.shop') }}</h3>
                        <ul class="mt-4 space-y-3">
                            <li><Link :href="localePath('/products')" class="text-sm text-slate-400 hover:text-white transition-colors">{{ t('footer.all_products') }}</Link></li>
                            <li><Link :href="localePath('/products?featured=1')" class="text-sm text-slate-400 hover:text-white transition-colors">{{ t('footer.featured') }}</Link></li>
                            <li><Link :href="localePath('/products?sale=1')" class="text-sm text-rose-400 hover:text-rose-300 transition-colors">Sale Items</Link></li>
                        </ul>
                    </div>

                    <!-- Account -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ t('footer.account') }}</h3>
                        <ul class="mt-4 space-y-3">
                            <li><Link :href="localePath('/login')" class="text-sm text-slate-400 hover:text-white transition-colors">{{ t('nav.sign_in') }}</Link></li>
                            <li><Link :href="localePath('/register')" class="text-sm text-slate-400 hover:text-white transition-colors">{{ t('nav.sign_up') }}</Link></li>
                            <li><Link :href="localePath('/cart')" class="text-sm text-slate-400 hover:text-white transition-colors">{{ t('nav.cart') }}</Link></li>
                        </ul>
                    </div>

                    <!-- Support -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ t('footer.support') }}</h3>
                        <ul class="mt-4 space-y-3">
                            <li><span class="text-sm text-slate-500">{{ t('footer.contact') }}</span></li>
                            <li><span class="text-sm text-slate-500">{{ t('footer.shipping') }}</span></li>
                            <li><span class="text-sm text-slate-500">{{ t('footer.returns') }}</span></li>
                        </ul>
                    </div>
                </div>

                <!-- Payment icons row -->
                <div class="mt-10 pt-8 border-t border-slate-800/60 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-sm text-slate-600">
                        &copy; {{ new Date().getFullYear() }} Store. {{ t('footer.rights') }}
                    </p>
                    <div class="flex items-center gap-4">
                        <!-- Payment method badges -->
                        <div class="flex items-center gap-2">
                            <div class="rounded px-2 py-1 bg-slate-800 text-slate-400 text-[10px] font-bold tracking-wide">VISA</div>
                            <div class="rounded px-2 py-1 bg-slate-800 text-slate-400 text-[10px] font-bold tracking-wide">MC</div>
                            <div class="rounded px-2 py-1 bg-slate-800 text-slate-400 text-[10px] font-bold tracking-wide">STRIPE</div>
                        </div>
                        <div class="h-3 w-px bg-slate-700" />
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent-400 opacity-75" />
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-accent-500" />
                            </span>
                            <span class="text-xs text-slate-600">All systems operational</span>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
