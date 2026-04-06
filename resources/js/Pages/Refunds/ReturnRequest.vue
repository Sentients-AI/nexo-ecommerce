<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useCurrency } from '@/Composables/useCurrency';

interface OrderItem {
    id: number;
    product_name: string;
    quantity: number;
    unit_price_cents: number;
    total_cents: number;
}

interface ReasonOption {
    value: string;
    label: string;
}

interface Props {
    order: {
        id: number;
        order_number: string;
        status: string;
        currency: string;
        items: OrderItem[];
    };
    reasons: ReasonOption[];
}

const props = defineProps<Props>();
const { formatPrice } = useCurrency();

interface SelectedItem {
    order_item_id: number;
    quantity: number;
    reason: string;
}

const selectedItems = ref<Record<number, SelectedItem & { selected: boolean }>>({});

// Initialise one entry per order item
props.order.items.forEach(item => {
    selectedItems.value[item.id] = {
        order_item_id: item.id,
        quantity: 1,
        reason: '',
        selected: false,
    };
});

const form = useForm({
    notes: '',
    items: [] as SelectedItem[],
});

const checkedItems = computed(() =>
    Object.values(selectedItems.value).filter(s => s.selected)
);

const totalRefundCents = computed(() =>
    checkedItems.value.reduce((sum, s) => {
        const item = props.order.items.find(i => i.id === s.order_item_id);
        return sum + (item ? item.unit_price_cents * s.quantity : 0);
    }, 0)
);

const isValid = computed(() =>
    checkedItems.value.length > 0 &&
    checkedItems.value.every(s => s.reason !== '')
);

function submit() {
    form.items = checkedItems.value.map(({ order_item_id, quantity, reason }) => ({
        order_item_id,
        quantity,
        reason,
    }));
    form.post(route('returns.store', { orderId: props.order.id }));
}
</script>

<template>
    <Head title="Request a Return" />

    <AuthenticatedLayout>
        <div class="max-w-2xl mx-auto px-4 py-8">
            <div class="mb-6">
                <Link
                    :href="route('orders.show', { orderId: order.id, locale: 'en' })"
                    class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center gap-1 mb-4"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Back to order
                </Link>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Request a Return</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Order {{ order.order_number }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Item selection -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
                    <div class="px-5 py-4">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Select items to return</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Choose at least one item and provide a reason</p>
                    </div>

                    <div
                        v-for="item in order.items"
                        :key="item.id"
                        class="px-5 py-4"
                    >
                        <div class="flex items-start gap-3">
                            <input
                                :id="`item-${item.id}`"
                                v-model="selectedItems[item.id].selected"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <div class="flex-1 min-w-0">
                                <label :for="`item-${item.id}`" class="text-sm font-medium text-gray-900 dark:text-white cursor-pointer">
                                    {{ item.product_name }}
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ formatPrice(item.unit_price_cents) }} × {{ item.quantity }}
                                </p>

                                <div v-if="selectedItems[item.id].selected" class="mt-3 space-y-3">
                                    <!-- Quantity -->
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Quantity to return
                                        </label>
                                        <select
                                            v-model.number="selectedItems[item.id].quantity"
                                            class="block w-24 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm px-3 py-1.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        >
                                            <option v-for="n in item.quantity" :key="n" :value="n">{{ n }}</option>
                                        </select>
                                    </div>

                                    <!-- Reason -->
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Reason <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            v-model="selectedItems[item.id].reason"
                                            class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm px-3 py-1.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                            :class="{ 'border-red-500': selectedItems[item.id].selected && !selectedItems[item.id].reason }"
                                        >
                                            <option value="">Select a reason…</option>
                                            <option v-for="r in reasons" :key="r.value" :value="r.value">{{ r.label }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 shrink-0">
                                {{ formatPrice(item.total_cents) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Additional notes (optional)
                    </label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        placeholder="Describe the issue in more detail…"
                        class="block w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm px-4 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"
                    />
                    <p v-if="form.errors.notes" class="mt-1 text-xs text-red-500">{{ form.errors.notes }}</p>
                </div>

                <!-- Validation error -->
                <p v-if="form.errors.items" class="text-sm text-red-500">{{ form.errors.items }}</p>

                <!-- Summary + submit -->
                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800/60 rounded-2xl border border-gray-200 dark:border-gray-700 px-5 py-4">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Estimated refund</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ formatPrice(totalRefundCents) }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        :disabled="!isValid || form.processing"
                        class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        {{ form.processing ? 'Submitting…' : 'Submit Return Request' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
