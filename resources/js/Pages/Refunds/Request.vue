<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/UI/Modal.vue';
import Spinner from '@/Components/UI/Spinner.vue';
import { useRefunds } from '@/Composables/useRefunds';

interface OrderItem {
    id: number;
    product_name: string;
    product_image?: string;
    quantity: number;
    unit_price_cents: number;
    total_cents: number;
}

interface Order {
    id: number;
    order_number: string;
    status: string;
    total_cents: number;
    refunded_amount_cents: number;
    remaining_refundable_amount: number;
    currency: string;
    items: OrderItem[];
    created_at: string;
}

interface Props {
    order: Order;
}

const props = defineProps<Props>();

const { requestRefund, loading, error, clearError, formatPrice } = useRefunds();

const reason = ref('');
const refundType = ref<'full' | 'partial'>('full');
const customAmount = ref<number>(props.order.remaining_refundable_amount);
const showConfirmation = ref(false);

// Common refund reasons for quick selection
const quickReasons = [
    { label: 'Changed my mind', value: 'I changed my mind about this purchase.' },
    { label: 'Wrong item', value: 'I received the wrong item.' },
    { label: 'Defective/Damaged', value: 'The item arrived damaged or defective.' },
    { label: 'Not as described', value: 'The item does not match the description.' },
    { label: 'Delivery issue', value: 'There was an issue with delivery.' },
];

const refundAmount = computed(() => {
    if (refundType.value === 'full') {
        return props.order.remaining_refundable_amount;
    }
    return Math.min(customAmount.value, props.order.remaining_refundable_amount);
});

const isValidAmount = computed(() => {
    return refundAmount.value > 0 && refundAmount.value <= props.order.remaining_refundable_amount;
});

const canSubmit = computed(() => {
    return reason.value.trim().length > 0 && isValidAmount.value;
});

function selectQuickReason(value: string) {
    reason.value = value;
}

function openConfirmation() {
    if (canSubmit.value) {
        showConfirmation.value = true;
    }
}

function closeConfirmation() {
    showConfirmation.value = false;
}

async function handleSubmit() {
    closeConfirmation();
    clearError();

    const amountCents = refundType.value === 'partial' ? refundAmount.value : undefined;

    const refund = await requestRefund(props.order.id, reason.value, amountCents);

    if (refund) {
        router.visit(`/refunds/${refund.id}`);
    }
}

function formatCentsInput(value: number): string {
    return (value / 100).toFixed(2);
}
</script>

