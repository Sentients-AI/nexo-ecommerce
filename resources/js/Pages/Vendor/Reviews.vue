<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';

interface Reply {
    id: number;
    body: string;
    is_merchant_reply: boolean;
    author_name: string;
    created_at: string;
}

interface ReviewRow {
    id: number;
    rating: number;
    title: string | null;
    body: string | null;
    author_name: string;
    created_at: string;
    product: { id: number; name: string; slug: string };
    replies: Reply[];
}

interface PaginatedReviews {
    data: ReviewRow[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    reviews: PaginatedReviews;
    filter: string;
    unreplied_count: number;
}

const props = defineProps<Props>();

const expandedReply = ref<number | null>(null);

function toggleReply(id: number) {
    expandedReply.value = expandedReply.value === id ? null : id;
}

const replyForms = ref<Record<number, ReturnType<typeof useForm>>>({});

function getForm(reviewId: number) {
    if (!replyForms.value[reviewId]) {
        replyForms.value[reviewId] = useForm({ body: '' });
    }
    return replyForms.value[reviewId];
}

function submitReply(review: ReviewRow) {
    const form = getForm(review.id);
    form.post(`/vendor/reviews/${review.id}/reply`, {
        onSuccess: () => {
            expandedReply.value = null;
        },
    });
}

function setFilter(f: string) {
    router.get('/vendor/reviews', { filter: f }, { preserveState: true, replace: true });
}

function stars(rating: number): string {
    return '★'.repeat(rating) + '☆'.repeat(5 - rating);
}

function starColor(rating: number): string {
    if (rating >= 4) return 'text-amber-400';
    if (rating === 3) return 'text-yellow-500';
    return 'text-red-400';
}
</script>

<template>
    <Head title="Reviews" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Reviews</span>
            </div>
        </template>

        <div class="mb-6 flex items-start justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Product Reviews</h1>
                <p class="mt-1 text-sm text-navy-400">Reply to customer reviews and manage feedback</p>
            </div>
            <div v-if="unreplied_count > 0" class="rounded-xl bg-amber-500/15 border border-amber-500/20 px-4 py-2 text-sm font-medium text-amber-400">
                {{ unreplied_count }} awaiting reply
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="flex gap-1 mb-4 bg-navy-900/60 border border-navy-800/60 rounded-xl p-1 w-fit">
            <button
                @click="setFilter('unreplied')"
                :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors', filter === 'unreplied' ? 'bg-brand-500 text-white' : 'text-navy-400 hover:text-white']"
            >
                Unreplied
                <span v-if="unreplied_count" class="ml-1.5 text-xs rounded-full px-1.5 py-0.5" :class="filter === 'unreplied' ? 'bg-white/20 text-white' : 'bg-navy-700 text-navy-300'">
                    {{ unreplied_count }}
                </span>
            </button>
            <button
                @click="setFilter('all')"
                :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors', filter === 'all' ? 'bg-brand-500 text-white' : 'text-navy-400 hover:text-white']"
            >
                All Reviews
            </button>
        </div>

        <!-- Reviews list -->
        <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60">
            <div v-if="reviews.data.length === 0" class="p-10 text-center text-navy-500 text-sm">
                No reviews found.
            </div>

            <div v-else class="divide-y divide-navy-800/40">
                <div v-for="review in reviews.data" :key="review.id" class="p-5">
                    <!-- Review header -->
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <!-- Stars + meta row -->
                            <div class="flex items-center gap-3 flex-wrap">
                                <span :class="['text-base tracking-tight font-mono', starColor(review.rating)]">{{ stars(review.rating) }}</span>
                                <span class="text-xs text-navy-500">{{ review.author_name }}</span>
                                <span class="text-xs text-navy-600">·</span>
                                <span class="text-xs text-navy-500">{{ review.created_at }}</span>
                                <span class="text-xs text-navy-600">·</span>
                                <Link
                                    :href="`/en/products/${review.product.slug}`"
                                    class="text-xs text-brand-400 hover:text-brand-300 transition-colors"
                                >
                                    {{ review.product.name }}
                                </Link>
                                <span
                                    v-if="review.replies.some(r => r.is_merchant_reply)"
                                    class="rounded-full px-2 py-0.5 text-xs border bg-accent-500/15 text-accent-400 border-accent-500/20"
                                >
                                    Replied
                                </span>
                            </div>

                            <!-- Review content -->
                            <p v-if="review.title" class="mt-2 text-sm font-semibold text-white">{{ review.title }}</p>
                            <p v-if="review.body" class="mt-1 text-sm text-navy-300">{{ review.body }}</p>
                        </div>
                    </div>

                    <!-- Existing replies -->
                    <div v-if="review.replies.length > 0" class="mt-3 pl-0 space-y-2">
                        <div v-for="reply in review.replies" :key="reply.id" class="flex gap-2">
                            <span
                                class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                :class="reply.is_merchant_reply ? 'bg-accent-500/20 text-accent-400' : 'bg-navy-700 text-navy-400'"
                            >R</span>
                            <div>
                                <p class="text-sm text-navy-300">{{ reply.body }}</p>
                                <p class="text-xs text-navy-600 mt-0.5">
                                    <span v-if="reply.is_merchant_reply" class="text-accent-500 font-medium mr-1">Seller</span>
                                    {{ reply.author_name }} · {{ reply.created_at }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Reply form -->
                    <div class="mt-3">
                        <div v-if="expandedReply !== review.id">
                            <button
                                @click="toggleReply(review.id)"
                                class="text-xs text-brand-400 hover:text-brand-300 font-medium transition-colors"
                            >
                                + Post a reply
                            </button>
                        </div>
                        <div v-else class="space-y-2">
                            <textarea
                                v-model="getForm(review.id).body"
                                rows="3"
                                placeholder="Write your reply…"
                                class="block w-full rounded-xl border border-navy-700 bg-navy-800 text-sm px-4 py-2.5 text-white placeholder-navy-500 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 resize-none"
                            />
                            <div class="flex gap-2">
                                <button
                                    @click="submitReply(review)"
                                    :disabled="!getForm(review.id).body.trim() || getForm(review.id).processing"
                                    class="rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-xs font-semibold px-4 py-2 transition-colors disabled:opacity-50"
                                >
                                    {{ getForm(review.id).processing ? 'Posting…' : 'Post Reply' }}
                                </button>
                                <button
                                    @click="expandedReply = null"
                                    class="rounded-lg text-navy-400 hover:text-white text-xs font-medium px-3 py-2 transition-colors"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="reviews.last_page > 1" class="flex items-center justify-center gap-1 p-4 border-t border-navy-800/60">
                <Link
                    v-for="link in reviews.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                        link.active ? 'bg-brand-500 text-white' : link.url ? 'text-navy-400 hover:bg-navy-800 hover:text-white' : 'text-navy-600 cursor-default pointer-events-none',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </VendorLayout>
</template>
