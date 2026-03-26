<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import StarRating from '@/Components/Reviews/StarRating.vue';
import ReviewReplyItem from '@/Components/Reviews/ReviewReplyItem.vue';
import type { ReviewApiResource, ReviewReplyApiResource } from '@/types/api';

const props = defineProps<{
    review: ReviewApiResource;
    currentUserId?: number;
    isAuthenticated: boolean;
}>();

const emit = defineEmits<{
    (e: 'reply-added', reviewId: number, reply: ReviewReplyApiResource): void;
    (e: 'vote-updated', reviewId: number, helpfulCount: number, notHelpfulCount: number, userVote: boolean | null): void;
}>();

const showReplyForm = ref(false);
const replyBody = ref('');
const replySubmitting = ref(false);
const replyError = ref('');

const localHelpfulCount = ref(props.review.helpful_count ?? 0);
const localNotHelpfulCount = ref(props.review.not_helpful_count ?? 0);
const localUserVote = ref<boolean | null | undefined>(props.review.user_vote);
const voteSubmitting = ref(false);

const formatDate = (dateStr: string): string => {
    return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

const canVote = (): boolean => {
    return props.isAuthenticated && props.review.user_id !== props.currentUserId;
};

const submitVote = async (isHelpful: boolean): Promise<void> => {
    if (!canVote() || voteSubmitting.value) {
        return;
    }

    voteSubmitting.value = true;

    try {
        const response = await axios.post(`/api/v1/reviews/${props.review.id}/vote`, { is_helpful: isHelpful });
        const data = response.data.data;
        localHelpfulCount.value = data.helpful_count;
        localNotHelpfulCount.value = data.not_helpful_count;
        localUserVote.value = data.user_vote;
        emit('vote-updated', props.review.id, data.helpful_count, data.not_helpful_count, data.user_vote);
    } catch {
        // silent fail for votes
    } finally {
        voteSubmitting.value = false;
    }
};

const submitReply = async (): Promise<void> => {
    if (!replyBody.value.trim() || replySubmitting.value) {
        return;
    }

    replySubmitting.value = true;
    replyError.value = '';

    try {
        const response = await axios.post(`/api/v1/reviews/${props.review.id}/replies`, { body: replyBody.value });
        const reply = response.data.data as ReviewReplyApiResource;
        emit('reply-added', props.review.id, reply);
        replyBody.value = '';
        showReplyForm.value = false;
    } catch (err: any) {
        replyError.value = err.response?.data?.message ?? 'Failed to post reply. Please try again.';
    } finally {
        replySubmitting.value = false;
    }
};
</script>

<template>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <!-- Header -->
        <div class="mb-3 flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900">
                    <span class="text-sm font-semibold text-indigo-700 dark:text-indigo-300">
                        {{ (review.user_name ?? '?')[0].toUpperCase() }}
                    </span>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ review.user_name ?? 'Anonymous' }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(review.created_at) }}</p>
                </div>
            </div>
            <StarRating :rating="review.rating" size="sm" />
        </div>

        <!-- Content -->
        <h4 class="font-semibold text-gray-900 dark:text-white">{{ review.title }}</h4>
        <p class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ review.body }}</p>

        <!-- Photos -->
        <div v-if="review.photos && review.photos.length > 0" class="mt-3 flex flex-wrap gap-2">
            <a
                v-for="photo in review.photos"
                :key="photo.id"
                :href="photo.url"
                target="_blank"
                rel="noopener noreferrer"
            >
                <img
                    :src="photo.url"
                    :alt="`Review photo ${photo.order + 1}`"
                    class="h-20 w-20 rounded-lg border border-gray-200 object-cover transition-opacity hover:opacity-90 dark:border-gray-700"
                />
            </a>
        </div>

        <!-- Helpful Voting -->
        <div class="mt-4 flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
            <span>Was this review helpful?</span>
            <button
                v-if="canVote()"
                class="flex items-center gap-1.5 rounded-full border px-3 py-1 transition-colors"
                :class="localUserVote === true
                    ? 'border-green-400 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400'
                    : 'border-gray-300 dark:border-gray-600 hover:border-green-400 hover:text-green-600'"
                :disabled="voteSubmitting"
                @click="submitVote(true)"
            >
                👍 Yes ({{ localHelpfulCount }})
            </button>
            <button
                v-if="canVote()"
                class="flex items-center gap-1.5 rounded-full border px-3 py-1 transition-colors"
                :class="localUserVote === false
                    ? 'border-red-400 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400'
                    : 'border-gray-300 dark:border-gray-600 hover:border-red-400 hover:text-red-600'"
                :disabled="voteSubmitting"
                @click="submitVote(false)"
            >
                👎 No ({{ localNotHelpfulCount }})
            </button>
            <span v-if="!canVote() && (localHelpfulCount > 0 || localNotHelpfulCount > 0)">
                {{ localHelpfulCount }} found this helpful
            </span>
        </div>

        <!-- Replies -->
        <div v-if="review.replies && review.replies.length > 0" class="mt-4 space-y-1 border-l-2 border-gray-200 pl-4 dark:border-gray-700">
            <ReviewReplyItem v-for="reply in review.replies" :key="reply.id" :reply="reply" />
        </div>

        <!-- Reply Form -->
        <div v-if="isAuthenticated" class="mt-4">
            <button
                v-if="!showReplyForm"
                class="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                @click="showReplyForm = true"
            >
                Reply
            </button>
            <div v-else class="space-y-2">
                <textarea
                    v-model="replyBody"
                    rows="3"
                    maxlength="2000"
                    placeholder="Write a reply..."
                    class="w-full resize-none rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-transparent focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                />
                <p v-if="replyError" class="text-xs text-red-500">{{ replyError }}</p>
                <div class="flex gap-2">
                    <button
                        class="rounded-lg bg-indigo-600 px-4 py-1.5 text-sm text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="replySubmitting || !replyBody.trim()"
                        @click="submitReply"
                    >
                        {{ replySubmitting ? 'Posting...' : 'Post Reply' }}
                    </button>
                    <button
                        class="px-4 py-1.5 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                        @click="showReplyForm = false; replyBody = ''; replyError = ''"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
