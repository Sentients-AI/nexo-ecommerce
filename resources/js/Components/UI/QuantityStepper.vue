<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    modelValue: number;
    min?: number;
    max?: number;
    disabled?: boolean;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    min: 1,
    max: 99,
    disabled: false,
    size: 'md',
});

const emit = defineEmits<{
    'update:modelValue': [value: number];
}>();

const canDecrement = computed(() => props.modelValue > props.min && !props.disabled);
const canIncrement = computed(() => props.modelValue < props.max && !props.disabled);

function decrement() {
    if (canDecrement.value) {
        emit('update:modelValue', props.modelValue - 1);
    }
}

function increment() {
    if (canIncrement.value) {
        emit('update:modelValue', props.modelValue + 1);
    }
}

function handleInput(event: Event) {
    const target = event.target as HTMLInputElement;
    let value = parseInt(target.value, 10);

    if (isNaN(value)) {
        value = props.min;
    } else if (value < props.min) {
        value = props.min;
    } else if (value > props.max) {
        value = props.max;
    }

    emit('update:modelValue', value);
}

const sizeClasses = {
    sm: {
        button: 'h-7 w-7 text-sm',
        input: 'h-7 w-10 text-sm',
    },
    md: {
        button: 'h-9 w-9 text-base',
        input: 'h-9 w-12 text-base',
    },
    lg: {
        button: 'h-11 w-11 text-lg',
        input: 'h-11 w-14 text-lg',
    },
};
</script>

<template>
    <div class="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800">
        <!-- Decrement button -->
        <button
            type="button"
            @click="decrement"
            :disabled="!canDecrement"
            class="flex items-center justify-center rounded-l-lg border-r border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-transparent dark:disabled:hover:bg-transparent"
            :class="sizeClasses[size].button"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
            </svg>
        </button>

        <!-- Input -->
        <input
            type="number"
            :value="modelValue"
            @input="handleInput"
            @blur="handleInput"
            :min="min"
            :max="max"
            :disabled="disabled"
            class="border-0 text-center font-medium text-gray-900 dark:text-white bg-transparent focus:ring-0 focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
            :class="sizeClasses[size].input"
        />

        <!-- Increment button -->
        <button
            type="button"
            @click="increment"
            :disabled="!canIncrement"
            class="flex items-center justify-center rounded-r-lg border-l border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-transparent dark:disabled:hover:bg-transparent"
            :class="sizeClasses[size].button"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
        </button>
    </div>
</template>
