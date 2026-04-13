<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CheckoutProgress from '@/Components/Checkout/CheckoutProgress.vue';
import Alert from '@/Components/UI/Alert.vue';
import Spinner from '@/Components/UI/Spinner.vue';
import { useCheckout } from '@/Composables/useCheckout';
import { usePayments } from '@/Composables/usePayments';
import { useLocale } from '@/Composables/useLocale';
import { useCurrency } from '@/Composables/useCurrency';
import type { CartApiResource } from '@/types/api';

function normalizeImages(images: string | string[] | null | undefined): string[] {
    if (!images) return [];
    if (Array.isArray(images)) return images;
    if (typeof images === 'string') return [images];
    return [];
}

interface ShippingMethod {
    id: number;
    name: string;
    description: string | null;
    type: string;
    rate_cents: number;
    cost_cents: number;
    estimated_delivery: string;
}

interface Props {
    cart: CartApiResource;
    stripePublicKey: string;
    shippingMethods: ShippingMethod[];
    isAuthenticated: boolean;
}

const props = defineProps<Props>();
const { localePath } = useLocale();
const { currency, formatPrice } = useCurrency();

const { initiateCheckout, loading: checkoutLoading, error: checkoutError, clearError } = useCheckout();
const { stripe, elements, loading: stripeLoading, error: stripeError, createElements, confirmPayment, paymentProcessing } = usePayments(props.stripePublicKey);

const clientSecret = ref<string | null>(null);
const paymentElementRef = ref<HTMLDivElement | null>(null);
const isSubmitting = ref(false);
const submitError = ref<string | null>(null);
const itemsExpanded = ref(true);
const selectedShippingMethodId = ref<number | null>(props.shippingMethods[0]?.id ?? null);
const guestEmail = ref('');
const guestName = ref('');

const couponCode = ref('');
const appliedCouponCode = ref<string | null>(null);
const appliedCouponName = ref<string | null>(null);
const discountCents = ref(0);
const couponError = ref<string | null>(null);
const couponLoading = ref(false);

const giftCardCode = ref('');
const appliedGiftCardCode = ref<string | null>(null);
const giftCardDiscountCents = ref(0);
const giftCardError = ref<string | null>(null);
const giftCardLoading = ref(false);

const isLoading = computed(() => checkoutLoading.value || stripeLoading.value || paymentProcessing.value || isSubmitting.value);

const taxCents = computed(() => Math.round(props.cart.subtotal * 0.08));
const selectedShipping = computed(() =>
    props.shippingMethods.find(m => m.id === selectedShippingMethodId.value) ?? null
);
const shippingCents = computed(() => selectedShipping.value?.cost_cents ?? 0);
const totalCents = computed(() => props.cart.subtotal + taxCents.value + shippingCents.value - discountCents.value - giftCardDiscountCents.value);

async function applyPromotion(): Promise<void> {
    const code = couponCode.value.trim();
    if (!code) return;

    couponError.value = null;
    couponLoading.value = true;

    try {
        const res = await fetch('/api/v1/promotions/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
            body: JSON.stringify({ cart_id: props.cart.id, code }),
        });

        const data = await res.json();

        if (data.valid) {
            appliedCouponCode.value = code;
            appliedCouponName.value = data.promotion_name;
            discountCents.value = data.discount_cents;
            couponCode.value = '';
        } else {
            couponError.value = data.message ?? 'Invalid coupon code.';
        }
    } catch {
        couponError.value = 'Failed to apply coupon. Please try again.';
    } finally {
        couponLoading.value = false;
    }
}

function removePromotion(): void {
    appliedCouponCode.value = null;
    appliedCouponName.value = null;
    discountCents.value = 0;
    couponError.value = null;
}

async function applyGiftCard(): Promise<void> {
    const code = giftCardCode.value.trim();
    if (!code) return;

    giftCardError.value = null;
    giftCardLoading.value = true;

    try {
        const res = await fetch('/api/v1/gift-cards/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
            body: JSON.stringify({ code }),
        });

        const data = await res.json();

        if (data.valid) {
            appliedGiftCardCode.value = code.toUpperCase();
            giftCardDiscountCents.value = data.balance_cents;
            giftCardCode.value = '';
        } else {
            giftCardError.value = data.message ?? 'Invalid gift card code.';
        }
    } catch {
        giftCardError.value = 'Failed to apply gift card. Please try again.';
    } finally {
        giftCardLoading.value = false;
    }
}

