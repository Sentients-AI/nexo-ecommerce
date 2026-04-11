<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface Plan {
    id: number;
    name: string;
    description: string | null;
    billing_interval: 'monthly' | 'annual';
    interval_label: string;
    price_cents: number;
    formatted_price: string;
    features: string[];
    stripe_price_id: string;
}

interface ActiveSubscription {
    id: number;
    stripe_price: string;
    stripe_status: string;
    ends_at: string | null;
    trial_ends_at: string | null;
    on_grace_period: boolean;
    cancelled: boolean;
}

const props = defineProps<{
    plans: Plan[];
    subscriptions: ActiveSubscription[];
    has_payment_method: boolean;
}>();

const page = usePage();
const locale = computed(() => (page.props as any).locale ?? 'en');

const isSubscribed = computed(() => props.subscriptions.length > 0);

const activeSub = computed(() => props.subscriptions[0] ?? null);

const subscribedPriceId = computed(() =>
    props.subscriptions.map(s => s.stripe_price)
);

function subscribeTo(plan: Plan): void {
    router.post(`/${locale.value}/subscriptions/${plan.id}/checkout`);
}

const cancelForm = useForm({});

function cancelSubscription(): void {
    cancelForm.post(`/${locale.value}/subscriptions/cancel`);
}

function openPortal(): void {
    router.get(`/${locale.value}/billing/portal`);
}

function isCurrentPlan(plan: Plan): boolean {
    return subscribedPriceId.value.includes(plan.stripe_price_id);
}
</script>

<template>
    <Head title="Subscriptions" />

    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto px-4 py-8 space-y-8">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Subscription Plans
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Subscribe to unlock premium features and earn extra loyalty points.
                    </p>
                </div>

                <button
                    v-if="isSubscribed"
                    @click="openPortal"
                    class="rounded-xl border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                >
                    Manage Billing
                </button>
            </div>

            <!-- Active subscription banner -->
            <div
                v-if="activeSub"
                class="rounded-xl border p-5"
                :class="activeSub.on_grace_period
                    ? 'border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20'
                    : 'border-indigo-200 dark:border-indigo-700 bg-indigo-50 dark:bg-indigo-900/20'"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semibold"
                            :class="activeSub.on_grace_period
                                ? 'text-amber-800 dark:text-amber-300'
                                : 'text-indigo-800 dark:text-indigo-300'"
                        >
                            {{ activeSub.on_grace_period ? 'Subscription Cancelled' : 'Active Subscription' }}
                        </p>
                        <p class="text-sm mt-0.5"
                            :class="activeSub.on_grace_period
                                ? 'text-amber-700 dark:text-amber-400'
                                : 'text-indigo-700 dark:text-indigo-400'"
                        >
                            <span v-if="activeSub.on_grace_period">
                                Access continues until {{ activeSub.ends_at }}.
                            </span>
                            <span v-else-if="activeSub.trial_ends_at">
                                Trial ends {{ activeSub.trial_ends_at }}.
                            </span>
                            <span v-else>
                                Your subscription is active and renews automatically.
                            </span>
                        </p>
                    </div>

                    <button
                        v-if="!activeSub.on_grace_period"
                        @click="cancelSubscription"
                        :disabled="cancelForm.processing"
                        class="text-sm text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300 font-medium disabled:opacity-50 transition-colors"
                    >
                        {{ cancelForm.processing ? 'Cancelling…' : 'Cancel' }}
                    </button>
                </div>
            </div>

            <!-- Plans grid -->
            <div v-if="plans.length > 0" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    class="relative flex flex-col rounded-2xl border bg-white dark:bg-gray-900 p-6 shadow-sm transition-shadow hover:shadow-md"
                    :class="isCurrentPlan(plan)
                        ? 'border-indigo-500 ring-2 ring-indigo-500/30'
                        : 'border-gray-200 dark:border-gray-700'"
                >
                    <!-- Current plan badge -->
                    <div
                        v-if="isCurrentPlan(plan)"
                        class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-indigo-600 px-3 py-0.5 text-xs font-semibold text-white"
                    >
                        Current Plan
                    </div>

                    <div class="mb-4">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ plan.name }}
                        </h3>
                        <p v-if="plan.description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ plan.description }}
                        </p>
                    </div>

                    <div class="mb-6">
                        <span class="text-4xl font-extrabold text-gray-900 dark:text-white">
                            {{ plan.formatted_price }}
                        </span>
                        <span class="text-gray-500 dark:text-gray-400 text-sm">
                            / {{ plan.interval_label }}
                        </span>
                    </div>

                    <ul v-if="plan.features.length > 0" class="mb-6 space-y-2 flex-1">
                        <li
                            v-for="feature in plan.features"
                            :key="feature"
                            class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            <svg class="h-4 w-4 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            {{ feature }}
                        </li>
                    </ul>

                    <button
                        v-if="isCurrentPlan(plan)"
                        disabled
                        class="w-full rounded-xl bg-indigo-100 dark:bg-indigo-900/30 px-4 py-2.5 text-sm font-semibold text-indigo-600 dark:text-indigo-400 cursor-default"
                    >
                        Subscribed
                    </button>
                    <button
                        v-else-if="!isSubscribed"
                        @click="subscribeTo(plan)"
                        class="w-full rounded-xl bg-indigo-600 hover:bg-indigo-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors"
                    >
                        Subscribe
                    </button>
                    <button
                        v-else
                        @click="openPortal"
                        class="w-full rounded-xl border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                    >
                        Switch Plan
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-else
                class="rounded-2xl border border-dashed border-gray-200 dark:border-gray-700 p-16 text-center"
            >
                <p class="text-4xl mb-3">💳</p>
                <p class="font-medium text-gray-900 dark:text-white">No plans available</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Subscription plans will appear here when configured.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
