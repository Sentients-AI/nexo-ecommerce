<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Crumb {
    label: string;
    href?: string;
}

interface Props {
    items: Crumb[];
    theme?: 'light' | 'dark';
}

const props = withDefaults(defineProps<Props>(), {
    theme: 'light',
});

const linkClass = props.theme === 'dark'
    ? 'text-navy-400 hover:text-white transition-colors'
    : 'text-slate-500 hover:text-slate-700 dark:text-navy-400 dark:hover:text-white transition-colors';

const currentClass = props.theme === 'dark'
    ? 'text-white font-medium'
    : 'text-slate-800 dark:text-white font-medium';

const separatorClass = props.theme === 'dark'
    ? 'text-navy-600'
    : 'text-slate-300 dark:text-navy-600';
</script>

<template>
    <nav aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1.5 text-sm">
            <li
                v-for="(crumb, i) in items"
                :key="i"
                class="flex items-center gap-1.5"
            >
                <!-- Separator -->
                <svg
                    v-if="i > 0"
                    class="size-3.5 shrink-0"
                    :class="separatorClass"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>

                <!-- Link or current -->
                <Link
                    v-if="crumb.href && i < items.length - 1"
                    :href="crumb.href"
                    :class="linkClass"
                >
                    {{ crumb.label }}
                </Link>
                <span
                    v-else
                    :class="currentClass"
                    :aria-current="i === items.length - 1 ? 'page' : undefined"
                >
                    {{ crumb.label }}
                </span>
            </li>
        </ol>
    </nav>
</template>
