<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useLocale } from '@/Composables/useLocale';

interface NotificationItem {
    id: string;
    type: string;
    message: string;
    url: string | null;
    read_at: string | null;
    created_at: string;
}

interface PaginatedNotifications {
    data: NotificationItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface Props {
    notifications: PaginatedNotifications;
}

const props = defineProps<Props>();
const { localePath } = useLocale();

function iconForType(type: string): string {
    const icons: Record<string, string> = {
        order_status_changed: '📦',
        order_shipped: '🚚',
        refund_approved: '💰',
        loyalty_points_earned: '⭐',
    };
    return icons[type] ?? '🔔';
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function markRead(id: string): void {
    router.patch(localePath(`/notifications/${id}/read`), {}, { preserveScroll: true });
}

function markAllRead(): void {
    router.patch(localePath('/notifications/read-all'), {}, { preserveScroll: true });
}

function deleteNotification(id: string): void {
    router.delete(localePath(`/notifications/${id}`), { preserveScroll: true });
}
</script>

<template>
    <Head title="Notifications" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Notifications</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-navy-400">{{ notifications.total }} total</p>
                </div>
                <button
                    v-if="notifications.data.some(n => !n.read_at)"
                    type="button"
                    class="text-sm text-brand-600 dark:text-brand-400 hover:text-brand-500 font-medium transition-colors"
                    @click="markAllRead"
                >
                    Mark all as read
                </button>
            </div>

            <!-- Empty state -->
            <div v-if="notifications.data.length === 0" class="flex flex-col items-center justify-center py-20 rounded-2xl border border-slate-200 dark:border-navy-800 bg-white dark:bg-navy-900">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 dark:bg-navy-800 mb-4">
                    <svg class="h-7 w-7 text-slate-400 dark:text-navy-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                </div>
                <p class="text-slate-500 dark:text-navy-400">You're all caught up!</p>
                <p class="mt-1 text-sm text-slate-400 dark:text-navy-500">No notifications yet.</p>
            </div>

            <!-- List -->
            <div v-else class="space-y-2">
                <div
                    v-for="n in notifications.data"
                    :key="n.id"
                    class="flex gap-4 rounded-xl border p-4 transition-colors"
                    :class="n.read_at
                        ? 'border-slate-200 bg-white dark:border-navy-800 dark:bg-navy-900'
                        : 'border-brand-200 bg-brand-50 dark:border-brand-800/40 dark:bg-brand-900/20'"
                >
                    <span class="mt-0.5 text-xl shrink-0">{{ iconForType(n.type) }}</span>

                    <div class="flex-1 min-w-0">
                        <component
                            :is="n.url ? Link : 'p'"
                            :href="n.url ?? undefined"
                            class="text-sm text-slate-800 dark:text-navy-100"
                            :class="n.url ? 'hover:text-brand-600 dark:hover:text-brand-400 cursor-pointer' : ''"
                            @click="n.url && markRead(n.id)"
                        >
                            {{ n.message }}
                        </component>
                        <p class="mt-1 text-xs text-slate-400 dark:text-navy-500">{{ formatDate(n.created_at) }}</p>
                    </div>

                    <div class="flex items-start gap-2 shrink-0">
                        <button
                            v-if="!n.read_at"
                            type="button"
                            class="text-xs text-brand-600 dark:text-brand-400 hover:text-brand-500 font-medium transition-colors whitespace-nowrap"
                            @click="markRead(n.id)"
                        >
                            Mark read
                        </button>
                        <button
                            type="button"
                            class="text-slate-300 hover:text-red-400 dark:text-navy-600 dark:hover:text-red-500 transition-colors"
                            title="Delete"
                            @click="deleteNotification(n.id)"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="notifications.last_page > 1" class="mt-6 flex items-center justify-center gap-2">
                <Link
                    v-if="notifications.prev_page_url"
                    :href="notifications.prev_page_url"
                    class="rounded-lg border border-slate-200 dark:border-navy-700 px-3 py-1.5 text-sm text-slate-600 dark:text-navy-300 hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                >
                    Previous
                </Link>
                <span class="text-sm text-slate-500 dark:text-navy-400">
                    Page {{ notifications.current_page }} of {{ notifications.last_page }}
                </span>
                <Link
                    v-if="notifications.next_page_url"
                    :href="notifications.next_page_url"
                    class="rounded-lg border border-slate-200 dark:border-navy-700 px-3 py-1.5 text-sm text-slate-600 dark:text-navy-300 hover:bg-slate-50 dark:hover:bg-navy-800 transition-colors"
                >
                    Next
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
