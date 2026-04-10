<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import MobileNav from '@/Components/Layout/MobileNav.vue';
import CartBadge from '@/Components/Layout/CartBadge.vue';
import NotificationBell from '@/Components/Layout/NotificationBell.vue';
import UserDropdown from '@/Components/Layout/UserDropdown.vue';
import ChatWidget from '@/Components/Chat/ChatWidget.vue';
import Alert from '@/Components/UI/Alert.vue';
import BottomNav from '@/Components/Layout/BottomNav.vue';
import { useLocale } from '@/Composables/useLocale';
import { useWishlist } from '@/Composables/useWishlist';

const page = usePage();
const flash = computed(() => page.props.flash);
const { t, localePath } = useLocale();
const { count: wishlistCount } = useWishlist();

const mobileNavOpen = ref(false);
const isScrolled = ref(false);

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
    <div class="min-h-screen flex flex-col bg-slate-50 dark:bg-navy-950">
        <!-- Navigation -->
        <nav
            class="sticky top-0 z-40 transition-all duration-300"
            :class="isScrolled
                ? 'glass-light dark:glass-dark shadow-sm'
                : 'bg-white/90 dark:bg-navy-950/90 backdrop-blur-sm'"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <!-- Left: Logo + Mobile menu -->
                    <div class="flex items-center gap-3">
                        <button
                            @click="mobileNavOpen = true"
                            class="sm:hidden rounded-lg p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 dark:text-navy-400 dark:hover:text-white dark:hover:bg-navy-800 transition-colors"
                        >
                            <span class="sr-only">Open menu</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>

                        <!-- Logo -->
                        <Link :href="localePath('/')" class="flex items-center gap-2 group">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 shadow-sm group-hover:bg-brand-400 transition-colors">
                                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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
                                :href="localePath('/orders')"
                                class="px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-navy-300 dark:hover:text-white dark:hover:bg-navy-800/70 transition-colors"
                            >
                                {{ t('nav.orders') }}
                            </Link>
                            <!-- Wishlist with badge -->
                            <Link
                                :href="localePath('/wishlist')"
                                class="relative px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-navy-300 dark:hover:text-white dark:hover:bg-navy-800/70 transition-colors"
                            >
                                {{ t('nav.wishlist') }}
                                <span
                                    v-if="wishlistCount > 0"
                                    class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-500 px-1 text-[10px] font-semibold text-white"
                                >
                                    {{ wishlistCount }}
                                </span>
                            </Link>
                        </div>
                    </div>

                    <!-- Right: Notifications + Cart + User -->
                    <div class="flex items-center gap-2">
                        <NotificationBell />
                        <CartBadge />
                        <div class="hidden sm:block sm:ml-1">
                            <UserDropdown />
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
        <footer class="bg-slate-900 dark:bg-navy-950 border-t border-slate-800/60 dark:border-navy-900 mt-auto">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Brand -->
                    <div class="lg:col-span-1">
                        <Link :href="localePath('/')" class="flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-500">
                                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                                </svg>
                            </div>
                            <span class="text-base font-bold text-white">Store</span>
                        </Link>
                        <p class="mt-4 text-sm text-slate-400 leading-relaxed max-w-xs">
                            {{ t('footer.tagline') }}
                        </p>
                    </div>

                    <!-- Shop -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ t('footer.shop') }}</h3>
                        <ul class="mt-4 space-y-3">
                            <li><Link :href="localePath('/products')" class="text-sm text-slate-400 hover:text-white transition-colors">{{ t('footer.all_products') }}</Link></li>
                            <li><Link :href="localePath('/products?featured=1')" class="text-sm text-slate-400 hover:text-white transition-colors">{{ t('footer.featured') }}</Link></li>
                        </ul>
                    </div>

                    <!-- Account -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ t('footer.account') }}</h3>
                        <ul class="mt-4 space-y-3">
                            <li><Link :href="localePath('/orders')" class="text-sm text-slate-400 hover:text-white transition-colors">{{ t('footer.my_orders') }}</Link></li>
                            <li><Link :href="localePath('/cart')" class="text-sm text-slate-400 hover:text-white transition-colors">{{ t('nav.cart') }}</Link></li>
                            <li><Link :href="localePath('/wishlist')" class="text-sm text-slate-400 hover:text-white transition-colors">{{ t('nav.wishlist') }}</Link></li>
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

                <!-- Bottom bar -->
                <div class="mt-12 pt-8 border-t border-slate-800/60 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-sm text-slate-600">
                        &copy; {{ new Date().getFullYear() }} Store. {{ t('footer.rights') }}
                    </p>
                    <div class="flex items-center gap-4">
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

        <!-- Chat widget -->
        <ChatWidget />
    </div>
</template>
