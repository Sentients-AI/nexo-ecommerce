<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useLocale } from '@/Composables/useLocale';
import { useWishlist } from '@/Composables/useWishlist';

interface Props {
    show: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
}>();

const page = usePage();
const isAuthenticated = ref(page.props.auth?.user !== null);
const { t, localePath } = useLocale();
const { count: wishlistCount } = useWishlist();

function close() {
    emit('close');
}

function handleEscape(e: KeyboardEvent) {
    if (e.key === 'Escape' && props.show) {
        close();
    }
}

watch(() => props.show, (show) => {
    if (show) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});

onMounted(() => {
    document.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscape);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition
            enter-active-class="duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-show="show"
                class="fixed inset-0 z-40 bg-navy-950/60 backdrop-blur-sm"
                @click="close"
            />
        </Transition>

        <!-- Slide-out panel -->
        <Transition
            enter-active-class="duration-300 ease-out"
            enter-from-class="-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full"
        >
            <div
                v-show="show"
                class="fixed inset-y-0 left-0 z-50 w-full max-w-xs bg-white dark:bg-navy-900 shadow-xl shadow-navy-950/20"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-5 border-b border-slate-100 dark:border-navy-800">
                    <Link :href="localePath('/')" class="text-xl font-bold text-slate-900 dark:text-white" @click="close">
                        Store
                    </Link>
                    <button
                        @click="close"
                        class="rounded-lg p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-navy-800 dark:hover:text-slate-300 transition-colors"
                    >
                        <span class="sr-only">Close menu</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Navigation -->
                <nav class="px-4 py-6 space-y-1">
                    <Link
                        :href="localePath('/')"
                        class="block px-3 py-2.5 rounded-xl text-base font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 hover:text-slate-900 dark:hover:text-white transition-colors"
                        @click="close"
                    >
                        {{ t('nav.home') }}
                    </Link>
                    <Link
                        :href="localePath('/products')"
                        class="block px-3 py-2.5 rounded-xl text-base font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 hover:text-slate-900 dark:hover:text-white transition-colors"
                        @click="close"
                    >
                        {{ t('nav.products') }}
                    </Link>
                    <Link
                        v-if="isAuthenticated"
                        :href="localePath('/orders')"
                        class="block px-3 py-2.5 rounded-xl text-base font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 hover:text-slate-900 dark:hover:text-white transition-colors"
                        @click="close"
                    >
                        {{ t('nav.orders') }}
                    </Link>
                    <Link
                        :href="localePath('/wishlist')"
                        class="flex items-center justify-between px-3 py-2.5 rounded-xl text-base font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 hover:text-slate-900 dark:hover:text-white transition-colors"
                        @click="close"
                    >
                        {{ t('nav.wishlist') }}
                        <span
                            v-if="wishlistCount > 0"
                            class="flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-900/50 px-1.5 text-xs font-medium text-brand-600 dark:text-brand-400"
                        >
                            {{ wishlistCount }}
                        </span>
                    </Link>
                    <Link
                        :href="localePath('/cart')"
                        class="block px-3 py-2.5 rounded-xl text-base font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 hover:text-slate-900 dark:hover:text-white transition-colors"
                        @click="close"
                    >
                        {{ t('nav.cart') }}
                    </Link>
                </nav>

                <!-- Auth links -->
                <div class="absolute bottom-0 left-0 right-0 border-t border-slate-100 dark:border-navy-800 p-4 space-y-2">
                    <template v-if="isAuthenticated">
                        <div class="px-3 py-2 text-sm text-slate-500 dark:text-slate-400">
                            Signed in as <span class="font-medium text-slate-900 dark:text-white">{{ page.props.auth?.user?.name }}</span>
                        </div>
                        <Link
                            :href="localePath('/logout')"
                            method="post"
                            as="button"
                            class="block w-full text-left px-3 py-2.5 rounded-xl text-base font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                            @click="close"
                        >
                            {{ t('nav.logout') }}
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="localePath('/login')"
                            class="block px-3 py-2.5 rounded-xl text-base font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                            @click="close"
                        >
                            {{ t('nav.sign_in') }}
                        </Link>
                        <Link
                            :href="localePath('/register')"
                            class="block px-3 py-2.5 rounded-xl text-base font-semibold text-white bg-brand-500 hover:bg-brand-400 text-center shadow-sm shadow-brand-500/25 transition-colors"
                            @click="close"
                        >
                            {{ t('nav.sign_up') }}
                        </Link>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
