<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';

interface Answer {
    id: number;
    body: string;
    is_vendor_answer: boolean;
    author_name: string;
    created_at: string;
}

interface QuestionRow {
    id: number;
    body: string;
    is_answered: boolean;
    author_name: string;
    created_at: string;
    product: { id: number; name: string; slug: string };
    answers: Answer[];
}

interface PaginatedQuestions {
    data: QuestionRow[];
    links: { url: string | null; label: string; active: boolean }[];
    meta: { current_page: number; last_page: number; total: number };
}

interface Props {
    questions: PaginatedQuestions;
    filter: string;
    unanswered_count: number;
}

const props = defineProps<Props>();

const expandedAnswer = ref<number | null>(null);

function toggleAnswer(id: number) {
    expandedAnswer.value = expandedAnswer.value === id ? null : id;
}

const answerForms = ref<Record<number, ReturnType<typeof useForm>>>({});

function getForm(questionId: number) {
    if (!answerForms.value[questionId]) {
        answerForms.value[questionId] = useForm({ body: '' });
    }

    return answerForms.value[questionId];
}

function submitAnswer(question: QuestionRow) {
    const form = getForm(question.id);
    form.post(route('vendor.questions.answer', { question: question.id }), {
        onSuccess: () => {
            expandedAnswer.value = null;
        },
    });
}

function setFilter(f: string) {
    router.get(route('vendor.questions.index'), { filter: f }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Product Q&amp;A" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Q&amp;A</span>
            </div>
        </template>

        <div class="mb-6 flex items-start justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Product Q&amp;A</h1>
                <p class="mt-1 text-sm text-navy-400">Answer customer questions about your products</p>
            </div>
            <div v-if="unanswered_count > 0" class="rounded-xl bg-amber-500/15 border border-amber-500/20 px-4 py-2 text-sm font-medium text-amber-400">
                {{ unanswered_count }} unanswered
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="flex gap-1 mb-4 bg-navy-900/60 border border-navy-800/60 rounded-xl p-1 w-fit">
            <button
                @click="setFilter('unanswered')"
                :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors', filter === 'unanswered' ? 'bg-brand-500 text-white' : 'text-navy-400 hover:text-white']"
            >
                Unanswered
                <span v-if="unanswered_count" class="ml-1.5 text-xs rounded-full px-1.5 py-0.5" :class="filter === 'unanswered' ? 'bg-white/20 text-white' : 'bg-navy-700 text-navy-300'">
                    {{ unanswered_count }}
                </span>
            </button>
            <button
                @click="setFilter('all')"
                :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors', filter === 'all' ? 'bg-brand-500 text-white' : 'text-navy-400 hover:text-white']"
            >
                All Questions
            </button>
        </div>

        <!-- Questions list -->
        <div class="bento rounded-2xl border border-navy-800/60 bg-navy-900/60">
            <div v-if="questions.data.length === 0" class="p-10 text-center text-navy-500 text-sm">
                No questions found.
            </div>

            <div v-else class="divide-y divide-navy-800/40">
                <div v-for="q in questions.data" :key="q.id" class="p-5">
                    <!-- Question header -->
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-500/20 text-brand-400 text-xs font-bold">Q</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-white text-sm font-medium">{{ q.body }}</p>
                            <div class="mt-1 flex items-center gap-3 text-xs text-navy-500 flex-wrap">
                                <span>{{ q.author_name }}</span>
                                <span>·</span>
                                <span>{{ q.created_at }}</span>
                                <span>·</span>
                                <Link
                                    :href="route('products.show', { locale: 'en', product: q.product.slug })"
                                    class="text-brand-400 hover:text-brand-300 transition-colors"
                                >
                                    {{ q.product.name }}
                                </Link>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs border"
                                    :class="q.is_answered
                                        ? 'bg-accent-500/15 text-accent-400 border-accent-500/20'
                                        : 'bg-amber-500/15 text-amber-400 border-amber-500/20'"
                                >
                                    {{ q.is_answered ? 'Answered' : 'Unanswered' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Existing answers -->
                    <div v-if="q.answers.length > 0" class="mt-3 pl-9 space-y-2">
                        <div v-for="a in q.answers" :key="a.id" class="flex gap-2">
                            <span
                                class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                :class="a.is_vendor_answer ? 'bg-accent-500/20 text-accent-400' : 'bg-navy-700 text-navy-400'"
                            >A</span>
                            <div>
                                <p class="text-sm text-navy-300">{{ a.body }}</p>
                                <p class="text-xs text-navy-600 mt-0.5">
                                    <span v-if="a.is_vendor_answer" class="text-accent-500 font-medium mr-1">Seller</span>
                                    {{ a.author_name }} · {{ a.created_at }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Answer form -->
                    <div class="mt-3 pl-9">
                        <div v-if="expandedAnswer !== q.id">
                            <button
                                @click="toggleAnswer(q.id)"
                                class="text-xs text-brand-400 hover:text-brand-300 font-medium transition-colors"
                            >
                                + Post an answer
                            </button>
                        </div>
                        <div v-else class="space-y-2">
                            <textarea
                                v-model="getForm(q.id).body"
                                rows="3"
                                placeholder="Write your answer…"
                                class="block w-full rounded-xl border border-navy-700 bg-navy-800 text-sm px-4 py-2.5 text-white placeholder-navy-500 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 resize-none"
                            />
                            <div class="flex gap-2">
                                <button
                                    @click="submitAnswer(q)"
                                    :disabled="!getForm(q.id).body.trim() || getForm(q.id).processing"
                                    class="rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-xs font-semibold px-4 py-2 transition-colors disabled:opacity-50"
                                >
                                    {{ getForm(q.id).processing ? 'Posting…' : 'Post Answer' }}
                                </button>
                                <button
                                    @click="expandedAnswer = null"
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
            <div v-if="questions.meta.last_page > 1" class="flex items-center justify-center gap-1 p-4 border-t border-navy-800/60">
                <Link
                    v-for="link in questions.links"
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
