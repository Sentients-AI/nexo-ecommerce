<script setup lang="ts">
import { ref } from 'vue';
import StarRating from './StarRating.vue';
import Spinner from '@/Components/UI/Spinner.vue';
import { useLocale } from '@/Composables/useLocale';
import type { ReviewApiResource } from '@/types/api';
import axios from 'axios';

interface Props {
    productSlug: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    submitted: [review: ReviewApiResource];
}>();

const { t } = useLocale();

const rating = ref(0);
const title = ref('');
const body = ref('');
const photos = ref<File[]>([]);
const photoPreviews = ref<string[]>([]);
const submitted = ref(false);
const loading = ref(false);
const errorMessage = ref('');

function handlePhotoSelect(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (!input.files) {
        return;
    }

    const newFiles = Array.from(input.files);
    const combined = [...photos.value, ...newFiles].slice(0, 5);
    photos.value = combined;

    photoPreviews.value = [];
    for (const file of combined) {
        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreviews.value.push(e.target?.result as string);
        };
        reader.readAsDataURL(file);
    }

    input.value = '';
}

function removePhoto(index: number): void {
    photos.value.splice(index, 1);
    photoPreviews.value.splice(index, 1);
}

async function handleSubmit(): Promise<void> {
    if (rating.value === 0 || !title.value || !body.value) {
        return;
    }

    loading.value = true;
    errorMessage.value = '';

    try {
        const formData = new FormData();
        formData.append('rating', String(rating.value));
        formData.append('title', title.value);
        formData.append('body', body.value);

        for (const file of photos.value) {
            formData.append('photos[]', file);
        }

        const response = await axios.post<{ success: boolean; data: ReviewApiResource }>(
            `/api/v1/products/${props.productSlug}/reviews`,
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        );

        if (response.data.success && response.data.data) {
            submitted.value = true;
            emit('submitted', response.data.data);
            rating.value = 0;
            title.value = '';
            body.value = '';
            photos.value = [];
            photoPreviews.value = [];

            setTimeout(() => {
                submitted.value = false;
            }, 3000);
        }
    } catch (err: any) {
        errorMessage.value = err.response?.data?.error?.message
            ?? err.response?.data?.message
            ?? 'Something went wrong. Please try again.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            {{ t('reviews.write_review') }}
        </h3>

        <form @submit.prevent="handleSubmit" class="space-y-4">
            <!-- Rating -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ t('reviews.your_rating') }}
                </label>
                <StarRating v-model:rating="rating" :interactive="true" size="lg" />
            </div>

            <!-- Title -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ t('reviews.review_title') }}
                </label>
                <input
                    v-model="title"
                    type="text"
                    required
                    maxlength="255"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <!-- Body -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ t('reviews.review_body') }}
                </label>
                <textarea
                    v-model="body"
                    required
                    rows="4"
                    maxlength="5000"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <!-- Photos -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Photos <span class="text-gray-400 text-xs">(optional, up to 5)</span>
                </label>

                <!-- Previews -->
                <div v-if="photoPreviews.length > 0" class="flex flex-wrap gap-2 mb-2">
                    <div
                        v-for="(preview, index) in photoPreviews"
                        :key="index"
                        class="relative"
                    >
                        <img
                            :src="preview"
                            :alt="`Photo ${index + 1}`"
                            class="h-16 w-16 rounded-lg object-cover border border-gray-200 dark:border-gray-600"
                        />
                        <button
                            type="button"
                            class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white text-xs hover:bg-red-600"
                            @click="removePhoto(index)"
                        >
                            ×
                        </button>
                    </div>
                </div>

                <label
                    v-if="photos.length < 5"
                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 px-4 py-3 text-sm text-gray-500 dark:text-gray-400 hover:border-indigo-400 hover:text-indigo-500 transition-colors"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add photos
                    <input
                        type="file"
                        class="sr-only"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        @change="handlePhotoSelect"
                    />
                </label>
            </div>

            <!-- Error -->
            <div v-if="errorMessage" class="rounded-lg bg-red-50 dark:bg-red-900/50 p-3 border border-red-200 dark:border-red-800">
                <p class="text-sm text-red-700 dark:text-red-200">{{ errorMessage }}</p>
            </div>

            <!-- Success -->
            <div v-if="submitted" class="rounded-lg bg-green-50 dark:bg-green-900/50 p-3 border border-green-200 dark:border-green-800">
                <p class="text-sm text-green-700 dark:text-green-200">{{ t('reviews.submitted') }}</p>
            </div>

            <!-- Submit -->
            <button
                type="submit"
                :disabled="loading || rating === 0 || !title || !body"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                <Spinner v-if="loading" size="sm" color="white" />
                {{ t('reviews.submit') }}
            </button>
        </form>
    </div>
</template>
