<script setup lang="ts">
import { computed } from 'vue';

interface Step {
    id: string;
    name: string;
    description?: string;
}

interface Props {
    currentStep: number;
    steps?: Step[];
}

const props = withDefaults(defineProps<Props>(), {
    steps: () => [
        { id: 'review', name: 'Review', description: 'Review your order' },
        { id: 'payment', name: 'Payment', description: 'Complete payment' },
        { id: 'complete', name: 'Complete', description: 'Order confirmed' },
    ],
});

function getStepStatus(index: number): 'complete' | 'current' | 'upcoming' {
    if (index < props.currentStep) return 'complete';
    if (index === props.currentStep) return 'current';
    return 'upcoming';
}
</script>

<template>
    <nav aria-label="Checkout progress">
        <!-- Mobile view -->
        <div class="sm:hidden">
            <div class="flex items-center justify-center gap-2 text-sm">
                <span class="font-medium text-indigo-600 dark:text-indigo-400">
                    Step {{ currentStep + 1 }} of {{ steps.length }}
                </span>
                <span class="text-gray-500 dark:text-gray-400">
                    &mdash; {{ steps[currentStep]?.name }}
                </span>
            </div>
            <!-- Progress bar -->
            <div class="mt-3 h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                <div
                    class="h-full rounded-full bg-indigo-600 transition-all duration-500"
                    :style="{ width: `${((currentStep + 1) / steps.length) * 100}%` }"
                />
            </div>
        </div>

        <!-- Desktop view -->
        <ol class="hidden sm:flex items-center">
            <li
                v-for="(step, index) in steps"
                :key="step.id"
                class="relative flex-1"
                :class="{ 'pr-8 sm:pr-20': index !== steps.length - 1 }"
            >
                <!-- Connector line -->
                <div
                    v-if="index !== steps.length - 1"
                    class="absolute top-4 left-0 -right-2 sm:-right-10 h-0.5"
                    :class="[
                        getStepStatus(index) === 'complete'
                            ? 'bg-indigo-600'
                            : 'bg-gray-200 dark:bg-gray-700'
                    ]"
                    style="left: calc(50% + 1rem)"
                />

                <div class="group flex flex-col items-center">
                    <!-- Step indicator -->
                    <span
                        class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full transition-colors duration-200"
                        :class="{
                            'bg-indigo-600 text-white': getStepStatus(index) === 'complete',
                            'border-2 border-indigo-600 bg-white dark:bg-gray-800 text-indigo-600': getStepStatus(index) === 'current',
                            'border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400': getStepStatus(index) === 'upcoming',
                        }"
                    >
                        <!-- Checkmark for complete -->
                        <svg
                            v-if="getStepStatus(index) === 'complete'"
                            class="h-5 w-5"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        <!-- Step number for current/upcoming -->
                        <span v-else class="text-sm font-semibold">
                            {{ index + 1 }}
                        </span>
                    </span>

                    <!-- Step label -->
                    <span
                        class="mt-2 text-sm font-medium transition-colors duration-200"
                        :class="{
                            'text-indigo-600 dark:text-indigo-400': getStepStatus(index) === 'complete' || getStepStatus(index) === 'current',
                            'text-gray-500 dark:text-gray-400': getStepStatus(index) === 'upcoming',
                        }"
                    >
                        {{ step.name }}
                    </span>

                    <!-- Step description -->
                    <span
                        v-if="step.description"
                        class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 text-center hidden lg:block"
                    >
                        {{ step.description }}
                    </span>
                </div>
            </li>
        </ol>
    </nav>
</template>
