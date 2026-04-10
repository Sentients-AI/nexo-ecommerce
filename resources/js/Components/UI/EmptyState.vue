<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
    theme?: 'light' | 'dark';
}

withDefaults(defineProps<Props>(), {
    theme: 'light',
});
</script>

<template>
    <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
        <!-- Icon slot -->
        <div
            class="mb-4 flex size-16 items-center justify-center rounded-2xl"
            :class="theme === 'dark'
                ? 'bg-navy-800/60 text-navy-400'
                : 'bg-slate-100 text-slate-400 dark:bg-navy-800/60 dark:text-navy-400'"
        >
            <slot name="icon">
                <svg class="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
            </slot>
        </div>

        <h3
            class="text-base font-semibold mb-1"
            :class="theme === 'dark' ? 'text-white' : 'text-slate-900 dark:text-white'"
        >
            {{ title }}
        </h3>

        <p
            v-if="description"
            class="text-sm max-w-xs"
            :class="theme === 'dark' ? 'text-navy-400' : 'text-slate-500 dark:text-navy-400'"
        >
            {{ description }}
        </p>

        <!-- Action slot -->
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
