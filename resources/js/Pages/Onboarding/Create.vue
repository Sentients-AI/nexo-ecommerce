<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Spinner from '@/Components/UI/Spinner.vue';

const props = defineProps<{
    baseDomain: string;
    reservedSlugs: string[];
}>();

const step = ref<1 | 2>(1);

const form = useForm({
    store_name: '',
    store_slug: '',
    store_email: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);
const slugManuallyEdited = ref(false);

// Auto-generate slug from store name unless user has manually edited it
watch(() => form.store_name, (name) => {
    if (!slugManuallyEdited.value) {
        form.store_slug = name
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .slice(0, 63);
    }
});

function onSlugInput() {
    slugManuallyEdited.value = true;
    form.store_slug = form.store_slug
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-+/g, '-');
}

const storeUrl = computed(() => {
    if (!form.store_slug) {
        return `yourstore.${props.baseDomain}`;
    }
    return `${form.store_slug}.${props.baseDomain}`;
});

const isSlugReserved = computed(() => {
    return props.reservedSlugs.includes(form.store_slug);
});

function goToStep2() {
    if (!form.store_name || !form.store_slug || !form.store_email) {
        return;
    }
    step.value = 2;
}

function submit() {
    form.post('/start');
}

// Password strength
const passwordStrength = computed(() => {
    const p = form.password;
    if (!p) { return { score: 0, label: '', color: '' }; }
    let score = 0;
    if (p.length >= 8) { score++; }
    if (/[a-z]/.test(p)) { score++; }
    if (/[A-Z]/.test(p)) { score++; }
    if (/\d/.test(p)) { score++; }
    if (/[!@#$%^&*(),.?":{}|<>]/.test(p)) { score++; }
    if (score <= 2) { return { score, label: 'Weak', color: 'bg-red-500' }; }
    if (score <= 3) { return { score, label: 'Fair', color: 'bg-amber-500' }; }
    if (score <= 4) { return { score, label: 'Good', color: 'bg-brand-500' }; }
    return { score, label: 'Strong', color: 'bg-accent-500' };
});

const passwordsMatch = computed(() =>
    form.password && form.password_confirmation && form.password === form.password_confirmation,
);
</script>

<template>
    <Head title="Start Your Store" />

    <GuestLayout>
        <div class="flex min-h-[calc(100vh-10rem)]">
            <!-- Left panel -->
            <div class="hidden lg:flex lg:w-1/2 bg-navy-950 relative overflow-hidden">
                <div class="absolute top-1/4 right-1/4 w-64 h-64 bg-accent-500/20 rounded-full blur-3xl animate-float" />
                <div class="absolute bottom-1/3 left-1/4 w-48 h-48 bg-brand-500/15 rounded-full blur-3xl animate-float" style="animation-delay: 1.5s;" />
                <div class="absolute top-1/2 right-1/2 w-32 h-32 bg-accent-400/10 rounded-full blur-2xl animate-float" style="animation-delay: 3s;" />

                <div class="absolute inset-0 opacity-5">
                    <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                        <defs>
                            <pattern id="ob-dots" width="10" height="10" patternUnits="userSpaceOnUse">
                                <circle cx="5" cy="5" r="1" fill="white" />
                            </pattern>
                        </defs>
                        <rect width="100" height="100" fill="url(#ob-dots)" />
                    </svg>
                </div>

                <div class="relative z-10 flex flex-col justify-center px-12 xl:px-20">
                    <div>
                        <h1 class="text-4xl xl:text-5xl font-bold text-white leading-tight">
                            Start selling today
                        </h1>
                        <p class="mt-4 text-lg text-navy-300 max-w-md">
                            Launch your online store in minutes. No credit card required for your 14-day trial.
                        </p>
                    </div>

                    <div class="mt-12 space-y-4">
                        <div class="flex items-center gap-3 text-white/80">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent-500/15 border border-accent-500/20">
                                <svg class="h-5 w-5 text-accent-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                                </svg>
                            </div>
                            <span>Your own storefront at <span class="text-accent-400 font-mono text-sm">{{ storeUrl }}</span></span>
                        </div>
                        <div class="flex items-center gap-3 text-white/80">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-500/15 border border-brand-500/20">
                                <svg class="h-5 w-5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                            </div>
                            <span>14-day free trial, no credit card needed</span>
                        </div>
                        <div class="flex items-center gap-3 text-white/80">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-500/15 border border-brand-500/20">
                                <svg class="h-5 w-5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                                </svg>
                            </div>
                            <span>Full analytics, inventory &amp; order management</span>
                        </div>
                        <div class="flex items-center gap-3 text-white/80">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent-500/15 border border-accent-500/20">
                                <svg class="h-5 w-5 text-accent-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                                </svg>
                            </div>
                            <span>Secure payments via Stripe</span>
                        </div>
                    </div>

                    <!-- Step indicator -->
                    <div class="mt-12 flex items-center gap-3">
                        <div :class="['h-2 w-8 rounded-full transition-colors', step === 1 ? 'bg-accent-500' : 'bg-accent-500/40']" />
                        <div :class="['h-2 w-8 rounded-full transition-colors', step === 2 ? 'bg-accent-500' : 'bg-accent-500/40']" />
                        <span class="text-navy-400 text-sm">Step {{ step }} of 2</span>
                    </div>
                </div>
            </div>

            <!-- Right panel - Form -->
            <div class="flex w-full lg:w-1/2 flex-col justify-center px-4 sm:px-6 lg:px-12 xl:px-20 py-12 bg-white dark:bg-navy-950">
                <div class="mx-auto w-full max-w-md">

                    <!-- Step indicators (mobile) -->
                    <div class="flex items-center gap-2 mb-6 lg:hidden">
                        <div :class="['h-1.5 flex-1 rounded-full transition-colors', step === 1 ? 'bg-brand-500' : 'bg-brand-500/40']" />
                        <div :class="['h-1.5 flex-1 rounded-full transition-colors', step === 2 ? 'bg-brand-500' : 'bg-brand-500/40']" />
                        <span class="text-xs text-slate-500 dark:text-slate-400 ml-1">{{ step }}/2</span>
                    </div>

                    <!-- STEP 1: Store Details -->
                    <div v-if="step === 1">
                        <div class="text-center lg:text-left">
                            <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                                Set up your store
                            </h2>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                Already have an account?
                                <Link href="/en/login" class="font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-400 transition-colors">
                                    Sign in
                                </Link>
                            </p>
                        </div>

                        <form @submit.prevent="goToStep2" class="mt-8 space-y-5">
                            <!-- Store name -->
                            <div>
                                <label for="store_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Store name
                                </label>
                                <div class="mt-2 relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                        <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                                        </svg>
                                    </div>
                                    <input
                                        id="store_name"
                                        v-model="form.store_name"
                                        type="text"
                                        required
                                        placeholder="My Awesome Store"
                                        :class="[
                                            'block w-full rounded-xl border py-3 pl-11 pr-4 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition-colors',
                                            form.errors.store_name
                                                ? 'border-red-300 dark:border-red-600 focus:border-red-500 focus:ring-red-500/20'
                                                : 'border-slate-300 dark:border-navy-700 focus:border-brand-500 focus:ring-brand-500/20 dark:bg-navy-800/60',
                                        ]"
                                    />
                                </div>
                                <p v-if="form.errors.store_name" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.store_name }}</p>
                            </div>

                            <!-- Store URL (slug) -->
                            <div>
                                <label for="store_slug" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Store URL
                                </label>
                                <div class="mt-2">
                                    <div class="flex rounded-xl overflow-hidden border transition-colors"
                                        :class="form.errors.store_slug || isSlugReserved
                                            ? 'border-red-300 dark:border-red-600'
                                            : 'border-slate-300 dark:border-navy-700 focus-within:border-brand-500'"
                                    >
                                        <input
                                            id="store_slug"
                                            v-model="form.store_slug"
                                            type="text"
                                            required
                                            placeholder="my-store"
                                            @input="onSlugInput"
                                            class="flex-1 min-w-0 py-3 px-4 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none bg-transparent dark:bg-navy-800/60"
                                        />
                                        <span class="shrink-0 flex items-center px-3 bg-slate-50 dark:bg-navy-800 text-slate-500 dark:text-slate-400 text-sm border-l border-slate-300 dark:border-navy-700">
                                            .{{ baseDomain }}
                                        </span>
                                    </div>
                                </div>
                                <p v-if="form.errors.store_slug" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.store_slug }}</p>
                                <p v-else-if="isSlugReserved" class="mt-2 text-sm text-red-600 dark:text-red-400">That subdomain is reserved.</p>
                                <p v-else-if="form.store_slug" class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                    Your store will be at
                                    <span class="font-mono text-brand-600 dark:text-brand-400">{{ storeUrl }}</span>
                                </p>
                            </div>

                            <!-- Store email -->
                            <div>
                                <label for="store_email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Store contact email
                                </label>
                                <div class="mt-2 relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                        <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                        </svg>
                                    </div>
                                    <input
                                        id="store_email"
                                        v-model="form.store_email"
                                        type="email"
                                        required
                                        placeholder="hello@mystore.com"
                                        :class="[
                                            'block w-full rounded-xl border py-3 pl-11 pr-4 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition-colors',
                                            form.errors.store_email
                                                ? 'border-red-300 dark:border-red-600 focus:border-red-500 focus:ring-red-500/20'
                                                : 'border-slate-300 dark:border-navy-700 focus:border-brand-500 focus:ring-brand-500/20 dark:bg-navy-800/60',
                                        ]"
                                    />
                                </div>
                                <p v-if="form.errors.store_email" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.store_email }}</p>
                            </div>

                            <button
                                type="submit"
                                :disabled="!form.store_name || !form.store_slug || !form.store_email || isSlugReserved"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-3.5 text-base font-semibold text-white shadow-lg shadow-brand-500/30 hover:bg-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                            >
                                Continue
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </button>
                        </form>
                    </div>

                    <!-- STEP 2: Account Details -->
                    <div v-else>
                        <div class="text-center lg:text-left">
                            <button
                                @click="step = 1"
                                class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 mb-4 transition-colors"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                                </svg>
                                Back to store details
                            </button>
                            <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                                Create your account
                            </h2>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                This will be the admin account for
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ form.store_name }}</span>.
                            </p>
                        </div>

                        <form @submit.prevent="submit" class="mt-8 space-y-5">
                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Your name</label>
                                <div class="mt-2 relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                        <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                        </svg>
                                    </div>
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        autocomplete="name"
                                        required
                                        placeholder="John Doe"
                                        :class="[
                                            'block w-full rounded-xl border py-3 pl-11 pr-4 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition-colors',
                                            form.errors.name
                                                ? 'border-red-300 dark:border-red-600 focus:border-red-500 focus:ring-red-500/20'
                                                : 'border-slate-300 dark:border-navy-700 focus:border-brand-500 focus:ring-brand-500/20 dark:bg-navy-800/60',
                                        ]"
                                    />
                                </div>
                                <p v-if="form.errors.name" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Your email</label>
                                <div class="mt-2 relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                        <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                        </svg>
                                    </div>
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="email"
                                        required
                                        placeholder="you@example.com"
                                        :class="[
                                            'block w-full rounded-xl border py-3 pl-11 pr-4 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition-colors',
                                            form.errors.email
                                                ? 'border-red-300 dark:border-red-600 focus:border-red-500 focus:ring-red-500/20'
                                                : 'border-slate-300 dark:border-navy-700 focus:border-brand-500 focus:ring-brand-500/20 dark:bg-navy-800/60',
                                        ]"
                                    />
                                </div>
                                <p v-if="form.errors.email" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
                            </div>

                            <!-- Password -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
                                <div class="mt-2 relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                        <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                        </svg>
                                    </div>
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        autocomplete="new-password"
                                        required
                                        placeholder="Create a strong password"
                                        :class="[
                                            'block w-full rounded-xl border py-3 pl-11 pr-12 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition-colors',
                                            form.errors.password
                                                ? 'border-red-300 dark:border-red-600 focus:border-red-500 focus:ring-red-500/20'
                                                : 'border-slate-300 dark:border-navy-700 focus:border-brand-500 focus:ring-brand-500/20 dark:bg-navy-800/60',
                                        ]"
                                    />
                                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                                        <svg v-if="showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>
                                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </button>
                                </div>
                                <div v-if="form.password" class="mt-3">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-xs text-slate-500 dark:text-slate-400">Password strength</span>
                                        <span :class="['text-xs font-medium', passwordStrength.score <= 2 ? 'text-red-600 dark:text-red-400' : passwordStrength.score <= 3 ? 'text-amber-600 dark:text-amber-400' : passwordStrength.score <= 4 ? 'text-brand-600 dark:text-brand-400' : 'text-accent-600 dark:text-accent-400']">
                                            {{ passwordStrength.label }}
                                        </span>
                                    </div>
                                    <div class="flex gap-1">
                                        <div v-for="i in 5" :key="i" class="h-1.5 flex-1 rounded-full transition-colors" :class="i <= passwordStrength.score ? passwordStrength.color : 'bg-slate-200 dark:bg-navy-700'" />
                                    </div>
                                </div>
                                <p v-if="form.errors.password" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.password }}</p>
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Confirm password</label>
                                <div class="mt-2 relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                        <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        :type="showConfirmPassword ? 'text' : 'password'"
                                        autocomplete="new-password"
                                        required
                                        placeholder="Confirm your password"
                                        :class="[
                                            'block w-full rounded-xl border py-3 pl-11 pr-12 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition-colors',
                                            form.password_confirmation && !passwordsMatch
                                                ? 'border-red-300 dark:border-red-600 focus:border-red-500 focus:ring-red-500/20'
                                                : passwordsMatch
                                                    ? 'border-accent-300 dark:border-accent-700 focus:border-accent-500 focus:ring-accent-500/20 dark:bg-navy-800/60'
                                                    : 'border-slate-300 dark:border-navy-700 focus:border-brand-500 focus:ring-brand-500/20 dark:bg-navy-800/60',
                                        ]"
                                    />
                                    <button type="button" @click="showConfirmPassword = !showConfirmPassword" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                                        <svg v-if="showConfirmPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>
                                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </button>
                                </div>
                                <p v-if="form.password_confirmation && !passwordsMatch" class="mt-2 text-sm text-red-600 dark:text-red-400">Passwords do not match</p>
                                <p v-else-if="passwordsMatch" class="mt-2 text-sm text-accent-600 dark:text-accent-400 flex items-center gap-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                    Passwords match
                                </p>
                            </div>

                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                By creating a store, you agree to our
                                <a href="/en/terms" class="text-brand-600 hover:text-brand-500 dark:text-brand-400">Terms of Service</a>
                                and
                                <a href="/en/privacy" class="text-brand-600 hover:text-brand-500 dark:text-brand-400">Privacy Policy</a>.
                            </p>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-3.5 text-base font-semibold text-white shadow-lg shadow-brand-500/30 hover:bg-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                            >
                                <Spinner v-if="form.processing" size="sm" color="white" />
                                <span>{{ form.processing ? 'Creating your store...' : 'Launch my store' }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
