<script setup lang="ts">
import { ref, computed, watch } from 'vue';

interface Props {
    minValue?: number;
    maxValue?: number;
    min?: number;
    max?: number;
    step?: number;
    formatPrice?: (value: number) => string;
}

const props = withDefaults(defineProps<Props>(), {
    minValue: 0,
    maxValue: 100000,
    min: 0,
    max: 100000,
    step: 100,
    formatPrice: (value: number) => `$${(value / 100).toFixed(0)}`,
});

const emit = defineEmits<{
    'update:minValue': [value: number];
    'update:maxValue': [value: number];
    change: [min: number, max: number];
}>();

const localMin = ref(props.minValue);
const localMax = ref(props.maxValue);

// Watch for prop changes
watch(() => props.minValue, (val) => { localMin.value = val; });
watch(() => props.maxValue, (val) => { localMax.value = val; });

const minPercent = computed(() => ((localMin.value - props.min) / (props.max - props.min)) * 100);
const maxPercent = computed(() => ((localMax.value - props.min) / (props.max - props.min)) * 100);

function handleMinChange(e: Event) {
    const target = e.target as HTMLInputElement;
    const value = Math.min(Number(target.value), localMax.value - props.step);
    localMin.value = value;
    emit('update:minValue', value);
}

function handleMaxChange(e: Event) {
    const target = e.target as HTMLInputElement;
    const value = Math.max(Number(target.value), localMin.value + props.step);
    localMax.value = value;
    emit('update:maxValue', value);
}

function handleChangeEnd() {
    emit('change', localMin.value, localMax.value);
}
</script>

<template>
    <div class="space-y-4">
        <!-- Price display -->
        <div class="flex items-center justify-between text-sm">
            <span class="font-medium text-gray-900 dark:text-white">
                {{ formatPrice(localMin) }}
            </span>
            <span class="text-gray-400">—</span>
            <span class="font-medium text-gray-900 dark:text-white">
                {{ formatPrice(localMax) }}
            </span>
        </div>

        <!-- Dual range slider -->
        <div class="relative h-2">
            <!-- Track background -->
            <div class="absolute inset-0 rounded-full bg-gray-200 dark:bg-gray-700" />

            <!-- Active track -->
            <div
                class="absolute h-full rounded-full bg-indigo-500"
                :style="{
                    left: `${minPercent}%`,
                    right: `${100 - maxPercent}%`,
                }"
            />

            <!-- Min slider -->
            <input
                type="range"
                :min="min"
                :max="max"
                :step="step"
                :value="localMin"
                @input="handleMinChange"
                @change="handleChangeEnd"
                class="absolute inset-0 w-full appearance-none bg-transparent pointer-events-none [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:h-5 [&::-webkit-slider-thumb]:w-5 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-white [&::-webkit-slider-thumb]:shadow-md [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-indigo-500 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:transition-transform [&::-webkit-slider-thumb]:hover:scale-110 [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:h-5 [&::-moz-range-thumb]:w-5 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-white [&::-moz-range-thumb]:shadow-md [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-indigo-500 [&::-moz-range-thumb]:cursor-pointer"
            />

            <!-- Max slider -->
            <input
                type="range"
                :min="min"
                :max="max"
                :step="step"
                :value="localMax"
                @input="handleMaxChange"
                @change="handleChangeEnd"
                class="absolute inset-0 w-full appearance-none bg-transparent pointer-events-none [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:h-5 [&::-webkit-slider-thumb]:w-5 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-white [&::-webkit-slider-thumb]:shadow-md [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-indigo-500 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:transition-transform [&::-webkit-slider-thumb]:hover:scale-110 [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:h-5 [&::-moz-range-thumb]:w-5 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-white [&::-moz-range-thumb]:shadow-md [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-indigo-500 [&::-moz-range-thumb]:cursor-pointer"
            />
        </div>

        <!-- Quick preset buttons -->
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                @click="localMin = min; localMax = 2500; handleChangeEnd()"
                class="px-2.5 py-1 text-xs font-medium rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            >
                Under $25
            </button>
            <button
                type="button"
                @click="localMin = 2500; localMax = 5000; handleChangeEnd()"
                class="px-2.5 py-1 text-xs font-medium rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            >
                $25-$50
            </button>
            <button
                type="button"
                @click="localMin = 5000; localMax = 10000; handleChangeEnd()"
                class="px-2.5 py-1 text-xs font-medium rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            >
                $50-$100
            </button>
            <button
                type="button"
                @click="localMin = 10000; localMax = max; handleChangeEnd()"
                class="px-2.5 py-1 text-xs font-medium rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            >
                $100+
            </button>
        </div>
    </div>
</template>
