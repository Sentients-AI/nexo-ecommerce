<script setup lang="ts">
interface Props {
    distribution: Record<number, number>;
    totalCount: number;
}

const props = defineProps<Props>();

const percentage = (star: number): number => {
    if (props.totalCount === 0) {
        return 0;
    }

    return Math.round(((props.distribution[star] ?? 0) / props.totalCount) * 100);
};
</script>

<template>
    <div class="space-y-2">
        <div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="flex items-center gap-3">
            <span class="w-12 shrink-0 text-right text-sm text-gray-600 dark:text-gray-400">{{ star }} ★</span>
            <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                    class="h-2.5 rounded-full bg-yellow-400 transition-all duration-300"
                    :style="{ width: percentage(star) + '%' }"
                />
            </div>
            <span class="w-10 shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ distribution[star] ?? 0 }}</span>
        </div>
    </div>
</template>
