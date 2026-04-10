<script setup lang="ts">
interface Step {
    id: string;
    name: string;
}

interface Props {
    currentStep: number;
    steps?: Step[];
}

const props = withDefaults(defineProps<Props>(), {
    steps: () => [
        { id: 'review',   name: 'Review Order' },
        { id: 'payment',  name: 'Payment' },
        { id: 'complete', name: 'Confirmed' },
    ],
});

function status(index: number): 'complete' | 'current' | 'upcoming' {
    if (index < props.currentStep) { return 'complete'; }
    if (index === props.currentStep) { return 'current'; }
    return 'upcoming';
}
</script>

<template>
    <nav aria-label="Checkout progress">
        <!-- Mobile: progress bar + label -->
        <div class="sm:hidden">
            <div class="flex items-center justify-between text-sm mb-2">
                <span class="font-semibold text-slate-900 dark:text-white">
                    {{ steps[currentStep]?.name }}
                </span>
                <span class="text-slate-500 dark:text-navy-400 text-xs">
                    {{ currentStep + 1 }} / {{ steps.length }}
                </span>
            </div>
            <div class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-navy-800 overflow-hidden">
                <div
                    class="h-full rounded-full bg-brand-500 transition-all duration-500"
                    :style="{ width: `${((currentStep + 1) / steps.length) * 100}%` }"
                />
            </div>
        </div>

        <!-- Desktop: step indicators -->
        <ol class="hidden sm:flex items-center gap-0">
            <li
                v-for="(step, i) in steps"
                :key="step.id"
                class="flex items-center"
                :class="i < steps.length - 1 ? 'flex-1' : ''"
            >
                <!-- Step -->
                <div class="flex flex-col items-center shrink-0">
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold transition-all duration-200"
                        :class="{
                            'bg-brand-500 text-white shadow-sm shadow-brand-500/30':
                                status(i) === 'complete',
                            'border-2 border-brand-500 bg-brand-50 dark:bg-brand-950/40 text-brand-600 dark:text-brand-400':
                                status(i) === 'current',
                            'border-2 border-slate-200 dark:border-navy-700 bg-white dark:bg-navy-900 text-slate-400 dark:text-navy-500':
                                status(i) === 'upcoming',
                        }"
                    >
                        <svg
                            v-if="status(i) === 'complete'"
                            class="h-4.5 w-4.5"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                        </svg>
                        <span v-else>{{ i + 1 }}</span>
                    </span>
                    <span
                        class="mt-1.5 text-xs font-medium whitespace-nowrap transition-colors duration-200"
                        :class="{
                            'text-brand-600 dark:text-brand-400': status(i) !== 'upcoming',
                            'text-slate-400 dark:text-navy-500': status(i) === 'upcoming',
                        }"
                    >
                        {{ step.name }}
                    </span>
                </div>

                <!-- Connector -->
                <div
                    v-if="i < steps.length - 1"
                    class="flex-1 h-0.5 mx-3 mb-5 rounded-full transition-all duration-500"
                    :class="status(i) === 'complete'
                        ? 'bg-brand-500'
                        : 'bg-slate-200 dark:bg-navy-700'"
                />
            </li>
        </ol>
    </nav>
</template>
