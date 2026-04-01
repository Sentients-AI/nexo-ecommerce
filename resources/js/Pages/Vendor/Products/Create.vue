<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';

interface Category {
    id: number;
    name: string;
}

interface Props {
    categories: Category[];
}

defineProps<Props>();

const form = useForm({
    name: '',
    sku: '',
    slug: '',
    description: '',
    short_description: '',
    price_cents: '',
    sale_price: '',
    category_id: '',
    is_active: true,
    is_featured: false,
    images: [] as string[],
});

function submit(): void {
    form.post('/vendor/products');
}

function addImageUrl(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.value.trim()) {
        form.images = [...form.images, input.value.trim()];
        input.value = '';
    }
}

function removeImage(index: number): void {
    form.images = form.images.filter((_, i) => i !== index);
}

function generateSlug(): void {
    if (!form.slug) {
        form.slug = form.name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
}
</script>

<template>
    <Head title="Create Product" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <a href="/vendor/products" class="text-sm text-navy-400 hover:text-white transition-colors">Products</a>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Create</span>
            </div>
        </template>

        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Create Product</h1>
                <p class="mt-1 text-sm text-navy-400">Add a new product to your store</p>
            </div>
            <a
                href="/vendor/products"
                class="inline-flex items-center gap-2 rounded-xl border border-navy-700 px-4 py-2 text-sm font-medium text-navy-300 hover:border-navy-600 hover:text-white transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Back
            </a>
        </div>

        <form @submit.prevent="submit" class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <!-- Left column: main fields -->
            <div class="lg:col-span-2 flex flex-col gap-5">
                <!-- Basic info -->
                <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-6">
                    <h2 class="text-sm font-semibold text-white mb-5">Basic Information</h2>

                    <div class="flex flex-col gap-4">
                        <div>
                            <label class="block text-xs font-medium text-navy-400 mb-1.5">Product Name <span class="text-red-400">*</span></label>
                            <input
                                v-model="form.name"
                                @blur="generateSlug"
                                type="text"
                                placeholder="e.g. Premium Wireless Headphones"
                                class="w-full rounded-xl border border-navy-700/50 bg-navy-800/50 px-4 py-2.5 text-sm text-white placeholder-navy-500 focus:border-brand-500/50 focus:outline-none focus:ring-1 focus:ring-brand-500/30"
                                :class="{ 'border-red-500/50': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-navy-400 mb-1.5">SKU <span class="text-red-400">*</span></label>
                                <input
                                    v-model="form.sku"
                                    type="text"
                                    placeholder="e.g. WH-1000XM5"
                                    class="w-full rounded-xl border border-navy-700/50 bg-navy-800/50 px-4 py-2.5 text-sm text-white placeholder-navy-500 focus:border-brand-500/50 focus:outline-none focus:ring-1 focus:ring-brand-500/30"
                                    :class="{ 'border-red-500/50': form.errors.sku }"
                                />
                                <p v-if="form.errors.sku" class="mt-1 text-xs text-red-400">{{ form.errors.sku }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-navy-400 mb-1.5">Slug</label>
                                <input
                                    v-model="form.slug"
                                    type="text"
                                    placeholder="auto-generated from name"
                                    class="w-full rounded-xl border border-navy-700/50 bg-navy-800/50 px-4 py-2.5 text-sm text-white placeholder-navy-500 focus:border-brand-500/50 focus:outline-none focus:ring-1 focus:ring-brand-500/30"
                                    :class="{ 'border-red-500/50': form.errors.slug }"
                                />
                                <p v-if="form.errors.slug" class="mt-1 text-xs text-red-400">{{ form.errors.slug }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-navy-400 mb-1.5">Short Description</label>
                            <input
                                v-model="form.short_description"
                                type="text"
                                placeholder="One-line summary shown in listings"
                                maxlength="500"
                                class="w-full rounded-xl border border-navy-700/50 bg-navy-800/50 px-4 py-2.5 text-sm text-white placeholder-navy-500 focus:border-brand-500/50 focus:outline-none focus:ring-1 focus:ring-brand-500/30"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-navy-400 mb-1.5">Description</label>
                            <textarea
                                v-model="form.description"
                                rows="5"
                                placeholder="Full product description…"
                                class="w-full rounded-xl border border-navy-700/50 bg-navy-800/50 px-4 py-2.5 text-sm text-white placeholder-navy-500 focus:border-brand-500/50 focus:outline-none focus:ring-1 focus:ring-brand-500/30 resize-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-6">
                    <h2 class="text-sm font-semibold text-white mb-5">Pricing</h2>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-navy-400 mb-1.5">Regular Price <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-navy-500 text-sm">$</span>
                                <input
                                    v-model="form.price_cents"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    class="w-full rounded-xl border border-navy-700/50 bg-navy-800/50 pl-8 pr-4 py-2.5 text-sm text-white placeholder-navy-500 focus:border-brand-500/50 focus:outline-none focus:ring-1 focus:ring-brand-500/30"
                                    :class="{ 'border-red-500/50': form.errors.price_cents }"
                                />
                            </div>
                            <p v-if="form.errors.price_cents" class="mt-1 text-xs text-red-400">{{ form.errors.price_cents }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-navy-400 mb-1.5">Sale Price <span class="text-navy-600">(optional)</span></label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-navy-500 text-sm">$</span>
                                <input
                                    v-model="form.sale_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    class="w-full rounded-xl border border-navy-700/50 bg-navy-800/50 pl-8 pr-4 py-2.5 text-sm text-white placeholder-navy-500 focus:border-brand-500/50 focus:outline-none focus:ring-1 focus:ring-brand-500/30"
                                    :class="{ 'border-red-500/50': form.errors.sale_price }"
                                />
                            </div>
                            <p v-if="form.errors.sale_price" class="mt-1 text-xs text-red-400">{{ form.errors.sale_price }}</p>
                        </div>
                    </div>
                </div>

                <!-- Images -->
                <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-6">
                    <h2 class="text-sm font-semibold text-white mb-5">Images</h2>

                    <div class="flex gap-2 mb-3">
                        <input
                            type="url"
                            placeholder="Paste image URL and press Enter"
                            class="flex-1 rounded-xl border border-navy-700/50 bg-navy-800/50 px-4 py-2.5 text-sm text-white placeholder-navy-500 focus:border-brand-500/50 focus:outline-none focus:ring-1 focus:ring-brand-500/30"
                            @keydown.enter.prevent="addImageUrl"
                        />
                        <button
                            type="button"
                            @click="(e) => addImageUrl(e)"
                            class="rounded-xl border border-navy-700 px-4 py-2.5 text-sm text-navy-300 hover:border-navy-600 hover:text-white transition-colors"
                        >
                            Add
                        </button>
                    </div>

                    <div v-if="form.images.length > 0" class="grid grid-cols-3 gap-3">
                        <div
                            v-for="(url, index) in form.images"
                            :key="index"
                            class="group relative aspect-square rounded-xl bg-navy-800/60 overflow-hidden"
                        >
                            <img :src="url" :alt="`Image ${index + 1}`" class="h-full w-full object-cover" />
                            <button
                                type="button"
                                @click="removeImage(index)"
                                class="absolute top-1.5 right-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-red-500/90 text-white opacity-0 group-hover:opacity-100 transition-opacity"
                            >
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <span v-if="index === 0" class="absolute bottom-1.5 left-1.5 rounded-md bg-navy-900/80 px-1.5 py-0.5 text-[10px] text-white">Primary</span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-navy-500">No images added yet. The first image will be used as the primary image.</p>
                </div>
            </div>

            <!-- Right column: status & category -->
            <div class="flex flex-col gap-5">
                <!-- Status & visibility -->
                <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-6">
                    <h2 class="text-sm font-semibold text-white mb-5">Status</h2>

                    <div class="flex flex-col gap-4">
                        <label class="flex items-center justify-between gap-3 cursor-pointer">
                            <div>
                                <p class="text-sm font-medium text-white">Active</p>
                                <p class="text-xs text-navy-500">Visible to customers</p>
                            </div>
                            <button
                                type="button"
                                @click="form.is_active = !form.is_active"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="form.is_active ? 'bg-brand-500' : 'bg-navy-700'"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"
                                />
                            </button>
                        </label>

                        <div class="h-px bg-navy-800/60" />

                        <label class="flex items-center justify-between gap-3 cursor-pointer">
                            <div>
                                <p class="text-sm font-medium text-white">Featured</p>
                                <p class="text-xs text-navy-500">Highlighted in the store</p>
                            </div>
                            <button
                                type="button"
                                @click="form.is_featured = !form.is_featured"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="form.is_featured ? 'bg-amber-500' : 'bg-navy-700'"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="form.is_featured ? 'translate-x-6' : 'translate-x-1'"
                                />
                            </button>
                        </label>
                    </div>
                </div>

                <!-- Category -->
                <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60 p-6">
                    <h2 class="text-sm font-semibold text-white mb-5">Category</h2>

                    <select
                        v-model="form.category_id"
                        class="w-full rounded-xl border border-navy-700/50 bg-navy-800/50 px-4 py-2.5 text-sm text-white focus:border-brand-500/50 focus:outline-none focus:ring-1 focus:ring-brand-500/30"
                        :class="{ 'border-red-500/50': form.errors.category_id }"
                    >
                        <option value="" class="bg-navy-900">No category</option>
                        <option
                            v-for="cat in categories"
                            :key="cat.id"
                            :value="cat.id"
                            class="bg-navy-900"
                        >
                            {{ cat.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.category_id" class="mt-1 text-xs text-red-400">{{ form.errors.category_id }}</p>
                </div>

                <!-- Save -->
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-400 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    {{ form.processing ? 'Creating…' : 'Create Product' }}
                </button>

                <a
                    href="/vendor/products"
                    class="flex w-full items-center justify-center rounded-xl border border-navy-700 px-4 py-2.5 text-sm font-medium text-navy-300 hover:border-navy-600 hover:text-white transition-colors"
                >
                    Cancel
                </a>
            </div>
        </form>
    </VendorLayout>
</template>
