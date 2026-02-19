<script setup lang="ts">
import { computed, ref } from 'vue';

interface Props {
    rating?: number;
    size?: 'sm' | 'md' | 'lg';
    interactive?: boolean;
    count?: number;
}

const props = withDefaults(defineProps<Props>(), {
    rating: 0,
    size: 'md',
    interactive: false,
    count: undefined,
});

const emit = defineEmits<{
    'update:rating': [value: number];
}>();

const hoverRating = ref(0);

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm': return 'h-4 w-4';
        case 'lg': return 'h-7 w-7';
        default: return 'h-5 w-5';
    }
});

const displayRating = computed(() => {
    if (props.interactive && hoverRating.value > 0) {
        return hoverRating.value;
    }
    return props.rating;
});

function handleClick(star: number) {
    if (props.interactive) {
        emit('update:rating', star);
    }
}
</script>

<template>
    <div class="flex items-center gap-1">
        <button
            v-for="star in 5"
            :key="star"
            type="button"
            :class="[
                interactive ? 'cursor-pointer hover:scale-110 transition-transform' : 'cursor-default',
                sizeClasses,
            ]"
            @click="handleClick(star)"
            @mouseenter="interactive && (hoverRating = star)"
            @mouseleave="interactive && (hoverRating = 0)"
        >
            <svg
                :class="[sizeClasses, star <= displayRating ? 'text-yellow-400' : 'text-gray-300 dark:text-gray-600']"
                fill="currentColor"
                viewBox="0 0 20 20"
            >
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
            </svg>
        </button>
        <span v-if="count !== undefined" class="ms-1 text-sm text-gray-500 dark:text-gray-400">
            ({{ count }})
        </span>
    </div>
</template>