<template>
    <Head title="Request Refund" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Back link -->
            <Link
                :href="`/orders/${order.id}`"
                class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                Back to Order
            </Link>

            <!-- Header -->
            <div class="mt-6">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Request Refund</h1>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    Order <span class="font-medium text-gray-700 dark:text-gray-300">{{ order.order_number }}</span>
                </p>
            </div>

            <!-- Order summary -->
            <div class="mt-8 rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden shadow-sm">
                <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-800/50">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Order Items</h2>
                </div>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <li
                        v-for="item in order.items"
                        :key="item.id"
                        class="flex items-center gap-4 px-6 py-4"
                    >
                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-700">
                            <img
                                v-if="item.product_image"
                                :src="item.product_image"
                                :alt="item.product_name"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center">
                                <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 dark:text-white truncate">{{ item.product_name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Qty: {{ item.quantity }}</p>
                        </div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ formatPrice(item.total_cents) }}
                        </p>
                    </li>
                </ul>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Order Total</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ formatPrice(order.total_cents) }}</span>
                    </div>
                    <div v-if="order.refunded_amount_cents > 0" class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Already Refunded</span>
                        <span class="font-medium text-red-600 dark:text-red-400">-{{ formatPrice(order.refunded_amount_cents) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                        <span class="font-semibold text-gray-900 dark:text-white">Refundable Amount</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ formatPrice(order.remaining_refundable_amount) }}</span>
                    </div>
                </div>
            </div>

            <!-- Refund form -->
            <form @submit.prevent="openConfirmation" class="mt-8 space-y-8">
                <!-- Refund type -->
                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Refund Amount</h3>
                    <div class="space-y-3">
                        <label
                            :class="[
                                'flex items-center gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all',
                                refundType === 'full'
                                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                            ]"
                        >
                            <input
                                v-model="refundType"
                                type="radio"
                                value="full"
                                class="h-5 w-5 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <div class="flex-1">
                                <p class="font-medium text-gray-900 dark:text-white">Full refund</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Get back the entire refundable amount</p>
                            </div>
                            <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ formatPrice(order.remaining_refundable_amount) }}
                            </span>
                        </label>
                        <label
                            :class="[
                                'flex items-center gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all',
                                refundType === 'partial'
                                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                            ]"
                        >
                            <input
                                v-model="refundType"
                                type="radio"
                                value="partial"
                                class="h-5 w-5 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <div class="flex-1">
                                <p class="font-medium text-gray-900 dark:text-white">Partial refund</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Specify a custom amount</p>
                            </div>
                        </label>
                    </div>

                    <!-- Custom amount input -->
                    <Transition
                        enter-active-class="duration-200 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="duration-150 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div v-if="refundType === 'partial'" class="mt-4">
                            <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Custom Amount
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-500 dark:text-gray-400 font-medium">
                                    $
                                </span>
                                <input
                                    id="amount"
                                    :value="formatCentsInput(customAmount)"
                                    @input="customAmount = Math.round(parseFloat(($event.target as HTMLInputElement).value || '0') * 100)"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    :max="order.remaining_refundable_amount / 100"
                                    class="block w-full rounded-xl border-gray-300 dark:border-gray-600 py-3 pl-8 pr-4 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700"
                                />
                            </div>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Maximum: {{ formatPrice(order.remaining_refundable_amount) }}
                            </p>
                        </div>
                    </Transition>
                </div>

                <!-- Reason -->
                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Reason for Refund</h3>

                    <!-- Quick select buttons -->
                    <div class="mb-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Quick select:</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="quickReason in quickReasons"
                                :key="quickReason.label"
                                type="button"
                                @click="selectQuickReason(quickReason.value)"
                                :class="[
                                    'px-3 py-1.5 rounded-full text-sm font-medium transition-all',
                                    reason === quickReason.value
                                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 ring-2 ring-indigo-500'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                                ]"
                            >
                                {{ quickReason.label }}
                            </button>
                        </div>
                    </div>

                    <textarea
                        id="reason"
                        v-model="reason"
                        rows="4"
                        required
                        placeholder="Please provide additional details about your refund request..."
                        class="block w-full rounded-xl border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white placeholder:text-gray-400"
                    ></textarea>
                </div>

                <!-- Error message -->
                <Transition
                    enter-active-class="duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="error" class="rounded-xl bg-red-50 dark:bg-red-900/50 p-4 border border-red-200 dark:border-red-800">
                        <div class="flex items-center gap-3">
                            <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                            <p class="text-sm text-red-700 dark:text-red-200">{{ error.message }}</p>
                        </div>
                    </div>
                </Transition>

                <!-- Submit buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-end gap-3">
                    <Link
                        :href="`/orders/${order.id}`"
                        class="w-full sm:w-auto inline-flex items-center justify-center rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-6 py-3 text-base font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="!canSubmit || loading"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-indigo-500/30 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                    >
                        <Spinner v-if="loading" size="sm" color="white" />
                        {{ loading ? 'Submitting...' : 'Request Refund' }}
                    </button>
                </div>
            </form>

            <!-- Confirmation modal -->
            <Modal :show="showConfirmation" @close="closeConfirmation" max-width="md">
                <div class="p-6">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900/50">
                        <svg class="h-7 w-7 text-yellow-600 dark:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>

                    <h3 class="mt-4 text-center text-lg font-semibold text-gray-900 dark:text-white">
                        Confirm Refund Request
                    </h3>

                    <div class="mt-4 rounded-xl bg-gray-50 dark:bg-gray-800 p-4">
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Order</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ order.order_number }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Refund Amount</dt>
                                <dd class="font-semibold text-indigo-600 dark:text-indigo-400">{{ formatPrice(refundAmount) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                        This request will be reviewed by our team. Refunds typically take 5-10 business days to process.
                    </p>

                    <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        <button
                            @click="closeConfirmation"
                            class="w-full sm:w-auto inline-flex items-center justify-center rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            Go Back
                        </button>
                        <button
                            @click="handleSubmit"
                            :disabled="loading"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 transition-colors"
                        >
                            <Spinner v-if="loading" size="xs" color="white" />
                            {{ loading ? 'Submitting...' : 'Confirm Request' }}
                        </button>
                    </div>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>
