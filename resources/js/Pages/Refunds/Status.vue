<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import { useLocale } from '@/Composables/useLocale';
import { RefundStatus } from '@/types/models';

interface Refund {
    id: number;
    order_id: number;
    amount_cents: number;
    currency: string;
    status: string;
    reason: string | null;
    created_at: string;
    approved_at: string | null;
    processed_at?: string | null;
}

interface Order {
    id: number;
    order_number: string;
    status: string;
}

interface Props {
    refund: Refund;
    order: Order;
}

const props = defineProps<Props>();
const { localePath } = useLocale();

function formatPrice(cents: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function formatDateTime(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatStatus(status: string): string {
    return status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

const statusIcon = computed(() => {
    switch (props.refund.status) {
        case RefundStatus.Succeeded:
            return 'success';
        case RefundStatus.Processing:
        case RefundStatus.Approved:
        case RefundStatus.Requested:
        case RefundStatus.PendingApproval:
            return 'pending';
        case RefundStatus.Failed:
        case RefundStatus.Rejected:
        case RefundStatus.Cancelled:
            return 'failed';
        default:
            return 'pending';
    }
});

const statusMessage = computed(() => {
    switch (props.refund.status) {
        case RefundStatus.Succeeded:
            return 'Your refund has been processed successfully. The funds should appear in your account within 5-10 business days.';
        case RefundStatus.Processing:
            return 'Your refund is being processed. This typically takes 3-5 business days.';
        case RefundStatus.Approved:
            return 'Great news! Your refund has been approved and will be processed shortly.';
        case RefundStatus.Requested:
        case RefundStatus.PendingApproval:
            return 'Your refund request is being reviewed by our team. We\'ll notify you once it\'s processed.';
        case RefundStatus.Failed:
            return 'There was an issue processing your refund. Please contact our support team for assistance.';
        case RefundStatus.Rejected:
            return 'Unfortunately, your refund request was not approved. Please contact support if you have questions.';
        case RefundStatus.Cancelled:
            return 'This refund request has been cancelled.';
        default:
            return 'Your refund status is being updated.';
    }
});

// Build timeline events
const timelineEvents = computed(() => {
    const events: Array<{
        title: string;
        description: string;
        date: string | null;
        completed: boolean;
        current: boolean;
        failed: boolean;
    }> = [];

    // Step 1: Requested
    events.push({
        title: 'Refund Requested',
        description: 'Your refund request has been submitted',
        date: props.refund.created_at,
        completed: true,
        current: props.refund.status === RefundStatus.Requested || props.refund.status === RefundStatus.PendingApproval,
        failed: false,
    });

    // Step 2: Approved or Rejected
    const isRejected = props.refund.status === RefundStatus.Rejected;
    const isCancelled = props.refund.status === RefundStatus.Cancelled;
    const isApprovedOrBeyond = [RefundStatus.Approved, RefundStatus.Processing, RefundStatus.Succeeded].includes(props.refund.status as RefundStatus);

    if (isRejected || isCancelled) {
        events.push({
            title: isRejected ? 'Request Rejected' : 'Request Cancelled',
            description: isRejected ? 'Your refund request was not approved' : 'This refund request was cancelled',
            date: props.refund.approved_at,
            completed: true,
            current: true,
            failed: true,
        });
    } else {
        events.push({
            title: 'Approved',
            description: 'Your refund request has been approved',
            date: props.refund.approved_at,
            completed: isApprovedOrBeyond || !!props.refund.approved_at,
            current: props.refund.status === RefundStatus.Approved,
            failed: false,
        });
    }

    // Step 3: Processing (only show if not rejected/cancelled)
    if (!isRejected && !isCancelled) {
        const isProcessingOrBeyond = [RefundStatus.Processing, RefundStatus.Succeeded].includes(props.refund.status as RefundStatus);
        events.push({
            title: 'Processing',
            description: 'Your refund is being processed',
            date: null,
            completed: isProcessingOrBeyond,
            current: props.refund.status === RefundStatus.Processing,
            failed: props.refund.status === RefundStatus.Failed,
        });
    }

    // Step 4: Completed (only show if not rejected/cancelled)
    if (!isRejected && !isCancelled) {
        const isFailed = props.refund.status === RefundStatus.Failed;
        events.push({
            title: isFailed ? 'Failed' : 'Completed',
            description: isFailed ? 'There was an issue processing your refund' : 'Your refund has been successfully processed',
            date: props.refund.processed_at,
            completed: props.refund.status === RefundStatus.Succeeded,
            current: props.refund.status === RefundStatus.Succeeded || isFailed,
            failed: isFailed,
        });
    }

    return events;
});

const progressPercentage = computed(() => {
    const completedSteps = timelineEvents.value.filter(e => e.completed).length;
    const totalSteps = timelineEvents.value.length;
    return (completedSteps / totalSteps) * 100;
});
</script>

<template>
    <Head title="Refund Status" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Back link -->
            <Link
                :href="`/orders/${order.id}`"
                class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                Back to Order
            </Link>

            <!-- Status header card -->
            <div class="mt-8 rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-navy-900/60 overflow-hidden shadow-sm">
                <div
                    :class="[
                        'px-6 py-8 text-center',
                        statusIcon === 'success' ? 'bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20' : '',
                        statusIcon === 'pending' ? 'bg-gradient-to-br from-yellow-50 to-amber-50 dark:from-yellow-900/20 dark:to-amber-900/20' : '',
                        statusIcon === 'failed' ? 'bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20' : '',
                    ]"
                >
                    <!-- Animated status icon -->
                    <div class="relative mx-auto h-20 w-20">
                        <!-- Pulse animation for pending -->
                        <div
                            v-if="statusIcon === 'pending'"
                            class="absolute inset-0 rounded-full bg-yellow-400/30 animate-ping"
                        />
                        <div
                            :class="[
                                'relative flex h-20 w-20 items-center justify-center rounded-full',
                                statusIcon === 'success' ? 'bg-green-100 dark:bg-green-900/50' : '',
                                statusIcon === 'pending' ? 'bg-yellow-100 dark:bg-yellow-900/50' : '',
                                statusIcon === 'failed' ? 'bg-red-100 dark:bg-red-900/50' : '',
                            ]"
                        >
                            <!-- Success icon with draw animation -->
                            <svg
                                v-if="statusIcon === 'success'"
                                class="h-10 w-10 text-green-600 dark:text-green-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4.5 12.75l6 6 9-13.5"
                                    class="animate-[draw_0.5s_ease-out_forwards]"
                                    style="stroke-dasharray: 24; stroke-dashoffset: 24; animation: draw 0.5s ease-out 0.3s forwards;"
                                />
                            </svg>
                            <!-- Pending icon -->
                            <svg
                                v-else-if="statusIcon === 'pending'"
                                class="h-10 w-10 text-yellow-600 dark:text-yellow-400 animate-pulse"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <!-- Failed icon -->
                            <svg
                                v-else
                                class="h-10 w-10 text-red-600 dark:text-red-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                    </div>

                    <h1 class="mt-6 text-2xl font-bold text-slate-900 dark:text-white">
                        Refund {{ formatStatus(refund.status) }}
                    </h1>
                    <p class="mt-2 text-slate-600 dark:text-slate-400 max-w-md mx-auto">
                        {{ statusMessage }}
                    </p>

                    <!-- Amount badge -->
                    <div class="mt-6 inline-flex items-center gap-2 rounded-full bg-white dark:bg-navy-900/60 px-4 py-2 shadow-sm border border-slate-100 dark:border-navy-800/60">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Amount:</span>
                        <span class="text-lg font-bold text-slate-900 dark:text-white">{{ formatPrice(refund.amount_cents) }}</span>
                    </div>
                </div>

                <!-- Progress bar -->
                <div class="px-6 py-4 bg-slate-50 dark:bg-navy-900/80">
                    <div class="flex items-center justify-between text-sm mb-2">
                        <span class="text-slate-500 dark:text-slate-400">Progress</span>
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ Math.round(progressPercentage) }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-200 dark:bg-navy-800 overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-500 ease-out"
                            :class="[
                                statusIcon === 'success' ? 'bg-green-500' : '',
                                statusIcon === 'pending' ? 'bg-yellow-500' : '',
                                statusIcon === 'failed' ? 'bg-red-500' : '',
                            ]"
                            :style="{ width: `${progressPercentage}%` }"
                        />
                    </div>
                </div>
            </div>

            <!-- Enhanced Timeline -->
            <div class="mt-8 rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-navy-900/60 overflow-hidden shadow-sm">
                <div class="border-b border-slate-100 dark:border-navy-800/60 px-6 py-4 bg-slate-50 dark:bg-navy-900/80">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Timeline</h2>
                </div>
                <div class="px-6 py-6">
                    <ol class="relative">
                        <li
                            v-for="(event, index) in timelineEvents"
                            :key="index"
                            class="relative pb-8 last:pb-0"
                        >
                            <!-- Connecting line -->
                            <div
                                v-if="index < timelineEvents.length - 1"
                                :class="[
                                    'absolute left-4 top-10 -ml-px h-full w-0.5 transition-colors duration-300',
                                    event.completed && timelineEvents[index + 1]?.completed && !event.failed
                                        ? 'bg-green-500'
                                        : event.failed
                                            ? 'bg-red-500'
                                            : 'bg-gray-200 dark:bg-navy-800'
                                ]"
                            />

                            <div class="relative flex items-start gap-4">
                                <!-- Step indicator -->
                                <div
                                    :class="[
                                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white dark:ring-navy-900 transition-all duration-300',
                                        event.completed && !event.failed
                                            ? 'bg-green-500 text-white'
                                            : event.failed
                                                ? 'bg-red-500 text-white'
                                                : event.current
                                                    ? 'bg-yellow-500 text-white'
                                                    : 'bg-slate-100 dark:bg-navy-800 text-slate-400'
                                    ]"
                                >
                                    <!-- Completed check -->
                                    <svg
                                        v-if="event.completed && !event.failed"
                                        class="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="2.5"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    <!-- Failed X -->
                                    <svg
                                        v-else-if="event.failed"
                                        class="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="2.5"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <!-- Current pulse -->
                                    <div
                                        v-else-if="event.current"
                                        class="h-2.5 w-2.5 rounded-full bg-white animate-pulse"
                                    />
                                    <!-- Step number -->
                                    <span v-else class="text-xs font-medium">{{ index + 1 }}</span>
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0 pt-0.5">
                                    <p
                                        :class="[
                                            'font-medium',
                                            event.completed || event.current
                                                ? 'text-slate-900 dark:text-white'
                                                : 'text-slate-500 dark:text-slate-400'
                                        ]"
                                    >
                                        {{ event.title }}
                                    </p>
                                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                        {{ event.description }}
                                    </p>
                                    <p v-if="event.date && event.completed" class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                                        {{ formatDateTime(event.date) }}
                                    </p>
                                </div>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>

            <!-- Refund details -->
            <div class="mt-8 rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-navy-900/60 overflow-hidden shadow-sm">
                <div class="border-b border-slate-100 dark:border-navy-800/60 px-6 py-4 bg-slate-50 dark:bg-navy-900/80 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Refund Details</h2>
                    <StatusBadge type="refund" :status="refund.status" />
                </div>
                <div class="px-6 py-5">
                    <dl class="space-y-4">
                        <div class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Refund ID</dt>
                            <dd class="text-sm font-medium text-slate-900 dark:text-white">#{{ refund.id }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Order</dt>
                            <dd class="text-sm font-medium">
                                <Link
                                    :href="`/orders/${order.id}`"
                                    class="text-brand-600 hover:text-brand-500 dark:text-brand-400 dark:hover:text-brand-300 transition-colors"
                                >
                                    {{ order.order_number }}
                                </Link>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Amount</dt>
                            <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ formatPrice(refund.amount_cents) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Requested</dt>
                            <dd class="text-sm text-slate-900 dark:text-white">{{ formatDateTime(refund.created_at) }}</dd>
                        </div>
                        <div v-if="refund.approved_at" class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Approved</dt>
                            <dd class="text-sm text-slate-900 dark:text-white">{{ formatDateTime(refund.approved_at) }}</dd>
                        </div>
                        <div v-if="refund.reason" class="pt-4 border-t border-slate-100 dark:border-navy-800/60">
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-2">Reason</dt>
                            <dd class="text-sm text-slate-900 dark:text-white bg-gray-50 dark:bg-navy-800/50 rounded-lg p-3">
                                {{ refund.reason }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Need help section -->
            <div class="mt-8 rounded-2xl border border-brand-100 dark:border-brand-900/50 bg-brand-50 dark:bg-brand-900/20 p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900/50">
                        <svg class="h-5 w-5 text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-brand-900 dark:text-brand-100">Have questions?</h3>
                        <p class="mt-1 text-sm text-brand-700 dark:text-brand-300">
                            If you have any questions about your refund or need assistance, our support team is here to help.
                        </p>
                        <Link
                            :href="localePath('/contact')"
                            class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-brand-600 dark:text-brand-400 hover:text-indigo-500 transition-colors"
                        >
                            Contact Support
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-8 flex flex-col sm:flex-row justify-center gap-4">
                <Link
                    :href="`/orders/${order.id}`"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-900/60 px-6 py-3 text-base font-medium text-slate-700 dark:text-slate-300 shadow-sm hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    View Order
                </Link>
                <Link
                    :href="localePath('/orders')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-brand-400 transition-colors"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                    All Orders
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes draw {
    to {
        stroke-dashoffset: 0;
    }
}
</style>
