<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import DOMPurify from 'dompurify';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface ContentPageData {
    title: string;
    slug: string;
    body: string | null;
    meta_description: string | null;
    updated_at: string | null;
}

interface Props {
    page: ContentPageData;
}

const props = defineProps<Props>();

const currentPage = usePage();
const isAuthenticated = computed(() => currentPage.props.auth?.user !== null);
const Layout = computed(() => isAuthenticated.value ? AuthenticatedLayout : GuestLayout);
</script>

<template>
    <Head :title="page.title">
        <meta v-if="page.meta_description" name="description" :content="page.meta_description" />
    </Head>

    <component :is="Layout">
        <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
            <!-- Page header -->
            <div class="mb-10 border-b border-slate-200 dark:border-navy-800/60 pb-8">
                <h1 class="text-4xl font-bold text-slate-900 dark:text-white">{{ page.title }}</h1>
                <p v-if="page.updated_at" class="mt-3 text-sm text-slate-500 dark:text-navy-500">
                    Last updated {{ page.updated_at }}
                </p>
            </div>

            <!-- Page body (rendered HTML from CMS) -->
            <div
                v-if="page.body"
                class="prose prose-slate dark:prose-invert max-w-none
                    prose-headings:font-bold prose-headings:text-slate-900 dark:prose-headings:text-white
                    prose-p:text-slate-600 dark:prose-p:text-navy-300
                    prose-a:text-brand-600 dark:prose-a:text-brand-400 prose-a:no-underline hover:prose-a:underline
                    prose-ul:text-slate-600 dark:prose-ul:text-navy-300
                    prose-ol:text-slate-600 dark:prose-ol:text-navy-300
                    prose-blockquote:border-brand-500 prose-blockquote:text-slate-500 dark:prose-blockquote:text-navy-400"
                v-html="DOMPurify.sanitize(page.body ?? '')"
            />

            <div v-else class="text-slate-400 dark:text-navy-600 italic">
                This page has no content yet.
            </div>
        </div>
    </component>
</template>
