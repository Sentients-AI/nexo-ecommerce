<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';

interface ImportResult {
    imported: number;
    skipped: number;
    errors: string[];
}

interface Props {
    import_result?: ImportResult;
}

const props = defineProps<Props>();
const page = usePage();

const flash = computed(() => (page.props as Record<string, unknown>).import_result as ImportResult | undefined ?? props.import_result);

const file = ref<File | null>(null);
const isDragging = ref(false);
const isUploading = ref(false);

function onFileChange(e: Event): void {
    const target = e.target as HTMLInputElement;
    file.value = target.files?.[0] ?? null;
}

function onDrop(e: DragEvent): void {
    isDragging.value = false;
    const dropped = e.dataTransfer?.files[0];
    if (dropped && (dropped.type === 'text/csv' || dropped.name.endsWith('.csv'))) {
        file.value = dropped;
    }
}

function submit(): void {
    if (!file.value || isUploading.value) { return; }

    isUploading.value = true;

    const formData = new FormData();
    formData.append('csv', file.value);

    router.post('/vendor/products/import', formData, {
        forceFormData: true,
        onFinish: () => { isUploading.value = false; },
    });
}

const sampleCsvContent = `name,sku,price,description,short_description,category,stock,is_active
"Blue Widget",WIDGET-001,29.99,"A fantastic blue widget","Great for everyday use","Widgets",100,true
"Red Gadget",GADGET-001,49.99,"A premium red gadget","Top of the range","Gadgets",50,true`;

function downloadSample(): void {
    const blob = new Blob([sampleCsvContent], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sample-products.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <Head title="Import Products" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <Link href="/vendor/products" class="text-sm text-navy-400 hover:text-white transition-colors">Products</Link>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Import</span>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-white">Bulk Import Products</h1>
                    <p class="mt-1 text-sm text-navy-400">Upload a CSV file to create multiple products at once</p>
                </div>
                <button
                    @click="downloadSample"
                    class="inline-flex items-center gap-2 rounded-xl border border-navy-700 px-4 py-2 text-sm font-medium text-navy-300 hover:border-navy-600 hover:text-white transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Sample CSV
                </button>
            </div>

            <!-- Import result -->
            <div v-if="flash" class="mb-6 rounded-2xl border overflow-hidden" :class="flash.errors.length > 0 && flash.imported === 0 ? 'border-red-500/30 bg-red-500/5' : 'border-accent-500/30 bg-accent-500/5'">
                <div class="px-6 py-4 flex items-center gap-3">
                    <div v-if="flash.imported > 0" class="flex h-8 w-8 items-center justify-center rounded-full bg-accent-500/15">
                        <svg class="h-4 w-4 text-accent-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-white">
                            {{ flash.imported }} product{{ flash.imported !== 1 ? 's' : '' }} imported
                            <span v-if="flash.skipped > 0" class="font-normal text-navy-400">, {{ flash.skipped }} skipped</span>
                        </p>
                    </div>
                </div>
                <ul v-if="flash.errors.length > 0" class="border-t border-navy-800/60 divide-y divide-navy-800/60">
                    <li v-for="(err, i) in flash.errors" :key="i" class="px-6 py-2.5 text-sm text-red-400">
                        {{ err }}
                    </li>
                </ul>
            </div>

            <!-- Upload card -->
            <div class="rounded-2xl border border-navy-800/60 bg-navy-900/60 overflow-hidden">
                <div class="border-b border-navy-800/60 px-6 py-4 bg-navy-900/80">
                    <h2 class="text-base font-semibold text-white">Upload CSV File</h2>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Drop zone -->
                    <div
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="onDrop"
                        class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors"
                        :class="isDragging ? 'border-brand-500 bg-brand-500/5' : 'border-navy-700 hover:border-navy-600'"
                    >
                        <svg class="h-10 w-10 text-navy-500 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <p class="text-sm font-medium text-navy-300">
                            <span v-if="file">{{ file.name }}</span>
                            <span v-else>Drop your CSV here, or <label class="cursor-pointer text-brand-400 hover:text-brand-300">browse<input type="file" accept=".csv,text/csv" class="sr-only" @change="onFileChange" /></label></span>
                        </p>
                        <p class="mt-1 text-xs text-navy-500">CSV files only · max 10 MB</p>
                    </div>

                    <!-- Column reference -->
                    <div class="rounded-xl bg-navy-800/40 px-5 py-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-navy-400">CSV Columns</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-brand-500/20 px-1.5 py-0.5 font-mono text-brand-400">name</span>
                                <span class="text-red-400 font-medium">required</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-brand-500/20 px-1.5 py-0.5 font-mono text-brand-400">sku</span>
                                <span class="text-red-400 font-medium">required</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-brand-500/20 px-1.5 py-0.5 font-mono text-brand-400">price</span>
                                <span class="text-red-400 font-medium">required</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-navy-700 px-1.5 py-0.5 font-mono text-navy-300">category</span>
                                <span class="text-navy-500">optional</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-navy-700 px-1.5 py-0.5 font-mono text-navy-300">description</span>
                                <span class="text-navy-500">optional</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-navy-700 px-1.5 py-0.5 font-mono text-navy-300">short_description</span>
                                <span class="text-navy-500">optional</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-navy-700 px-1.5 py-0.5 font-mono text-navy-300">stock</span>
                                <span class="text-navy-500">optional (qty)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-navy-700 px-1.5 py-0.5 font-mono text-navy-300">is_active</span>
                                <span class="text-navy-500">optional (true/false)</span>
                            </div>
                        </div>
                    </div>

                    <button
                        @click="submit"
                        :disabled="!file || isUploading"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-brand-500/25 hover:bg-brand-400 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <svg v-if="!isUploading" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                        </svg>
                        <svg v-else class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ isUploading ? 'Importing...' : 'Import Products' }}
                    </button>
                </div>
            </div>
        </div>
    </VendorLayout>
</template>
