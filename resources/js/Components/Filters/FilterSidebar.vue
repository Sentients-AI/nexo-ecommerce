<script setup lang="ts">
import { ref, computed } from 'vue';
import PriceRangeSlider from './PriceRangeSlider.vue';
import type { CategoryApiResource } from '@/types/api';

interface Props {
    categories?: CategoryApiResource[];
    selectedCategory?: string;
    minPrice?: number;
    maxPrice?: number;
    inStockOnly?: boolean;
    onSaleOnly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    minPrice: 0,
    maxPrice: 100000,
    inStockOnly: false,
    onSaleOnly: false,
});

const emit = defineEmits<{
    'update:selectedCategory': [value: string];
    'update:minPrice': [value: number];
    'update:maxPrice': [value: number];
    'update:inStockOnly': [value: boolean];
    'update:onSaleOnly': [value: boolean];
    apply: [];
    clear: [];
}>();

const localMinPrice = ref(props.minPrice);
const localMaxPrice = ref(props.maxPrice);
const localInStockOnly = ref(props.inStockOnly);
const localOnSaleOnly = ref(props.onSaleOnly);

const showCategories = ref(true);
const showPrice = ref(true);
const showAvailability = ref(true);

const activeFilterCount = computed(() => {
    let count = 0;
    if (props.selectedCategory) count++;
    if (localMinPrice.value > 0 || localMaxPrice.value < 100000) count++;
    if (localInStockOnly.value) count++;
    if (localOnSaleOnly.value) count++;
    return count;
});

function handleCategoryClick(slug: string) {
    const newValue = props.selectedCategory === slug ? '' : slug;
    emit('update:selectedCategory', newValue);
    emit('apply');
}

function handlePriceChange(min: number, max: number) {
    localMinPrice.value = min;
    localMaxPrice.value = max;
    emit('update:minPrice', min);
    emit('update:maxPrice', max);
    emit('apply');
}

function handleInStockToggle() {
    localInStockOnly.value = !localInStockOnly.value;
    emit('update:inStockOnly', localInStockOnly.value);
    emit('apply');
}

function handleOnSaleToggle() {
    localOnSaleOnly.value = !localOnSaleOnly.value;
    emit('update:onSaleOnly', localOnSaleOnly.value);
    emit('apply');
}

function handleClearAll() {
    localMinPrice.value = 0;
    localMaxPrice.value = 100000;
    localInStockOnly.value = false;
    localOnSaleOnly.value = false;
    emit('clear');
}
</script>

<template>
    <aside class="w-full lg:w-64 shrink-0">
        <div class="sticky top-4 rounded-2xl bg-white dark:bg-navy-900/60 border border-slate-100 dark:border-navy-800/60 overflow-hidden shadow-sm">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b border-slate-100 dark:border-navy-800/60">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                    </svg>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Filters</h2>
                    <span
                        v-if="activeFilterCount > 0"
                        class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-xs font-medium text-white"
                    >
                        {{ activeFilterCount }}
                    </span>
                </div>
                <button
                    v-if="activeFilterCount > 0"
                    @click="handleClearAll"
                    class="text-xs font-medium text-brand-600 dark:text-brand-400 hover:text-brand-500 transition-colors"
                >
                    Clear all
                </button>
            </div>

            <!-- Categories Section -->
            <div v-if="categories && categories.length > 0" class="border-b border-slate-100 dark:border-navy-800/60">
                <button
                    @click="showCategories = !showCategories"
                    class="flex w-full items-center justify-between p-4 text-left hover:bg-slate-50 dark:hover:bg-navy-800/40 transition-colors"
                >
                    <span class="text-sm font-medium text-slate-900 dark:text-white">Categories</span>
                    <svg
                        class="h-4 w-4 text-slate-500 transition-transform duration-200"
                        :class="{ 'rotate-180': showCategories }"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                <Transition
                    enter-active-class="duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-show="showCategories" class="px-4 pb-4">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="cat in categories"
                                :key="cat.id"
                                @click="handleCategoryClick(cat.slug)"
                                class="px-3 py-1.5 text-sm font-medium rounded-full border transition-all"
                                :class="selectedCategory === cat.slug
                                    ? 'bg-brand-500 border-brand-500 text-white shadow-sm shadow-brand-500/25'
                                    : 'bg-white dark:bg-navy-800 border-slate-300 dark:border-navy-700 text-slate-700 dark:text-slate-300 hover:border-brand-400 hover:text-brand-600 dark:hover:text-brand-400'"
                            >
                                {{ cat.name }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- Price Range Section -->
            <div class="border-b border-slate-100 dark:border-navy-800/60">
                <button
                    @click="showPrice = !showPrice"
                    class="flex w-full items-center justify-between p-4 text-left hover:bg-slate-50 dark:hover:bg-navy-800/40 transition-colors"
                >
                    <span class="text-sm font-medium text-slate-900 dark:text-white">Price Range</span>
                    <svg
                        class="h-4 w-4 text-slate-500 transition-transform duration-200"
                        :class="{ 'rotate-180': showPrice }"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                <Transition
                    enter-active-class="duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-show="showPrice" class="px-4 pb-4">
                        <PriceRangeSlider
                            :min-value="localMinPrice"
                            :max-value="localMaxPrice"
                            :min="0"
                            :max="100000"
                            :step="100"
                            @change="handlePriceChange"
                        />
                    </div>
                </Transition>
            </div>

            <!-- Availability Section -->
            <div>
                <button
                    @click="showAvailability = !showAvailability"
                    class="flex w-full items-center justify-between p-4 text-left hover:bg-slate-50 dark:hover:bg-navy-800/40 transition-colors"
                >
                    <span class="text-sm font-medium text-slate-900 dark:text-white">Availability</span>
                    <svg
                        class="h-4 w-4 text-slate-500 transition-transform duration-200"
                        :class="{ 'rotate-180': showAvailability }"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                <Transition
                    enter-active-class="duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-show="showAvailability" class="px-4 pb-4 space-y-3">
                        <!-- In Stock Toggle -->
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <div class="relative">
                                <input
                                    type="checkbox"
                                    :checked="localInStockOnly"
                                    @change="handleInStockToggle"
                                    class="sr-only peer"
                                />
                                <div class="h-5 w-9 rounded-full bg-slate-200 dark:bg-navy-700 peer-checked:bg-accent-500 transition-colors" />
                                <div class="absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-4" />
                            </div>
                            <span class="text-sm text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white transition-colors">
                                In Stock Only
                            </span>
                        </label>

                        <!-- On Sale Toggle -->
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <div class="relative">
                                <input
                                    type="checkbox"
                                    :checked="localOnSaleOnly"
                                    @change="handleOnSaleToggle"
                                    class="sr-only peer"
                                />
                                <div class="h-5 w-9 rounded-full bg-slate-200 dark:bg-navy-700 peer-checked:bg-red-500 transition-colors" />
                                <div class="absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-4" />
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white transition-colors">
                                    On Sale
                                </span>
                                <span class="px-1.5 py-0.5 text-xs font-bold text-white bg-red-500 rounded">
                                    SALE
                                </span>
                            </div>
                        </label>
                    </div>
                </Transition>
            </div>
        </div>
    </aside>
</template>
