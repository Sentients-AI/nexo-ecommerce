<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

interface Props {
    show: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
}>();

const page = usePage();
const isAuthenticated = ref(page.props.auth?.user !== null);

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
                class="fixed inset-0 z-40 bg-gray-600/50 dark:bg-gray-900/80"
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
                class="fixed inset-y-0 left-0 z-50 w-full max-w-xs bg-white dark:bg-gray-800 shadow-xl"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-5 border-b border-gray-200 dark:border-gray-700">
                    <Link href="/" class="text-xl font-bold text-gray-900 dark:text-white" @click="close">
                        Store
                    </Link>
                    <button
                        @click="close"
                        class="rounded-md p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                    >
                        <span class="sr-only">Close menu</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Navigation -->
                <nav class="px-4 py-6 space-y-2">
                    <Link
                        href="/"
                        class="block px-3 py-2 rounded-md text-base font-medium text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700"
                        @click="close"
                    >
                        Home
                    </Link>
                    <Link
                        href="/products"
                        class="block px-3 py-2 rounded-md text-base font-medium text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700"
                        @click="close"
                    >
                        Products
                    </Link>
                    <Link
                        v-if="isAuthenticated"
                        href="/orders"
                        class="block px-3 py-2 rounded-md text-base font-medium text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700"
                        @click="close"
                    >
                        Orders
                    </Link>
                    <Link
                        href="/cart"
                        class="block px-3 py-2 rounded-md text-base font-medium text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700"
                        @click="close"
                    >
                        Cart
                    </Link>
                </nav>

                <!-- Auth links -->
                <div class="absolute bottom-0 left-0 right-0 border-t border-gray-200 dark:border-gray-700 p-4 space-y-2">
                    <template v-if="isAuthenticated">
                        <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                            Signed in as <span class="font-medium text-gray-900 dark:text-white">{{ page.props.auth?.user?.name }}</span>
                        </div>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="block w-full text-left px-3 py-2 rounded-md text-base font-medium text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="close"
                        >
                            Sign Out
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            href="/login"
                            class="block px-3 py-2 rounded-md text-base font-medium text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="close"
                        >
                            Sign In
                        </Link>
                        <Link
                            href="/register"
                            class="block px-3 py-2 rounded-md text-base font-medium text-white bg-indigo-600 hover:bg-indigo-500 text-center"
                            @click="close"
                        >
                            Sign Up
                        </Link>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
