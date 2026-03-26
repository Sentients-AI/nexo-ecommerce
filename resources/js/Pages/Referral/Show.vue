<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

interface ReferralCode {
    code: string;
    referee_discount_percent: number;
    expires_at: string | null;
}

defineProps<{
    referralCode: ReferralCode;
}>();
</script>

<template>
    <Head title="You've been invited!" />

    <div class="min-h-screen bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-gray-900 dark:to-gray-800 flex items-center justify-center p-4">
        <div class="max-w-md w-full">
            <!-- Card -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-8 py-10 text-center">
                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2H12zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h3m0 0V5.5A2.5 2.5 0 1114.5 8H12m0 4H5" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-white mb-2">
                        You've been invited!
                    </h1>
                    <p class="text-indigo-100">
                        A friend thinks you'd love shopping with us.
                    </p>
                </div>

                <!-- Body -->
                <div class="px-8 py-8 text-center">
                    <!-- Discount Offer -->
                    <div class="bg-indigo-50 dark:bg-indigo-900/30 rounded-xl p-6 mb-6">
                        <p class="text-sm text-brand-600 dark:text-brand-400 font-medium uppercase tracking-wide mb-1">
                            Your exclusive offer
                        </p>
                        <p class="text-5xl font-bold text-brand-700 dark:text-brand-300 mb-1">
                            {{ referralCode.referee_discount_percent }}%
                        </p>
                        <p class="text-slate-600 dark:text-slate-400">
                            off your first order
                        </p>
                    </div>

                    <!-- Referral Code -->
                    <div class="mb-6">
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-2">
                            Your referral code
                        </p>
                        <div class="bg-gray-100 dark:bg-navy-900/60 rounded-lg px-4 py-3 font-mono text-lg font-semibold text-gray-800 dark:text-gray-200 tracking-widest">
                            {{ referralCode.code }}
                        </div>
                    </div>

                    <!-- Expiry -->
                    <p
                        v-if="referralCode.expires_at"
                        class="text-sm text-slate-400 dark:text-slate-500 mb-6"
                    >
                        Offer expires {{ new Date(referralCode.expires_at).toLocaleDateString() }}
                    </p>

                    <!-- CTA -->
                    <Link
                        href="/en/register"
                        class="block w-full bg-brand-500 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors duration-200 text-center"
                    >
                        Sign up &amp; claim your discount
                    </Link>

                    <p class="mt-4 text-sm text-slate-400 dark:text-slate-500">
                        Already have an account?
                        <Link href="/en/login" class="text-brand-600 dark:text-brand-400 hover:underline">
                            Sign in
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
