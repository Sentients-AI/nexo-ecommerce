<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useLocale } from '@/Composables/useLocale';

const page = usePage();
const user = page.props.auth?.user;
const { localePath } = useLocale();

const isOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

function toggle() {
    isOpen.value = !isOpen.value;
}

function close() {
    isOpen.value = false;
}

function logout() {
    close();
    router.post(localePath('/logout'));
}

function handleClickOutside(event: MouseEvent) {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
        close();
    }
}

function handleEscape(event: KeyboardEvent) {
    if (event.key === 'Escape' && isOpen.value) {
        close();
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleEscape);
});
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <!-- Trigger -->
        <button
            @click="toggle"
            class="flex items-center gap-2 rounded-full p-1 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-navy-800 transition-colors"
        >
            <!-- Avatar -->
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-500 text-sm font-medium text-white ring-2 ring-brand-500/20">
                {{ user?.name?.charAt(0).toUpperCase() ?? 'U' }}
            </span>
            <!-- Chevron -->
            <svg
                class="h-4 w-4 transition-transform"
                :class="{ 'rotate-180': isOpen }"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <!-- Dropdown menu -->
        <Transition
            enter-active-class="duration-150 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="duration-100 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-show="isOpen"
                class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl bg-white dark:bg-navy-900 shadow-lg shadow-navy-950/10 ring-1 ring-slate-200/60 dark:ring-navy-700/60 focus:outline-none z-50"
            >
                <div class="py-1">
                    <!-- User info -->
                    <div class="px-4 py-3 border-b border-slate-100 dark:border-navy-800">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">
                            {{ user?.name }}
                        </p>
                        <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                            {{ user?.email }}
                        </p>
                    </div>

                    <!-- Menu items -->
                    <div class="py-1">
                        <Link
                            :href="localePath('/orders')"
                            class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                            @click="close"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                            </svg>
                            My Orders
                        </Link>
                    </div>

                    <!-- Sign out -->
                    <div class="border-t border-slate-100 dark:border-navy-800 py-1">
                        <button
                            @click="logout"
                            class="flex w-full items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                            </svg>
                            Sign Out
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
