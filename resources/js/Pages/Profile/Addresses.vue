<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useLocale } from '@/Composables/useLocale';

interface Address {
    id: number;
    name: string;
    phone: string | null;
    address_line_1: string;
    address_line_2: string | null;
    city: string;
    state: string | null;
    postal_code: string;
    country: string;
    is_default: boolean;
}

interface Props {
    addresses: Address[];
}

const props = defineProps<Props>();
const { localePath } = useLocale();

const showForm = ref(false);
const editingAddress = ref<Address | null>(null);

const form = useForm({
    name: '',
    phone: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    postal_code: '',
    country: 'MY',
    is_default: false,
});

function openCreateForm() {
    editingAddress.value = null;
    form.reset();
    form.country = 'MY';
    showForm.value = true;
}

function openEditForm(address: Address) {
    editingAddress.value = address;
    form.name = address.name;
    form.phone = address.phone ?? '';
    form.address_line_1 = address.address_line_1;
    form.address_line_2 = address.address_line_2 ?? '';
    form.city = address.city;
    form.state = address.state ?? '';
    form.postal_code = address.postal_code;
    form.country = address.country;
    form.is_default = address.is_default;
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingAddress.value = null;
    form.reset();
    form.clearErrors();
}

function submitForm() {
    if (editingAddress.value) {
        form.patch(localePath(`/addresses/${editingAddress.value.id}`), {
            onSuccess: () => closeForm(),
        });
    } else {
        form.post(localePath('/addresses'), {
            onSuccess: () => closeForm(),
        });
    }
}

function deleteAddress(address: Address) {
    if (!confirm(`Delete "${address.name}"?`)) {
        return;
    }
    router.delete(localePath(`/addresses/${address.id}`));
}

function setDefault(address: Address) {
    router.patch(localePath(`/addresses/${address.id}/default`));
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Saved Addresses" />

        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Saved Addresses</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage your shipping addresses for faster checkout.
                    </p>
                </div>
                <button
                    v-if="!showForm"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none"
                    @click="openCreateForm"
                >
                    + Add Address
                </button>
            </div>

            <!-- Add / Edit Form -->
            <div v-if="showForm" class="mb-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-5 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ editingAddress ? 'Edit Address' : 'New Address' }}
                </h2>

                <form class="space-y-4" @submit.prevent="submitForm">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Label / Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="e.g. Home, Office"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                            <input
                                v-model="form.phone"
                                type="tel"
                                placeholder="+60 12-345 6789"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Address Line 1</label>
                        <input
                            v-model="form.address_line_1"
                            type="text"
                            placeholder="Street address"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        <p v-if="form.errors.address_line_1" class="mt-1 text-xs text-red-500">{{ form.errors.address_line_1 }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Address Line 2 <span class="text-gray-400">(optional)</span></label>
                        <input
                            v-model="form.address_line_2"
                            type="text"
                            placeholder="Apartment, suite, unit, etc."
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">City</label>
                            <input
                                v-model="form.city"
                                type="text"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            <p v-if="form.errors.city" class="mt-1 text-xs text-red-500">{{ form.errors.city }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">State</label>
                            <input
                                v-model="form.state"
                                type="text"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Postal Code</label>
                            <input
                                v-model="form.postal_code"
                                type="text"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            <p v-if="form.errors.postal_code" class="mt-1 text-xs text-red-500">{{ form.errors.postal_code }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Country</label>
                        <select
                            v-model="form.country"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="MY">Malaysia</option>
                            <option value="SG">Singapore</option>
                            <option value="US">United States</option>
                            <option value="GB">United Kingdom</option>
                            <option value="AU">Australia</option>
                        </select>
                        <p v-if="form.errors.country" class="mt-1 text-xs text-red-500">{{ form.errors.country }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="is_default"
                            v-model="form.is_default"
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600"
                        />
                        <label for="is_default" class="text-sm text-gray-700 dark:text-gray-300">Set as default address</label>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            @click="closeForm"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                        >
                            {{ form.processing ? 'Saving...' : (editingAddress ? 'Save Changes' : 'Add Address') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Empty State -->
            <div v-if="!showForm && props.addresses.length === 0" class="rounded-xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-600">
                <svg class="mx-auto mb-4 size-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <p class="mb-3 text-gray-500 dark:text-gray-400">No saved addresses yet.</p>
                <button
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    @click="openCreateForm"
                >
                    Add your first address
                </button>
            </div>

            <!-- Address Cards -->
            <div v-else-if="!showForm" class="space-y-4">
                <div
                    v-for="address in props.addresses"
                    :key="address.id"
                    class="relative rounded-xl border bg-white p-5 shadow-sm dark:bg-gray-800"
                    :class="address.is_default ? 'border-indigo-300 dark:border-indigo-600' : 'border-gray-200 dark:border-gray-700'"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex items-center gap-2">
                                <span class="font-semibold text-gray-900 dark:text-white">{{ address.name }}</span>
                                <span
                                    v-if="address.is_default"
                                    class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300"
                                >
                                    Default
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ address.address_line_1 }}</p>
                            <p v-if="address.address_line_2" class="text-sm text-gray-600 dark:text-gray-400">{{ address.address_line_2 }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ address.city }}<span v-if="address.state">, {{ address.state }}</span> {{ address.postal_code }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ address.country }}</p>
                            <p v-if="address.phone" class="mt-1 text-sm text-gray-500 dark:text-gray-500">{{ address.phone }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex shrink-0 gap-2">
                            <button
                                v-if="!address.is_default"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                                @click="setDefault(address)"
                            >
                                Set Default
                            </button>
                            <button
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                                @click="openEditForm(address)"
                            >
                                Edit
                            </button>
                            <button
                                class="rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
                                @click="deleteAddress(address)"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
