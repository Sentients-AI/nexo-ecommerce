<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useLocale } from '@/Composables/useLocale';
import { useCart } from '@/Composables/useCart';
import { useWishlist } from '@/Composables/useWishlist';

const page = usePage();
const { localePath } = useLocale();
const { totalItems: cartCount } = useCart();
const { count: wishlistCount } = useWishlist();

const isAuthenticated = computed(() => page.props.auth?.user !== null);

interface NavItem {
    label: string;
    href: string;
    icon: string;
    badge?: number;
    authRequired?: boolean;
}

const items = computed((): NavItem[] => [
    {
        label: 'Home',
        href: localePath('/'),
        icon: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
    },
    {
        label: 'Shop',
        href: localePath('/products'),
        icon: 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z',
    },
    {
        label: 'Wishlist',
        href: localePath('/wishlist'),
        icon: 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
        badge: wishlistCount.value,
    },
    {
        label: 'Cart',
        href: localePath('/cart'),
        icon: 'M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
        badge: cartCount.value,
    },
    {
        label: 'Account',
        href: isAuthenticated.value ? localePath('/profile') : localePath('/login'),
        icon: isAuthenticated.value
            ? 'M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z'
            : 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
    },
]);

function isActive(href: string): boolean {
    return page.url === href || page.url.startsWith(href + '?');
}
</script>

<template>
    <!-- Only visible on mobile (sm:hidden) -->
    <nav class="fixed bottom-0 inset-x-0 z-40 sm:hidden border-t border-slate-200 dark:border-navy-800 bg-white/95 dark:bg-navy-950/95 backdrop-blur-sm safe-area-inset-bottom">
        <div class="grid grid-cols-5 h-16">
            <Link
                v-for="item in items"
                :key="item.label"
                :href="item.href"
                class="relative flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition-colors"
                :class="isActive(item.href)
                    ? 'text-brand-600 dark:text-brand-400'
                    : 'text-slate-500 dark:text-navy-400 hover:text-slate-800 dark:hover:text-white'"
            >
                <!-- Active indicator pill -->
                <span
                    v-if="isActive(item.href)"
                    class="absolute top-2 h-0.5 w-6 rounded-full bg-brand-500"
                />

                <div class="relative">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    <!-- Badge -->
                    <span
                        v-if="item.badge && item.badge > 0"
                        class="absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-500 px-0.5 text-[9px] font-bold text-white"
                    >
                        {{ item.badge > 99 ? '99+' : item.badge }}
                    </span>
                </div>
                {{ item.label }}
            </Link>
        </div>
    </nav>
</template>
