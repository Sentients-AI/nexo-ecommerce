<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Form } from '@inertiajs/vue3';
import VendorLayout from '@/Layouts/VendorLayout.vue';

interface StorefrontData {
    name: string;
    description: string | null;
    logo_path: string | null;
    banner_path: string | null;
    accent_color: string;
    social_links: {
        twitter?: string;
        instagram?: string;
        facebook?: string;
        tiktok?: string;
    };
}

interface Props {
    storefront: StorefrontData | null;
}

const props = defineProps<Props>();
</script>

<template>
    <Head title="Storefront Customization" />

    <VendorLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-white">Command Center</span>
                <span class="text-navy-600">/</span>
                <span class="text-sm text-navy-400">Storefront</span>
            </div>
        </template>

        <div class="mb-6">
            <h1 class="text-xl font-bold text-white">Storefront Customization</h1>
            <p class="mt-1 text-sm text-navy-400">Personalize your store's public appearance</p>
        </div>

        <Form
            v-if="storefront"
            action="/vendor/storefront"
            method="patch"
            #default="{ errors, processing, wasSuccessful }"
        >
            <div class="space-y-5">
                <!-- Store Identity -->
                <div class="rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5">
                    <h2 class="text-sm font-semibold text-white mb-4">Store Identity</h2>

                    <div class="space-y-4">
                        <!-- Store Description -->
                        <div>
                            <label class="block text-xs font-medium text-navy-400 mb-1.5">Store Description</label>
                            <textarea
                                name="description"
                                :value="storefront.description ?? ''"
                                rows="4"
                                placeholder="Tell customers what makes your store special…"
                                class="block w-full rounded-xl border border-navy-700 bg-navy-800 text-sm px-4 py-2.5 text-white placeholder-navy-500 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 resize-none"
                            />
                            <p v-if="errors.description" class="mt-1 text-xs text-red-400">{{ errors.description }}</p>
                        </div>

                        <!-- Logo URL -->
                        <div>
                            <label class="block text-xs font-medium text-navy-400 mb-1.5">Logo URL</label>
                            <input
                                name="logo_path"
                                type="url"
                                :value="storefront.logo_path ?? ''"
                                placeholder="https://example.com/logo.png"
                                class="block w-full rounded-xl border border-navy-700 bg-navy-800 text-sm px-4 py-2.5 text-white placeholder-navy-500 focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                            />
                            <p v-if="errors.logo_path" class="mt-1 text-xs text-red-400">{{ errors.logo_path }}</p>
                        </div>

                        <!-- Banner URL -->
                        <div>
                            <label class="block text-xs font-medium text-navy-400 mb-1.5">Banner Image URL</label>
                            <input
                                name="banner_path"
                                type="url"
                                :value="storefront.banner_path ?? ''"
                                placeholder="https://example.com/banner.jpg"
                                class="block w-full rounded-xl border border-navy-700 bg-navy-800 text-sm px-4 py-2.5 text-white placeholder-navy-500 focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                            />
                            <p v-if="errors.banner_path" class="mt-1 text-xs text-red-400">{{ errors.banner_path }}</p>
                            <!-- Banner preview -->
                            <div v-if="storefront.banner_path" class="mt-2 h-24 w-full rounded-xl overflow-hidden border border-navy-700">
                                <img :src="storefront.banner_path" alt="Banner preview" class="h-full w-full object-cover" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Theme -->
                <div class="rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5">
                    <h2 class="text-sm font-semibold text-white mb-4">Theme Color</h2>
                    <div class="flex items-center gap-4">
                        <input
                            name="accent_color"
                            type="color"
                            :value="storefront.accent_color"
                            class="h-10 w-16 cursor-pointer rounded-lg border border-navy-700 bg-navy-800 p-1"
                        />
                        <div>
                            <p class="text-sm text-white">Accent Color</p>
                            <p class="text-xs text-navy-500">Used for buttons and highlights on your storefront</p>
                        </div>
                    </div>
                    <p v-if="errors.accent_color" class="mt-1 text-xs text-red-400">{{ errors.accent_color }}</p>
                </div>

                <!-- Social Links -->
                <div class="rounded-2xl border border-navy-800/60 bg-navy-900/60 p-5">
                    <h2 class="text-sm font-semibold text-white mb-4">Social Links</h2>
                    <div class="space-y-3">
                        <div v-for="platform in ['twitter', 'instagram', 'facebook', 'tiktok']" :key="platform">
                            <label class="block text-xs font-medium text-navy-400 mb-1 capitalize">{{ platform }}</label>
                            <input
                                :name="`social_links[${platform}]`"
                                type="url"
                                :value="(storefront.social_links as Record<string, string>)[platform] ?? ''"
                                :placeholder="`https://${platform}.com/yourstore`"
                                class="block w-full rounded-xl border border-navy-700 bg-navy-800 text-sm px-4 py-2.5 text-white placeholder-navy-500 focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                            />
                            <p v-if="errors[`social_links.${platform}` as keyof typeof errors]" class="mt-1 text-xs text-red-400">
                                {{ errors[`social_links.${platform}` as keyof typeof errors] }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="processing"
                        class="rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold px-5 py-2.5 transition-colors disabled:opacity-50"
                    >
                        {{ processing ? 'Saving…' : 'Save Changes' }}
                    </button>
                    <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-300" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-300">
                        <span v-if="wasSuccessful" class="text-sm text-accent-400 font-medium">Saved!</span>
                    </Transition>
                </div>
            </div>
        </Form>

        <div v-else class="rounded-2xl border border-navy-800/60 bg-navy-900/60 p-10 text-center text-navy-500 text-sm">
            No store configured.
        </div>
    </VendorLayout>
</template>
