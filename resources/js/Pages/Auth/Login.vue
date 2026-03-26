<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Spinner from '@/Components/UI/Spinner.vue';
import { useLocale } from '@/Composables/useLocale';

const { localePath } = useLocale();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

function submit() {
    form.post(localePath('/login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
}

function togglePassword() {
    showPassword.value = !showPassword.value;
}
</script>

<template>
    <Head title="Sign In" />

    <GuestLayout>
        <div class="flex min-h-[calc(100vh-10rem)]">
            <!-- Left side - Illustration (hidden on mobile) -->
            <div class="hidden lg:flex lg:w-1/2 bg-navy-950 relative overflow-hidden">
                <!-- Gradient orbs -->
                <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-brand-500/20 rounded-full blur-3xl animate-float" />
                <div class="absolute bottom-1/4 right-1/4 w-48 h-48 bg-accent-500/15 rounded-full blur-3xl animate-float" style="animation-delay: 1.5s;" />
                <div class="absolute top-1/2 left-1/2 w-32 h-32 bg-brand-400/10 rounded-full blur-2xl animate-float" style="animation-delay: 3s;" />

                <!-- Subtle grid overlay -->
                <div class="absolute inset-0 opacity-5">
                    <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                        <defs>
                            <pattern id="login-grid" width="10" height="10" patternUnits="userSpaceOnUse">
                                <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5"/>
                            </pattern>
                        </defs>
                        <rect width="100" height="100" fill="url(#login-grid)" />
                    </svg>
                </div>

                <!-- Content -->
                <div class="relative z-10 flex flex-col justify-center px-12 xl:px-20">
                    <div>
                        <h1 class="text-4xl xl:text-5xl font-bold text-white leading-tight">
                            Welcome back!
                        </h1>
                        <p class="mt-4 text-lg text-navy-300 max-w-md">
                            Sign in to access your account, track orders, and discover amazing products.
                        </p>
                    </div>

                    <!-- Features list -->
                    <div class="mt-12 space-y-4">
                        <div class="flex items-center gap-3 text-white/80">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500/15 border border-brand-500/20">
                                <svg class="h-5 w-5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                </svg>
                            </div>
                            <span>Track your orders in real-time</span>
                        </div>
                        <div class="flex items-center gap-3 text-white/80">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500/15 border border-brand-500/20">
                                <svg class="h-5 w-5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                </svg>
                            </div>
                            <span>Save your favorite products</span>
                        </div>
                        <div class="flex items-center gap-3 text-white/80">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent-500/15 border border-accent-500/20">
                                <svg class="h-5 w-5 text-accent-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
                                </svg>
                            </div>
                            <span>Get exclusive member deals</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right side - Form -->
            <div class="flex w-full lg:w-1/2 flex-col justify-center px-4 sm:px-6 lg:px-12 xl:px-20 py-12 bg-white dark:bg-navy-950">
                <div class="mx-auto w-full max-w-md">
                    <!-- Header -->
                    <div class="text-center lg:text-left">
                        <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                            Sign in to your account
                        </h2>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                            Don't have an account?
                            <Link :href="localePath('/register')" class="font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-400 transition-colors">
                                Create one for free
                            </Link>
                        </p>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submit" class="mt-8 space-y-6">
                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                Email address
                            </label>
                            <div class="mt-2 relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                    <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                    </svg>
                                </div>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    name="email"
                                    type="email"
                                    autocomplete="email"
                                    required
                                    placeholder="you@example.com"
                                    :class="[
                                        'block w-full rounded-xl border py-3 pl-11 pr-4 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition-colors',
                                        form.errors.email
                                            ? 'border-red-300 dark:border-red-600 focus:border-red-500 focus:ring-red-500/20'
                                            : 'border-slate-300 dark:border-navy-700 focus:border-brand-500 focus:ring-brand-500/20 dark:bg-navy-800/60'
                                    ]"
                                />
                            </div>
                            <p v-if="form.errors.email" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                Password
                            </label>
                            <div class="mt-2 relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                    <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                    </svg>
                                </div>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    name="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    placeholder="Enter your password"
                                    :class="[
                                        'block w-full rounded-xl border py-3 pl-11 pr-12 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition-colors',
                                        form.errors.password
                                            ? 'border-red-300 dark:border-red-600 focus:border-red-500 focus:ring-red-500/20'
                                            : 'border-slate-300 dark:border-navy-700 focus:border-brand-500 focus:ring-brand-500/20 dark:bg-navy-800/60'
                                    ]"
                                />
                                <button
                                    type="button"
                                    @click="togglePassword"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                                >
                                    <svg v-if="showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- Remember me -->
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    id="remember"
                                    v-model="form.remember"
                                    name="remember"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 dark:border-navy-700 text-brand-600 focus:ring-brand-500 dark:bg-navy-800"
                                />
                                <span class="text-sm text-slate-600 dark:text-slate-400">Remember me</span>
                            </label>
                            <Link :href="localePath('/forgot-password')" class="text-sm font-medium text-brand-600 hover:text-brand-500 dark:text-brand-400 transition-colors">
                                Forgot password?
                            </Link>
                        </div>

                        <!-- Submit button -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-3.5 text-base font-semibold text-white shadow-lg shadow-brand-500/30 hover:bg-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                        >
                            <Spinner v-if="form.processing" size="sm" color="white" />
                            <span>{{ form.processing ? 'Signing in...' : 'Sign in' }}</span>
                        </button>

                        <!-- Divider -->
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-slate-200 dark:border-navy-700" />
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="bg-white dark:bg-navy-950 px-4 text-slate-500 dark:text-slate-400">Or continue with</span>
                            </div>
                        </div>

                        <!-- Social login buttons -->
                        <div class="grid grid-cols-2 gap-3">
                            <a
                                href="/auth/google/redirect"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-800/60 px-4 py-3 text-sm font-medium text-slate-700 dark:text-slate-300 shadow-sm hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                </svg>
                                Google
                            </a>
                            <a
                                href="#"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-800/60 px-4 py-3 text-sm font-medium text-slate-700 dark:text-slate-300 shadow-sm hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                            >
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                GitHub
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