function removeGiftCard(): void {
    appliedGiftCardCode.value = null;
    giftCardDiscountCents.value = 0;
    giftCardError.value = null;
}

async function handleInitiateCheckout() {
    clearError();
    submitError.value = null;
    isSubmitting.value = true;

    const success = await initiateCheckout(props.cart.id, currency.value);

    if (!success) {
        submitError.value = checkoutError.value?.message ?? 'Failed to initiate checkout';
        isSubmitting.value = false;
        return;
    }

    const orderData = await fetch('/api/v1/checkout', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            'Idempotency-Key': getIdempotencyKey('checkout'),
        },
        credentials: 'include',
        body: JSON.stringify({
            cart_id: props.cart.id,
            currency: currency.value,
            shipping_method_id: selectedShippingMethodId.value,
            ...(appliedCouponCode.value ? { promotion_code: appliedCouponCode.value } : {}),
            ...(appliedGiftCardCode.value ? { gift_card_code: appliedGiftCardCode.value } : {}),
            ...(!props.isAuthenticated ? { guest_email: guestEmail.value, guest_name: guestName.value } : {}),
        }),
    });

    if (!orderData.ok) {
        const errorData = await orderData.json();
        submitError.value = errorData.error?.message ?? 'Checkout failed';
        isSubmitting.value = false;
        return;
    }

    const result = await orderData.json();

    if (result.order && result.payment_intent) {
        sessionStorage.setItem('checkout_client_secret', result.payment_intent.client_secret);
        router.visit(`/checkout/pending?order_id=${result.order.id}`);
    } else {
        submitError.value = 'Invalid checkout response';
        isSubmitting.value = false;
    }
}

function getCsrfToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
}

function getIdempotencyKey(operation: string): string {
    const storageKey = `idempotency_${operation}`;
    let key = sessionStorage.getItem(storageKey);
    if (!key) {
        key = `${operation}_${Date.now()}_${Math.random().toString(36).substring(2, 11)}`;
        sessionStorage.setItem(storageKey, key);
    }
    return key;
}
</script>

