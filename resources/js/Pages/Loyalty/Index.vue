<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Form } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface Account {
    points_balance: number;
    total_points_earned: number;
    total_points_redeemed: number;
    points_value_cents: number;
    points_value_dollars: number;
}

interface Transaction {
    id: number;
    type: string;
    type_label: string;
    is_credit: boolean;
    points: number;
    balance_after: number;
    description: string;
    created_at: string;
}

interface Tier {
    name: string;
    min_points: number;
    max_points: number | null;
    color: string;
}

interface LoyaltyConfig {
    points_per_dollar: number;
    points_value_cents: number;
    minimum_redemption: number;
}

const props = defineProps<{
    account: Account;
    transactions: Transaction[];
    tiers: Tier[];
    config: LoyaltyConfig;
}>();

const currentTier = computed<Tier>(() => {
    const balance = props.account.points_balance;
    return [...props.tiers].reverse().find(t => balance >= t.min_points) ?? props.tiers[0];
});

const nextTier = computed<Tier | null>(() => {
    const idx = props.tiers.findIndex(t => t.name === currentTier.value.name);
    return idx < props.tiers.length - 1 ? props.tiers[idx + 1] : null;
});

const tierProgress = computed<number>(() => {
    const tier = currentTier.value;
    if (!nextTier.value) {
        return 100;
    }
    const range = (nextTier.value.min_points - tier.min_points);
    const progress = props.account.points_balance - tier.min_points;
    return Math.min(100, Math.round((progress / range) * 100));
});

const tierColorMap: Record<string, string> = {
    amber: 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800',
    slate: 'text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700',
    yellow: 'text-yellow-600 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800',
};

const tierBarColorMap: Record<string, string> = {
    amber: 'bg-amber-500',
    slate: 'bg-slate-500',
    yellow: 'bg-yellow-500',
};

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}
</script>

<template>
    <Head title="My Points" />

    <AuthenticatedLayout>
        <div class="max-w-3xl mx-auto px-4 py-8 space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Loyalty Points
            </h1>

            <!-- Balance & Tier Card -->
            <div
                class="rounded-xl border p-6"
                :class="tierColorMap[currentTier.color]"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium uppercase tracking-wide opacity-70">
                            Your Balance
                        </p>
                        <p class="text-5xl font-bold mt-1">
                            {{ account.points_balance.toLocaleString() }}
                            <span class="text-2xl font-normal opacity-60">pts</span>
                        </p>
                        <p class="text-sm mt-1 opacity-70">
                            ≈ ${{ account.points_value_dollars.toFixed(2) }} value
                        </p>
                    </div>

                    <div class="text-right">
                        <span class="text-sm font-semibold uppercase tracking-wider">
                            {{ currentTier.name }}
                        </span>
                        <p class="text-xs opacity-60 mt-0.5">
                            current tier
                        </p>
                    </div>
                </div>

                <!-- Progress to next tier -->
                <div v-if="nextTier" class="mt-5">
                    <div class="flex justify-between text-xs opacity-70 mb-1.5">
                        <span>{{ currentTier.name }}</span>
                        <span>{{ nextTier.name }} ({{ nextTier.min_points.toLocaleString() }} pts)</span>
                    </div>
                    <div class="h-2 bg-black/10 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="tierBarColorMap[currentTier.color]"
                            :style="{ width: tierProgress + '%' }"
                        />
                    </div>
                    <p class="text-xs opacity-60 mt-1.5">
                        {{ (nextTier.min_points - account.points_balance).toLocaleString() }} pts until {{ nextTier.name }}
                    </p>
                </div>

                <div v-else class="mt-3 text-sm font-medium opacity-80">
                    🏆 You've reached the highest tier!
                </div>
            </div>

            <!-- Stats Row -->
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Total Earned
                    </p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ account.total_points_earned.toLocaleString() }}
                        <span class="text-sm font-normal text-gray-400">pts</span>
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Total Redeemed
                    </p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ account.total_points_redeemed.toLocaleString() }}
                        <span class="text-sm font-normal text-gray-400">pts</span>
                    </p>
                </div>
            </div>

            <!-- How it works -->
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-3">
                    How it works
                </h2>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <li class="flex items-center gap-2">
                        <span class="text-indigo-500">•</span>
                        Earn <strong>{{ config.points_per_dollar }} point</strong> for every $1 spent.
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-indigo-500">•</span>
                        Each point is worth <strong>{{ config.points_value_cents }}¢</strong> at checkout.
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-indigo-500">•</span>
                        Minimum redemption: <strong>{{ config.minimum_redemption }} points</strong>.
                    </li>
                </ul>
            </div>

            <!-- Tiers -->
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">
                    Membership Tiers
                </h2>
                <div class="space-y-3">
                    <div
                        v-for="tier in tiers"
                        :key="tier.name"
                        class="flex items-center justify-between px-4 py-3 rounded-lg border"
                        :class="[
                            tierColorMap[tier.color],
                            currentTier.name === tier.name ? 'ring-2 ring-current ring-opacity-40' : 'opacity-70',
                        ]"
                    >
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">{{ tier.name }}</span>
                            <span v-if="currentTier.name === tier.name" class="text-xs px-1.5 py-0.5 bg-current/10 rounded-full">
                                current
                            </span>
                        </div>
                        <span class="text-sm">
                            {{ tier.max_points
                                ? `${tier.min_points.toLocaleString()} – ${tier.max_points.toLocaleString()} pts`
                                : `${tier.min_points.toLocaleString()}+ pts` }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Redeem Points -->
            <div
                v-if="account.points_balance >= config.minimum_redemption"
                class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6"
            >
                <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-1">
                    Redeem Points
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Redeem points for account credit, applied at next checkout.
                </p>

                <Form
                    action="/api/v1/loyalty/redeem"
                    method="post"
                    #default="{ errors, processing, wasSuccessful }"
                >
                    <div class="flex gap-3">
                        <input
                            name="points"
                            type="number"
                            :min="config.minimum_redemption"
                            :max="account.points_balance"
                            :placeholder="`Min ${config.minimum_redemption}`"
                            class="flex-1 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        <button
                            type="submit"
                            :disabled="processing"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition-colors"
                        >
                            Redeem
                        </button>
                    </div>
                    <p v-if="errors.points" class="mt-2 text-sm text-red-500">
                        {{ errors.points }}
                    </p>
                    <p v-if="wasSuccessful" class="mt-2 text-sm text-green-600 dark:text-green-400">
                        Points redeemed! Your discount will be applied at checkout.
                    </p>
                </Form>
            </div>

            <!-- Transaction History -->
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        Transaction History
                    </h2>
                </div>

                <ul v-if="transactions.length > 0" class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li
                        v-for="tx in transactions"
                        :key="tx.id"
                        class="flex items-center justify-between px-6 py-4"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ tx.description || tx.type_label }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ formatDate(tx.created_at) }} · Balance after: {{ tx.balance_after.toLocaleString() }} pts
                            </p>
                        </div>
                        <span
                            class="text-sm font-semibold"
                            :class="tx.is_credit
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-red-500 dark:text-red-400'"
                        >
                            {{ tx.is_credit ? '+' : '-' }}{{ Math.abs(tx.points).toLocaleString() }} pts
                        </span>
                    </li>
                </ul>

                <div v-else class="px-6 py-10 text-center">
                    <p class="text-4xl mb-3">
                        ⭐
                    </p>
                    <p class="font-medium text-gray-900 dark:text-white">
                        No transactions yet
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Start shopping to earn points on every order.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
