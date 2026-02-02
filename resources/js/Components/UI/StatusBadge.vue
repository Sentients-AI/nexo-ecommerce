<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    status: string;
    type?: 'order' | 'payment' | 'refund';
    size?: 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
    type: 'order',
    size: 'md',
});

const statusConfig: Record<string, Record<string, { bg: string; text: string; dot: string }>> = {
    order: {
        pending: {
            bg: 'bg-yellow-100 dark:bg-yellow-900/50',
            text: 'text-yellow-800 dark:text-yellow-200',
            dot: 'bg-yellow-400',
        },
        processing: {
            bg: 'bg-blue-100 dark:bg-blue-900/50',
            text: 'text-blue-800 dark:text-blue-200',
            dot: 'bg-blue-400',
        },
        confirmed: {
            bg: 'bg-indigo-100 dark:bg-indigo-900/50',
            text: 'text-indigo-800 dark:text-indigo-200',
            dot: 'bg-indigo-400',
        },
        shipped: {
            bg: 'bg-purple-100 dark:bg-purple-900/50',
            text: 'text-purple-800 dark:text-purple-200',
            dot: 'bg-purple-400',
        },
        delivered: {
            bg: 'bg-green-100 dark:bg-green-900/50',
            text: 'text-green-800 dark:text-green-200',
            dot: 'bg-green-400',
        },
        completed: {
            bg: 'bg-green-100 dark:bg-green-900/50',
            text: 'text-green-800 dark:text-green-200',
            dot: 'bg-green-400',
        },
        cancelled: {
            bg: 'bg-red-100 dark:bg-red-900/50',
            text: 'text-red-800 dark:text-red-200',
            dot: 'bg-red-400',
        },
        refunded: {
            bg: 'bg-gray-100 dark:bg-gray-700',
            text: 'text-gray-800 dark:text-gray-200',
            dot: 'bg-gray-400',
        },
    },
    payment: {
        pending: {
            bg: 'bg-yellow-100 dark:bg-yellow-900/50',
            text: 'text-yellow-800 dark:text-yellow-200',
            dot: 'bg-yellow-400',
        },
        requires_payment_method: {
            bg: 'bg-orange-100 dark:bg-orange-900/50',
            text: 'text-orange-800 dark:text-orange-200',
            dot: 'bg-orange-400',
        },
        requires_confirmation: {
            bg: 'bg-blue-100 dark:bg-blue-900/50',
            text: 'text-blue-800 dark:text-blue-200',
            dot: 'bg-blue-400',
        },
        requires_action: {
            bg: 'bg-purple-100 dark:bg-purple-900/50',
            text: 'text-purple-800 dark:text-purple-200',
            dot: 'bg-purple-400',
        },
        processing: {
            bg: 'bg-blue-100 dark:bg-blue-900/50',
            text: 'text-blue-800 dark:text-blue-200',
            dot: 'bg-blue-400',
        },
        succeeded: {
            bg: 'bg-green-100 dark:bg-green-900/50',
            text: 'text-green-800 dark:text-green-200',
            dot: 'bg-green-400',
        },
        failed: {
            bg: 'bg-red-100 dark:bg-red-900/50',
            text: 'text-red-800 dark:text-red-200',
            dot: 'bg-red-400',
        },
        cancelled: {
            bg: 'bg-gray-100 dark:bg-gray-700',
            text: 'text-gray-800 dark:text-gray-200',
            dot: 'bg-gray-400',
        },
    },
    refund: {
        requested: {
            bg: 'bg-yellow-100 dark:bg-yellow-900/50',
            text: 'text-yellow-800 dark:text-yellow-200',
            dot: 'bg-yellow-400',
        },
        pending: {
            bg: 'bg-yellow-100 dark:bg-yellow-900/50',
            text: 'text-yellow-800 dark:text-yellow-200',
            dot: 'bg-yellow-400',
        },
        approved: {
            bg: 'bg-blue-100 dark:bg-blue-900/50',
            text: 'text-blue-800 dark:text-blue-200',
            dot: 'bg-blue-400',
        },
        processing: {
            bg: 'bg-indigo-100 dark:bg-indigo-900/50',
            text: 'text-indigo-800 dark:text-indigo-200',
            dot: 'bg-indigo-400',
        },
        succeeded: {
            bg: 'bg-green-100 dark:bg-green-900/50',
            text: 'text-green-800 dark:text-green-200',
            dot: 'bg-green-400',
        },
        completed: {
            bg: 'bg-green-100 dark:bg-green-900/50',
            text: 'text-green-800 dark:text-green-200',
            dot: 'bg-green-400',
        },
        failed: {
            bg: 'bg-red-100 dark:bg-red-900/50',
            text: 'text-red-800 dark:text-red-200',
            dot: 'bg-red-400',
        },
        rejected: {
            bg: 'bg-red-100 dark:bg-red-900/50',
            text: 'text-red-800 dark:text-red-200',
            dot: 'bg-red-400',
        },
    },
};

const defaultConfig = {
    bg: 'bg-gray-100 dark:bg-gray-700',
    text: 'text-gray-800 dark:text-gray-200',
    dot: 'bg-gray-400',
};

const config = computed(() => {
    const normalizedStatus = props.status.toLowerCase().replace(/\s+/g, '_');
    return statusConfig[props.type]?.[normalizedStatus] ?? defaultConfig;
});

const sizeClasses = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-sm',
};

const dotSizeClasses = {
    sm: 'h-1.5 w-1.5',
    md: 'h-2 w-2',
};

function formatStatus(status: string): string {
    return status
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (l) => l.toUpperCase());
}
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full font-medium"
        :class="[config.bg, config.text, sizeClasses[size]]"
    >
        <span
            class="rounded-full"
            :class="[config.dot, dotSizeClasses[size]]"
        />
        {{ formatStatus(status) }}
    </span>
</template>
