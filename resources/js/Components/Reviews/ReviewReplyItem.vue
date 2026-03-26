<script setup lang="ts">
import type { ReviewReplyApiResource } from '@/types/api';

defineProps<{ reply: ReviewReplyApiResource }>();

const formatDate = (dateStr: string): string => {
    return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <div
        class="mt-3 rounded-lg p-3 text-sm"
        :class="reply.is_merchant_reply
            ? 'bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700'
            : 'bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700'"
    >
        <div class="mb-1 flex items-center gap-2">
            <span
                v-if="reply.is_merchant_reply"
                class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-amber-100 dark:bg-amber-800 text-amber-800 dark:text-amber-200"
            >
                Merchant Response
            </span>
            <span class="font-medium text-gray-900 dark:text-white">{{ reply.user_name ?? 'User' }}</span>
            <span class="text-xs text-gray-400 dark:text-gray-500">{{ formatDate(reply.created_at) }}</span>
        </div>
        <p class="whitespace-pre-wrap text-gray-700 dark:text-gray-300">{{ reply.body }}</p>
    </div>
</template>
