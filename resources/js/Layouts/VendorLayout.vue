<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useLocale } from '@/Composables/useLocale';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { localePath } = useLocale();

const sidebarOpen = ref(false);

const navigation = [
    {
        label: 'Command Center',
        href: '/vendor/dashboard',
        icon: 'dashboard',
    },
    {
        label: 'Live Orders',
        href: '/vendor/orders',
        icon: 'orders',
    },
    {
        label: 'Products',
        href: '/vendor/products',
        icon: 'products',
    },
    {
        label: 'Inventory',
        href: '/vendor/inventory',
        icon: 'inventory',
    },
    {
        label: 'Customers',
        href: '/vendor/customers',
        icon: 'customers',
    },
    {
        label: 'Analytics',
        href: '/vendor/analytics',
        icon: 'analytics',
    },
    {
        label: 'Promotions',
        href: '/vendor/promotions',
        icon: 'promotions',
    },
    {
        label: 'Settings',
        href: '/vendor/settings',
        icon: 'settings',
    },
];

function isActive(href: string): boolean {
    return page.url.startsWith(href);
}

function logout(): void {
    router.post(localePath('/logout'));
}
</script>

<template>
    <div class="min-h-screen flex bg-navy-950 text-white">
        <!-- ── MOBILE SIDEBAR OVERLAY ── -->
        <Teleport to="body">
            <Transition
                enter-active-class="duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="sidebarOpen"
                    class="fixed inset-0 z-50 bg-navy-950/80 backdrop-blur-sm lg:hidden"
                    @click="sidebarOpen = false"
                />
            </Transition>
        </Teleport>

        <!-- ── SIDEBAR ── -->
        <aside
            class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col border-r border-navy-800/60 bg-navy-950 transition-transform duration-300 lg:static lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <!-- Logo -->
            <div class="flex h-16 shrink-0 items-center gap-3 px-5 border-b border-navy-800/60">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 shadow-sm shadow-brand-500/30">
                    <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-bold text-white tracking-tight">Vendor</div>
                    <div class="text-xs text-navy-400">Command Center</div>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-150"
                    :class="isActive(item.href)
                        ? 'bg-brand-500/15 text-brand-300 border border-brand-500/20'
                        : 'text-navy-400 hover:text-white hover:bg-navy-800/70'"
                    @click="sidebarOpen = false"
                >
                    <!-- Dashboard icon -->
                    <svg v-if="item.icon === 'dashboard'" class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3h7.5v7.5h-7.5V3zM3.75 13.5h7.5V21h-7.5v-7.5zM13.5 3H21v7.5H13.5V3zM13.5 13.5H21V21H13.5v-7.5z" />
                    </svg>
                    <!-- Orders icon -->
                    <svg v-else-if="item.icon === 'orders'" class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                    </svg>
                    <!-- Products icon -->
                    <svg v-else-if="item.icon === 'products'" class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                    <!-- Inventory icon -->
                    <svg v-else-if="item.icon === 'inventory'" class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                    </svg>
                    <!-- Customers icon -->
                    <svg v-else-if="item.icon === 'customers'" class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                    <!-- Analytics icon -->
                    <svg v-else-if="item.icon === 'analytics'" class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                    <!-- Promotions icon -->
                    <svg v-else-if="item.icon === 'promotions'" class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                    </svg>
                    <!-- Settings icon -->
                    <svg v-else class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>

                    <span>{{ item.label }}</span>

                    <!-- Active indicator dot -->
                    <span v-if="isActive(item.href)" class="ml-auto h-1.5 w-1.5 rounded-full bg-brand-400"></span>
                </Link>
            </nav>

            <!-- User section -->
            <div class="shrink-0 border-t border-navy-800/60 p-3">
                <div class="flex items-center gap-3 rounded-xl p-2">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-500/20 text-brand-300 text-sm font-semibold">
                        {{ user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-white truncate">{{ user?.name }}</div>
                        <div class="text-xs text-navy-400 truncate">{{ user?.email }}</div>
                    </div>
                    <button
                        @click="logout"
                        class="p-1.5 rounded-lg text-navy-500 hover:text-white hover:bg-navy-800 transition-colors"
                        title="Sign out"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- ── MAIN AREA ── -->
        <div class="flex flex-1 flex-col min-w-0">
            <!-- Top bar -->
            <header class="flex h-16 shrink-0 items-center justify-between gap-4 border-b border-navy-800/60 bg-navy-950/80 px-4 sm:px-6 backdrop-blur-sm sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <button
                        @click="sidebarOpen = true"
                        class="lg:hidden rounded-lg p-2 text-navy-400 hover:text-white hover:bg-navy-800 transition-colors"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <!-- Breadcrumb slot -->
                    <slot name="header">
                        <h1 class="text-sm font-semibold text-white">Dashboard</h1>
                    </slot>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Live indicator -->
                    <div class="hidden sm:flex items-center gap-1.5 rounded-full bg-accent-500/15 border border-accent-500/20 px-3 py-1">
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-accent-500"></span>
                        </span>
                        <span class="text-xs font-medium text-accent-300">Live</span>
                    </div>

                    <!-- Storefront link -->
                    <Link
                        :href="localePath('/')"
                        class="flex items-center gap-1.5 rounded-lg border border-navy-700/60 bg-navy-800/40 px-3 py-1.5 text-xs font-medium text-navy-300 hover:text-white hover:bg-navy-700/60 transition-all"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                        Storefront
                    </Link>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
