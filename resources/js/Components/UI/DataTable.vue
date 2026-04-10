<script setup lang="ts" generic="T extends Record<string, unknown>">
import { ref, computed } from 'vue';

export interface Column<Row = Record<string, unknown>> {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'center' | 'right';
    width?: string;
    format?: (value: unknown, row: Row) => string;
}

interface Props {
    columns: Column<T>[];
    rows: T[];
    loading?: boolean;
    skeletonRows?: number;
    rowKey?: string;
    theme?: 'light' | 'dark';
    stickyHeader?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    skeletonRows: 5,
    rowKey: 'id',
    theme: 'light',
    stickyHeader: false,
    emptyTitle: 'No records found',
    emptyDescription: 'There is nothing to display yet.',
});

const emit = defineEmits<{
    sort: [key: string, direction: 'asc' | 'desc'];
    rowClick: [row: T];
}>();

const sortKey = ref('');
const sortDirection = ref<'asc' | 'desc'>('asc');

function handleSort(col: Column<T>) {
    if (!col.sortable) { return; }
    if (sortKey.value === col.key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = col.key;
        sortDirection.value = 'asc';
    }
    emit('sort', sortKey.value, sortDirection.value);
}

function getCellValue(row: T, col: Column<T>): string {
    const raw = row[col.key];
    if (col.format) { return col.format(raw, row); }
    if (raw === null || raw === undefined) { return '—'; }
    return String(raw);
}

const alignClass: Record<string, string> = {
    left:   'text-left',
    center: 'text-center',
    right:  'text-right',
};

const isDark = computed(() => props.theme === 'dark');

const headerClass = computed(() =>
    isDark.value
        ? 'bg-navy-900/80 text-navy-300 border-navy-800'
        : 'bg-slate-50 dark:bg-navy-900/50 text-slate-500 dark:text-navy-400 border-slate-200 dark:border-navy-700',
);

const rowClass = computed(() =>
    isDark.value
        ? 'border-navy-800 hover:bg-navy-800/50 text-white'
        : 'border-slate-100 dark:border-navy-800 hover:bg-slate-50 dark:hover:bg-navy-800/40 text-slate-800 dark:text-slate-200',
);

const wrapperClass = computed(() =>
    isDark.value
        ? 'rounded-xl border border-navy-800 overflow-hidden'
        : 'rounded-xl border border-slate-200 dark:border-navy-700 overflow-hidden',
);
</script>

<template>
    <div :class="wrapperClass">
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <!-- Header -->
                <thead>
                    <tr>
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            class="px-4 py-3 text-xs font-semibold uppercase tracking-wider border-b whitespace-nowrap select-none"
                            :class="[
                                headerClass,
                                alignClass[col.align ?? 'left'],
                                col.sortable ? 'cursor-pointer hover:opacity-80' : '',
                                stickyHeader ? 'sticky top-0 z-10' : '',
                                col.width ?? '',
                            ]"
                            @click="handleSort(col)"
                        >
                            <span class="inline-flex items-center gap-1">
                                {{ col.label }}
                                <template v-if="col.sortable">
                                    <svg
                                        v-if="sortKey === col.key && sortDirection === 'asc'"
                                        class="size-3.5 opacity-70"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                                    </svg>
                                    <svg
                                        v-else-if="sortKey === col.key && sortDirection === 'desc'"
                                        class="size-3.5 opacity-70"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
                                    <svg
                                        v-else
                                        class="size-3.5 opacity-30"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4" />
                                    </svg>
                                </template>
                            </span>
                        </th>
                        <!-- Actions column header -->
                        <th
                            v-if="$slots.actions"
                            class="px-4 py-3 border-b"
                            :class="[headerClass, 'text-right']"
                        />
                    </tr>
                </thead>

                <!-- Skeleton loading -->
                <tbody v-if="loading">
                    <tr
                        v-for="i in skeletonRows"
                        :key="i"
                        class="border-b last:border-0"
                        :class="isDark ? 'border-navy-800' : 'border-slate-100 dark:border-navy-800'"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            class="px-4 py-3"
                        >
                            <div class="skeleton h-4 rounded" :style="{ width: Math.random() * 40 + 50 + '%' }" />
                        </td>
                        <td v-if="$slots.actions" class="px-4 py-3">
                            <div class="skeleton h-4 rounded w-16 ml-auto" />
                        </td>
                    </tr>
                </tbody>

                <!-- Data rows -->
                <tbody v-else-if="rows.length > 0">
                    <tr
                        v-for="row in rows"
                        :key="String(row[rowKey])"
                        class="border-b last:border-0 transition-colors"
                        :class="[rowClass, $listeners?.rowClick ? 'cursor-pointer' : '']"
                        @click="emit('rowClick', row)"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            class="px-4 py-3"
                            :class="alignClass[col.align ?? 'left']"
                        >
                            <!-- Custom cell slot -->
                            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                {{ getCellValue(row, col) }}
                            </slot>
                        </td>
                        <!-- Row actions slot -->
                        <td v-if="$slots.actions" class="px-4 py-3 text-right">
                            <slot name="actions" :row="row" />
                        </td>
                    </tr>
                </tbody>

                <!-- Empty state -->
                <tbody v-else>
                    <tr>
                        <td
                            :colspan="columns.length + ($slots.actions ? 1 : 0)"
                            class="px-4 py-0"
                        >
                            <slot name="empty">
                                <div class="flex flex-col items-center justify-center py-14 text-center">
                                    <div
                                        class="mb-3 flex size-12 items-center justify-center rounded-xl"
                                        :class="isDark ? 'bg-navy-800 text-navy-500' : 'bg-slate-100 dark:bg-navy-800 text-slate-400 dark:text-navy-500'"
                                    >
                                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p
                                        class="font-medium text-sm"
                                        :class="isDark ? 'text-navy-300' : 'text-slate-600 dark:text-navy-300'"
                                    >
                                        {{ emptyTitle }}
                                    </p>
                                    <p
                                        v-if="emptyDescription"
                                        class="text-xs mt-0.5"
                                        :class="isDark ? 'text-navy-500' : 'text-slate-400 dark:text-navy-500'"
                                    >
                                        {{ emptyDescription }}
                                    </p>
                                </div>
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer slot (pagination, etc.) -->
        <div v-if="$slots.footer">
            <slot name="footer" />
        </div>
    </div>
</template>