<template>
    <Head title="Checkout — Review Order" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-8">

            <!-- Progress + heading -->
            <div class="mb-8">
                <CheckoutProgress :current-step="0" class="mb-6" />
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Review Your Order</h1>
                        <p class="mt-1 text-sm text-slate-500 dark:text-navy-400">
                            Check everything looks right before payment.
                        </p>
                    </div>
                    <Link
                        :href="localePath('/cart')"
                        class="hidden sm:inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 dark:text-brand-400 hover:text-brand-500 transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                        </svg>
                        Edit Cart
                    </Link>
                </div>
            </div>

            <div class="lg:grid lg:grid-cols-12 lg:gap-8">
                <!-- ── LEFT COLUMN ── -->
                <div class="lg:col-span-7 space-y-4">

                    <!-- Order items card -->
                    <div class="rounded-2xl bg-white dark:bg-navy-900/50 border border-slate-100 dark:border-navy-800/60 overflow-hidden">
                        <button
                            class="flex w-full items-center justify-between px-6 py-4 text-left hover:bg-slate-50 dark:hover:bg-navy-800/30 transition-colors"
                            @click="itemsExpanded = !itemsExpanded"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 dark:bg-brand-900/30">
                                    <svg class="h-4 w-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                                    </svg>
                                </div>
                                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Order Items</h2>
                                <span class="rounded-full bg-brand-100 dark:bg-brand-900/50 px-2 py-0.5 text-xs font-semibold text-brand-700 dark:text-brand-300">
                                    {{ cart.items.length }}
                                </span>
                            </div>
                            <svg
                                class="h-4 w-4 text-slate-400 transition-transform duration-200"
                                :class="{ 'rotate-180': itemsExpanded }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <Transition
                            enter-active-class="duration-200 ease-out"
                            enter-from-class="opacity-0 -translate-y-1"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="duration-150 ease-in"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div v-show="itemsExpanded" class="border-t border-slate-100 dark:border-navy-800/60">
                                <ul class="divide-y divide-slate-50 dark:divide-navy-800/40">
                                    <li
                                        v-for="item in cart.items"
                                        :key="item.id"
                                        class="flex gap-4 px-6 py-4"
                                    >
                                        <!-- Image -->
                                        <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-slate-100 dark:bg-navy-800">
                                            <img
                                                v-if="normalizeImages(item.product?.images).length > 0"
                                                :src="normalizeImages(item.product?.images)[0]"
                                                :alt="item.product?.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <div v-else class="flex h-full items-center justify-center text-slate-300 dark:text-navy-600">
                                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        </div>
                                        <!-- Details -->
                                        <div class="flex flex-1 items-center justify-between gap-3 min-w-0">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-900 dark:text-white truncate">
                                                    {{ item.product?.name ?? 'Product' }}
                                                </p>
                                                <p class="mt-0.5 text-xs text-slate-500 dark:text-navy-400">
                                                    Qty {{ item.quantity }} × {{ formatPrice(item.price) }}
                                                </p>
                                            </div>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white shrink-0">
                                                {{ formatPrice(item.price * item.quantity) }}
                                            </p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </Transition>
                    </div>

                    <!-- Guest details -->
                    <div v-if="!isAuthenticated" class="rounded-2xl bg-white dark:bg-navy-900/50 border border-slate-100 dark:border-navy-800/60 p-6">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-900/30">
                                <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Your Details</h2>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="guest-name" class="block text-xs font-semibold text-slate-600 dark:text-navy-300 mb-1.5 uppercase tracking-wide">
                                    Full Name
                                </label>
                                <input
                                    id="guest-name"
                                    v-model="guestName"
                                    type="text"
                                    placeholder="Jane Doe"
                                    class="w-full rounded-xl border border-slate-200 dark:border-navy-700 bg-slate-50 dark:bg-navy-900 px-4 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-navy-500 focus:border-brand-500 focus:bg-white dark:focus:bg-navy-800 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors"
                                />
                            </div>
                            <div>
                                <label for="guest-email" class="block text-xs font-semibold text-slate-600 dark:text-navy-300 mb-1.5 uppercase tracking-wide">
                                    Email <span class="text-red-400 normal-case">*</span>
                                </label>
                                <input
                                    id="guest-email"
                                    v-model="guestEmail"
                                    type="email"
                                    placeholder="you@example.com"
                                    required
                                    class="w-full rounded-xl border border-slate-200 dark:border-navy-700 bg-slate-50 dark:bg-navy-900 px-4 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-navy-500 focus:border-brand-500 focus:bg-white dark:focus:bg-navy-800 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors"
                                />
                            </div>
                        </div>
                        <p class="mt-4 text-xs text-slate-500 dark:text-navy-400">
                            Have an account?
                            <Link :href="localePath('/login')" class="font-semibold text-brand-600 dark:text-brand-400 hover:underline">Sign in for faster checkout</Link>
                        </p>
                    </div>

                    <!-- Mobile: edit cart -->
                    <div class="sm:hidden text-center">
                        <Link
                            :href="localePath('/cart')"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 dark:text-brand-400"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                            </svg>
                            Edit cart
                        </Link>
                    </div>
                </div>

                <!-- ── RIGHT COLUMN: Summary ── -->
                <div class="mt-8 lg:col-span-5 lg:mt-0">
                    <div class="rounded-2xl bg-white dark:bg-navy-900/50 border border-slate-100 dark:border-navy-800/60 p-6 sticky top-24 space-y-5">

                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Order Summary</h2>

                        <!-- Shipping methods -->
                        <div v-if="shippingMethods.length > 0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-navy-400 mb-2">Delivery</p>
                            <div class="space-y-2">
                                <label
                                    v-for="method in shippingMethods"
                                    :key="method.id"
                                    class="flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition-all"
                                    :class="selectedShippingMethodId === method.id
                                        ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20 shadow-sm shadow-brand-500/10'
                                        : 'border-slate-200 dark:border-navy-700 hover:border-brand-300 dark:hover:border-brand-800'"
                                >
                                    <input
                                        type="radio"
                                        :value="method.id"
                                        v-model="selectedShippingMethodId"
                                        class="text-brand-500 focus:ring-brand-500 shrink-0"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ method.name }}</p>
                                        <p class="text-xs text-slate-500 dark:text-navy-400">{{ method.estimated_delivery }}</p>
                                    </div>
                                    <span
                                        class="text-sm font-semibold shrink-0"
                                        :class="method.cost_cents === 0 ? 'text-accent-600 dark:text-accent-400' : 'text-slate-800 dark:text-white'"
                                    >
                                        {{ method.cost_cents === 0 ? 'Free' : formatPrice(method.cost_cents) }}
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Coupon -->
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-navy-400 mb-2">Promo Code</p>

                            <!-- Applied -->
                            <div
                                v-if="appliedCouponCode"
                                class="flex items-center gap-3 rounded-xl border border-accent-300 dark:border-accent-700 bg-accent-50 dark:bg-accent-900/20 px-4 py-2.5"
                            >
                                <svg class="h-4 w-4 text-accent-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-accent-700 dark:text-accent-300 truncate">{{ appliedCouponCode }}</p>
                                    <p class="text-xs text-accent-600 dark:text-accent-400">{{ appliedCouponName }} · −{{ formatPrice(discountCents) }}</p>
                                </div>
                                <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition-colors" @click="removePromotion">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Input -->
                            <div v-else>
                                <div class="flex gap-2">
                                    <input
                                        v-model="couponCode"
                                        type="text"
                                        placeholder="SUMMER20"
                                        :class="couponError ? 'border-red-400 dark:border-red-600' : 'border-slate-200 dark:border-navy-700'"
                                        class="flex-1 rounded-xl border bg-slate-50 dark:bg-navy-900 px-4 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:border-brand-500 focus:bg-white dark:focus:bg-navy-800 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors uppercase tracking-widest"
                                        @keydown.enter.prevent="applyPromotion"
                                    />
                                    <button
                                        type="button"
                                        :disabled="couponLoading || !couponCode.trim()"
                                        class="rounded-xl border border-slate-200 dark:border-navy-700 px-4 py-2.5 text-sm font-semibold text-slate-600 dark:text-navy-300 hover:border-brand-400 hover:text-brand-600 dark:hover:text-brand-400 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                                        @click="applyPromotion"
                                    >
                                        {{ couponLoading ? '…' : 'Apply' }}
                                    </button>
                                </div>
                                <p v-if="couponError" class="mt-1.5 text-xs text-red-500 dark:text-red-400">{{ couponError }}</p>
                            </div>
                        </div>

                        <!-- Gift Card -->
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-navy-400 mb-2">Gift Card</p>

                            <!-- Applied -->
                            <div
                                v-if="appliedGiftCardCode"
                                class="flex items-center gap-3 rounded-xl border border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-2.5"
                            >
                                <svg class="h-4 w-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300 truncate">{{ appliedGiftCardCode }}</p>
                                    <p class="text-xs text-emerald-600 dark:text-emerald-400">−{{ formatPrice(giftCardDiscountCents) }} applied</p>
                                </div>
                                <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition-colors" @click="removeGiftCard">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Input -->
                            <div v-else>
                                <div class="flex gap-2">
                                    <input
                                        v-model="giftCardCode"
                                        type="text"
                                        placeholder="GIFT CARD CODE"
                                        :class="giftCardError ? 'border-red-400 dark:border-red-600' : 'border-slate-200 dark:border-navy-700'"
                                        class="flex-1 rounded-xl border bg-slate-50 dark:bg-navy-900 px-4 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:border-brand-500 focus:bg-white dark:focus:bg-navy-800 focus:outline-none focus:ring-1 focus:ring-brand-500 transition-colors uppercase tracking-widest"
                                        @keydown.enter.prevent="applyGiftCard"
                                    />
                                    <button
                                        type="button"
                                        :disabled="giftCardLoading || !giftCardCode.trim()"
                                        class="rounded-xl border border-slate-200 dark:border-navy-700 px-4 py-2.5 text-sm font-semibold text-slate-600 dark:text-navy-300 hover:border-brand-400 hover:text-brand-600 dark:hover:text-brand-400 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                                        @click="applyGiftCard"
                                    >
                                        {{ giftCardLoading ? '…' : 'Apply' }}
                                    </button>
                                </div>
                                <p v-if="giftCardError" class="mt-1.5 text-xs text-red-500 dark:text-red-400">{{ giftCardError }}</p>
                            </div>
                        </div>

                        <!-- Price breakdown -->
                        <div class="rounded-xl bg-slate-50 dark:bg-navy-800/40 p-4 space-y-2.5">
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-navy-400">Subtotal</span>
                                <span class="font-medium text-slate-900 dark:text-white">{{ formatPrice(cart.subtotal) }}</span>
                            </div>
                            <div v-if="discountCents > 0" class="flex justify-between text-sm">
                                <span class="text-accent-600 dark:text-accent-400">Discount ({{ appliedCouponCode }})</span>
                                <span class="font-medium text-accent-600 dark:text-accent-400">−{{ formatPrice(discountCents) }}</span>
                            </div>
                            <div v-if="giftCardDiscountCents > 0" class="flex justify-between text-sm">
                                <span class="text-emerald-600 dark:text-emerald-400">Gift Card ({{ appliedGiftCardCode }})</span>
                                <span class="font-medium text-emerald-600 dark:text-emerald-400">−{{ formatPrice(giftCardDiscountCents) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-navy-400">Tax (8%)</span>
                                <span class="font-medium text-slate-900 dark:text-white">{{ formatPrice(taxCents) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-navy-400">Shipping</span>
                                <span
                                    class="font-medium"
                                    :class="shippingCents === 0 ? 'text-accent-600 dark:text-accent-400' : 'text-slate-900 dark:text-white'"
                                >
                                    {{ shippingCents === 0 ? 'Free' : formatPrice(shippingCents) }}
                                </span>
                            </div>
                            <div class="flex justify-between pt-2.5 border-t border-slate-200 dark:border-navy-700">
                                <span class="text-base font-bold text-slate-900 dark:text-white">Total</span>
                                <span class="text-base font-bold text-slate-900 dark:text-white tabular-nums">{{ formatPrice(totalCents) }}</span>
                            </div>
                        </div>

                        <!-- Error -->
                        <Alert v-if="submitError || checkoutError" variant="danger">
                            {{ submitError || checkoutError?.message }}
                        </Alert>

                        <!-- CTA button -->
                        <button
                            :disabled="isLoading"
                            class="flex w-full items-center justify-center gap-2.5 rounded-xl bg-brand-500 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-brand-500/20 hover:bg-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all hover:shadow-brand-500/30 hover:-translate-y-0.5 active:translate-y-0"
                            @click="handleInitiateCheckout"
                        >
                            <Spinner v-if="isLoading" size="sm" color="white" />
                            <svg v-else class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                            </svg>
                            {{ isLoading ? 'Processing…' : 'Continue to Payment' }}
                        </button>

                        <p class="text-xs text-center text-slate-400 dark:text-navy-500">
                            Redirected to Stripe's secure payment page
                        </p>

                        <!-- Trust badges -->
                        <div class="flex items-center justify-center gap-5 pt-2 border-t border-slate-100 dark:border-navy-800">
                            <div class="flex items-center gap-1.5 text-xs text-slate-400 dark:text-navy-500">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                                256-bit SSL
                            </div>
                            <div class="h-3 w-px bg-slate-200 dark:bg-navy-700" />
                            <div class="flex items-center gap-1.5 text-xs text-slate-400 dark:text-navy-500">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                                Stripe Protected
                            </div>
                            <div class="h-3 w-px bg-slate-200 dark:bg-navy-700" />
                            <div class="flex items-center gap-1.5 text-xs text-slate-400 dark:text-navy-500">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                Easy Returns
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
