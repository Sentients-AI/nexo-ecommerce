<script setup lang="ts">
interface Props {
    variant?: 'default' | 'brand' | 'accent' | 'warning' | 'danger' | 'navy' | 'outline';
    size?: 'xs' | 'sm' | 'md';
    dot?: boolean;
    pill?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
    size: 'sm',
    dot: false,
    pill: true,
});

const variantClasses: Record<string, string> = {
    default:  'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    brand:    'bg-brand-100 text-brand-700 dark:bg-brand-950 dark:text-brand-300',
    accent:   'bg-accent-100 text-accent-700 dark:bg-accent-950 dark:text-accent-300',
    warning:  'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    danger:   'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    navy:     'bg-navy-800 text-navy-200 dark:bg-navy-900 dark:text-navy-300',
    outline:  'border border-slate-300 text-slate-600 dark:border-navy-600 dark:text-navy-300',
};

const dotColors: Record<string, string> = {
    default:  'bg-slate-400',
    brand:    'bg-brand-500',
    accent:   'bg-accent-500',
    warning:  'bg-amber-500',
    danger:   'bg-red-500',
    navy:     'bg-navy-400',
    outline:  'bg-slate-400',
};

const sizeClasses: Record<string, string> = {
    xs: 'px-1.5 py-0.5 text-xs',
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-sm',
};

const dotSizeClasses: Record<string, string> = {
    xs: 'size-1.5',
    sm: 'size-1.5',
    md: 'size-2',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1 font-medium"
        :class="[
            variantClasses[variant],
            sizeClasses[size],
            pill ? 'rounded-full' : 'rounded',
        ]"
    >
        <span
            v-if="dot"
            class="rounded-full shrink-0"
            :class="[dotColors[variant], dotSizeClasses[size]]"
        />
        <slot />
    </span>
</template>
