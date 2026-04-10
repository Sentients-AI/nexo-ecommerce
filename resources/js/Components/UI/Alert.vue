<script setup lang="ts">
import { computed, ref } from 'vue';

interface Props {
    variant?: 'info' | 'success' | 'warning' | 'danger';
    title?: string;
    dismissible?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'info',
    dismissible: false,
});

const dismissed = ref(false);

const config = computed(() => ({
    info: {
        wrapper: 'bg-brand-50 border-brand-200 dark:bg-brand-950/40 dark:border-brand-800',
        icon: 'text-brand-500',
        title: 'text-brand-800 dark:text-brand-200',
        body:  'text-brand-700 dark:text-brand-300',
        close: 'text-brand-500 hover:text-brand-700 dark:hover:text-brand-200',
    },
    success: {
        wrapper: 'bg-accent-50 border-accent-200 dark:bg-accent-950/40 dark:border-accent-800',
        icon: 'text-accent-500',
        title: 'text-accent-800 dark:text-accent-200',
        body:  'text-accent-700 dark:text-accent-300',
        close: 'text-accent-500 hover:text-accent-700 dark:hover:text-accent-200',
    },
    warning: {
        wrapper: 'bg-amber-50 border-amber-200 dark:bg-amber-950/40 dark:border-amber-800',
        icon: 'text-amber-500',
        title: 'text-amber-800 dark:text-amber-200',
        body:  'text-amber-700 dark:text-amber-300',
        close: 'text-amber-500 hover:text-amber-700 dark:hover:text-amber-200',
    },
    danger: {
        wrapper: 'bg-red-50 border-red-200 dark:bg-red-950/40 dark:border-red-800',
        icon: 'text-red-500',
        title: 'text-red-800 dark:text-red-200',
        body:  'text-red-700 dark:text-red-300',
        close: 'text-red-500 hover:text-red-700 dark:hover:text-red-200',
    },
}[props.variant]));
</script>

<template>
    <Transition
        enter-active-class="duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
    >
        <div
            v-if="!dismissed"
            class="flex gap-3 rounded-xl border p-4 text-sm"
            :class="config.wrapper"
            role="alert"
        >
            <!-- Icon slot -->
            <div class="shrink-0 mt-0.5" :class="config.icon">
                <slot name="icon">
                    <!-- Default icons per variant -->
                    <svg v-if="variant === 'info'" class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <svg v-else-if="variant === 'success'" class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <svg v-else-if="variant === 'warning'" class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <svg v-else-if="variant === 'danger'" class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </slot>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <p v-if="title" class="font-semibold mb-0.5" :class="config.title">{{ title }}</p>
                <div :class="config.body">
                    <slot />
                </div>
            </div>

            <!-- Dismiss button -->
            <button
                v-if="dismissible"
                type="button"
                class="shrink-0 rounded-md p-0.5 transition-colors"
                :class="config.close"
                aria-label="Dismiss"
                @click="dismissed = true"
            >
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
    </Transition>
</template>
