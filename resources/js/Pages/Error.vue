<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { useLocale } from '@/Composables/useLocale';

interface Props {
    statusCode: number;
    message?: string;
    error?: string;
}

const props = defineProps<Props>();

const { t, localePath } = useLocale();

const errorData = computed(() => {
    const code = props.statusCode;
    
    const errors: Record<number, { title: string; description: string; icon: string }> = {
        403: {
            title: 'Access Forbidden',
            description: "You don't have permission to access this page.",
            icon: 'shield',
        },
        404: {
            title: 'Page Not Found',
            description: "The page you're looking for doesn't exist or has been moved.",
            icon: 'search',
        },
        419: {
            title: 'Page Expired',
            description: 'Your session has expired. Please refresh and try again.',
            icon: 'clock',
        },
        429: {
            title: 'Too Many Requests',
            description: 'You are making too many requests. Please slow down.',
            icon: 'alert',
        },
        500: {
            title: 'Server Error',
            description: 'Something went wrong on our end. Please try again later.',
            icon: 'server',
        },
        503: {
            title: 'Service Unavailable',
            description: 'The service is temporarily unavailable. Please check back soon.',
            icon: 'wrench',
        },
    };

    return errors[code] || {
        title: 'Something Went Wrong',
        description: props.message || props.error || 'An unexpected error occurred.',
        icon: 'alert',
    };
});

const displayMessage = computed(() => {
    if (props.message) { return props.message; }
    if (props.error) { return props.error; }
    return errorData.value.description;
});
</script>

<template>
    <Head :title="`${statusCode} - ${errorData.title}`" />

    <GuestLayout>
        <div class="min-h-[70vh] flex items-center justify-center px-4 py-16 bg-white dark:bg-navy-950">
            <div class="text-center max-w-lg mx-auto">
                <!-- Animated gradient orbs -->
                <div class="relative mx-auto w-32 h-32 mb-8">
                    <div class="absolute inset-0 rounded-full bg-brand-500/20 animate-pulse" />
                    <div class="absolute inset-4 rounded-full bg-brand-500/30 animate-pulse" style="animation-delay: 0.2s;" />
                    <div class="absolute inset-0 flex items-center justify-center">
                        <!-- Error code -->
                        <span class="text-5xl font-bold text-brand-500 dark:text-brand-400">{{ statusCode }}</span>
                    </div>
                </div>

                <!-- Icon -->
                <div class="flex justify-center mb-6">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-navy-100 dark:bg-navy-900">
                        <!-- Shield icon for 403 -->
                        <svg v-if="errorData.icon === 'shield'" class="h-8 w-8 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                        <!-- Search icon for 404 -->
                        <svg v-else-if="errorData.icon === 'search'" class="h-8 w-8 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <!-- Clock icon for 419 -->
                        <svg v-else-if="errorData.icon === 'clock'" class="h-8 w-8 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Alert icon for 429/default -->
                        <svg v-else-if="errorData.icon === 'alert'" class="h-8 w-8 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <!-- Server icon for 500 -->
                        <svg v-else-if="errorData.icon === 'server'" class="h-8 w-8 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        <!-- Wrench icon for 503 -->
                        <svg v-else class="h-8 w-8 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" />
                        </svg>
                    </div>
                </div>

                <!-- Title -->
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-3">
                    {{ errorData.title }}
                </h1>

                <!-- Description -->
                <p class="text-slate-500 dark:text-navy-400 mb-8 leading-relaxed">
                    {{ displayMessage }}
                </p>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Link
                        :href="localePath('/')"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 hover:bg-brand-400 px-6 py-3 text-sm font-semibold text-white shadow-sm transition-all hover:shadow-brand-500/30"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        Go Home
                    </Link>
                    <button
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-navy-700 bg-white dark:bg-navy-900 hover:bg-slate-50 dark:hover:bg-navy-800 px-6 py-3 text-sm font-semibold text-slate-700 dark:text-navy-200 transition-colors"
                        @click="history.back()"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                        </svg>
                        Go Back
                    </button>
                </div>

                <!-- Support link -->
                <p class="mt-8 text-sm text-slate-400 dark:text-navy-500">
                    Need help?
                    <a href="mailto:support@example.com" class="text-brand-500 hover:text-brand-400 font-medium">
                        Contact support
                    </a>
                </p>
            </div>
        </div>
    </GuestLayout>
</template>
