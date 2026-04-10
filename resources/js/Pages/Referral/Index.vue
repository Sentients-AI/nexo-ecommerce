<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Form } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useLocale } from '@/Composables/useLocale';

interface ReferralCode {
    code: string;
    shareable_url: string;
    status: string;
    referrer_reward_points: number;
    referee_discount_percent: number;
    max_uses: number | null;
    used_count: number;
    expires_at: string | null;
    is_active: boolean;
}

interface Stats {
    total_usages: number;
    total_points_earned: number;
}

interface Usage {
    referee_email: string;
    points_awarded: number;
    discount_given: number;
    used_at: string;
}

const props = defineProps<{
    referralCode: ReferralCode;
    stats: Stats;
    usages: Usage[];
}>();

const { localePath } = useLocale();

const copied = ref(false);
const regenerating = ref(false);

function copyCode() {
    navigator.clipboard.writeText(props.referralCode.code).then(() => {
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    });
}

function copyLink() {
    navigator.clipboard.writeText(props.referralCode.shareable_url).then(() => {
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    });
}

function regenerate() {
    regenerating.value = true;
    router.post('/api/v1/referral/regenerate', {}, {
        onFinish: () => { regenerating.value = false; },
        onSuccess: () => router.reload(),
    });
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}

const statusColors: Record<string, string> = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    expired: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    exhausted: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    inactive: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
};
</script>

<template>
    <Head title="My Referrals" />

    <AuthenticatedLayout>
        <div class="max-w-3xl mx-auto px-4 py-8 space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                My Referrals
            </h1>

            <!-- Stats Row -->
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-5 text-center">
                    <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                        {{ stats.total_usages }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Friends referred
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-5 text-center">
                    <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                        {{ stats.total_points_earned.toLocaleString() }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Points earned
                    </p>
                </div>
            </div>

            <!-- Referral Code Card -->
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Your Referral Code
                    </h2>
                    <span
                        class="text-xs font-medium px-2.5 py-1 rounded-full capitalize"
                        :class="statusColors[referralCode.status] ?? statusColors.inactive"
                    >
                        {{ referralCode.status }}
                    </span>
                </div>

                <!-- Code display -->
                <div class="flex items-center gap-3">
                    <div class="flex-1 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-3 font-mono text-xl font-bold tracking-widest text-gray-800 dark:text-gray-100 text-center">
                        {{ referralCode.code }}
                    </div>
                    <button
                        class="shrink-0 px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors"
                        @click="copyCode"
                    >
                        {{ copied ? 'Copied!' : 'Copy' }}
                    </button>
                </div>

                <!-- Shareable link -->
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                        Shareable link
                    </p>
                    <div class="flex items-center gap-2">
                        <input
                            :value="referralCode.shareable_url"
                            readonly
                            class="flex-1 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-gray-600 dark:text-gray-300 truncate"
                        >
                        <button
                            class="shrink-0 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                            @click="copyLink"
                        >
                            Copy link
                        </button>
                    </div>
                </div>

                <!-- Reward details -->
                <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100 dark:border-gray-800">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            You earn
                        </p>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ referralCode.referrer_reward_points }} pts
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Friend gets
                        </p>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ referralCode.referee_discount_percent }}% off
                        </p>
                    </div>
                    <div v-if="referralCode.max_uses">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Uses remaining
                        </p>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ referralCode.max_uses - referralCode.used_count }} / {{ referralCode.max_uses }}
                        </p>
                    </div>
                    <div v-if="referralCode.expires_at">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Expires
                        </p>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ formatDate(referralCode.expires_at) }}
                        </p>
                    </div>
                </div>

                <!-- Regenerate -->
                <div class="pt-2">
                    <button
                        :disabled="regenerating"
                        class="text-sm text-gray-500 dark:text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition-colors disabled:opacity-50"
                        @click="regenerate"
                    >
                        {{ regenerating ? 'Regenerating…' : 'Generate a new code (deactivates current)' }}
                    </button>
                </div>
            </div>

            <!-- Apply a referral code -->
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                    Have a referral code?
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Enter a friend's code to get a discount on your next order.
                </p>

                <Form
                    action="/api/v1/referral/apply"
                    method="post"
                    #default="{ errors, processing, wasSuccessful }"
                >
                    <div class="flex gap-3">
                        <input
                            name="code"
                            type="text"
                            placeholder="Enter code"
                            class="flex-1 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        <button
                            type="submit"
                            :disabled="processing"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition-colors"
                        >
                            Apply
                        </button>
                    </div>

                    <p v-if="errors.code" class="mt-2 text-sm text-red-500">
                        {{ errors.code }}
                    </p>
                    <p v-if="wasSuccessful" class="mt-2 text-sm text-green-600 dark:text-green-400">
                        Code applied! Your discount is ready at checkout.
                    </p>
                </Form>
            </div>

            <!-- Referral history -->
            <div
                v-if="usages.length > 0"
                class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700"
            >
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Referral History
                    </h2>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li
                        v-for="(usage, i) in usages"
                        :key="i"
                        class="flex items-center justify-between px-6 py-4"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ usage.referee_email }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ formatDate(usage.used_at) }} · gave {{ usage.discount_given }}% off
                            </p>
                        </div>
                        <span class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">
                            +{{ usage.points_awarded }} pts
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Empty state for history -->
            <div
                v-else
                class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 px-6 py-10 text-center"
            >
                <p class="text-4xl mb-3">
                    🎁
                </p>
                <p class="text-gray-900 dark:text-white font-medium">
                    No referrals yet
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Share your code above and earn points when friends sign up.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
