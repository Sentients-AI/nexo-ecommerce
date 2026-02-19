<script setup lang="ts">
import { ref } from 'vue';
import StarRating from './StarRating.vue';
import Spinner from '@/Components/UI/Spinner.vue';
import { useApi } from '@/Composables/useApi';
import { useLocale } from '@/Composables/useLocale';
import type { ReviewApiResource } from '@/types/api';

interface Props {
    productSlug: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    submitted: [review: ReviewApiResource];
}>();

const { t } = useLocale();
const { post, loading, error, clearError } = useApi();

const rating = ref(0);
const title = ref('');
const body = ref('');
const submitted = ref(false);

async function handleSubmit() {
    if (rating.value === 0 || !title.value || !body.value) {
        return;
    }

    clearError();

    const result = await post<{ success: boolean; data: ReviewApiResource }>(
        `/api/v1/products/${props.productSlug}/reviews`,
        {
            rating: rating.value,
            title: title.value,
            body: body.value,
        }
    );

    if (result?.success && result.data) {
        submitted.value = true;
        emit('submitted', result.data);
        rating.value = 0;
        title.value = '';
        body.value = '';

        setTimeout(() => {
            submitted.value = false;
        }, 3000);
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

            <!-- Error -->
            <div v-if="error" class="rounded-lg bg-red-50 dark:bg-red-900/50 p-3 border border-red-200 dark:border-red-800">
                <p class="text-sm text-red-700 dark:text-red-200">{{ error.message }}</p>
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
