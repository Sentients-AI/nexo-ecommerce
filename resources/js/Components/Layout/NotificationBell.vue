<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useLocale } from '@/Composables/useLocale';

interface NotificationItem {
    id: string;
    type: string;
    message: string;
    url: string | null;
    read_at: string | null;
    created_at: string;
}

interface RecentNotificationsResponse {
    items: NotificationItem[];
}

const { localePath } = useLocale();
const page = usePage();

const open = ref(false);
const items = ref<NotificationItem[]>([]);
const unreadCount = ref<number>((page.props as any).unread_notifications_count ?? 0);

async function fetchRecent(): Promise<void> {
    try {
        const response = await fetch('/api/v1/notifications/recent', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (response.ok) {
            const data: RecentNotificationsResponse = await response.json();
            items.value = data.items;
        }
    } catch {
        // silent fail
    }
}

function toggle(): void {
    open.value = !open.value;
    if (open.value) {
        fetchRecent();
    }
}

function close(): void {
    open.value = false;
}

function handleClickOutside(e: MouseEvent): void {
    const el = document.getElementById('notification-bell');
    if (el && !el.contains(e.target as Node)) {
        close();
    }
}

function markRead(id: string): void {
    router.patch(localePath(`/notifications/${id}/read`), {}, {
        preserveScroll: true,
        onSuccess: () => {
            const n = items.value.find((i) => i.id === id);
            if (n && !n.read_at) {
                n.read_at = new Date().toISOString();
                unreadCount.value = Math.max(0, unreadCount.value - 1);
            }
        },
    });
}

function markAllRead(): void {
    router.patch(localePath('/notifications/read-all'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            items.value.forEach((n) => { n.read_at = n.read_at ?? new Date().toISOString(); });
            unreadCount.value = 0;
        },
    });
}

function formatTime(iso: string): string {
    const d = new Date(iso);
    const now = new Date();
    const diff = Math.floor((now.getTime() - d.getTime()) / 1000);
    if (diff < 60) { return 'just now'; }
    if (diff < 3600) { return `${Math.floor(diff / 60)}m ago`; }
    if (diff < 86400) { return `${Math.floor(diff / 3600)}h ago`; }
    return `${Math.floor(diff / 86400)}d ago`;
}

function iconForType(type: string): string {
    const icons: Record<string, string> = {
        order_status_changed: '📦',
        order_shipped: '🚚',
        refund_approved: '💰',
        loyalty_points_earned: '⭐',
    };
    return icons[type] ?? '🔔';
}

// Real-time via Echo
let channel: any = null;

onMounted(() => {
    document.addEventListener('click', handleClickOutside);

    const userId = (page.props as any).auth?.user?.id;
    if (userId && window.Echo) {
        channel = window.Echo.private(`App.Models.User.${userId}`)
            .notification((notification: NotificationItem & { message: string }) => {
                unreadCount.value += 1;
                if (open.value) {
                    items.value.unshift({
                        id: (notification as any).id ?? crypto.randomUUID(),
                        type: (notification as any).type ?? 'general',
                        message: notification.message,
                        url: (notification as any).url ?? null,
                        read_at: null,
                        created_at: new Date().toISOString(),
                    });
                }
            });
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    channel?.stopListening('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated');
});
</script>

<template>
    <div id="notification-bell" class="relative">
        <button
            type="button"
            class="relative flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 dark:text-navy-400 dark:hover:text-white dark:hover:bg-navy-800 transition-colors"
            :aria-label="`Notifications (${unreadCount} unread)`"
            @click="toggle"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>
            <span
                v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white leading-none"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <!-- Dropdown -->
        <Transition
            enter-active-class="duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="duration-100 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 -translate-y-1"
        >
            <div
                v-if="open"
                class="absolute right-0 top-11 z-50 w-80 rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-navy-700 dark:bg-navy-900 origin-top-right overflow-hidden"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-navy-800">
                    <span class="text-sm font-semibold text-slate-900 dark:text-white">Notifications</span>
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        class="text-xs text-brand-500 hover:text-brand-400 transition-colors font-medium"
                        @click="markAllRead"
                    >
                        Mark all read
                    </button>
                </div>

                <!-- Empty state -->
                <div v-if="items.length === 0" class="flex flex-col items-center justify-center py-10">
                    <svg class="h-8 w-8 text-slate-300 dark:text-navy-600 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                    <p class="text-xs text-slate-400 dark:text-navy-500">No notifications yet</p>
                </div>

                <!-- List -->
                <ul v-else class="divide-y divide-slate-100 dark:divide-navy-800 max-h-80 overflow-y-auto">
                    <li
                        v-for="n in items"
                        :key="n.id"
                        class="flex gap-3 px-4 py-3 transition-colors"
                        :class="n.read_at ? 'bg-white dark:bg-navy-900' : 'bg-brand-50 dark:bg-brand-900/20'"
                    >
                        <span class="mt-0.5 text-base shrink-0">{{ iconForType(n.type) }}</span>
                        <div class="flex-1 min-w-0">
                            <component
                                :is="n.url ? Link : 'span'"
                                :href="n.url ?? undefined"
                                class="text-xs text-slate-700 dark:text-navy-200 line-clamp-2"
                                :class="n.url ? 'hover:text-brand-600 dark:hover:text-brand-400 cursor-pointer' : ''"
                                @click="n.url && markRead(n.id); close()"
                            >
                                {{ n.message }}
                            </component>
                            <span class="mt-0.5 block text-[11px] text-slate-400 dark:text-navy-500">{{ formatTime(n.created_at) }}</span>
                        </div>
                        <button
                            v-if="!n.read_at"
                            type="button"
                            class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand-500 hover:bg-brand-400"
                            title="Mark as read"
                            @click.stop="markRead(n.id)"
                        />
                    </li>
                </ul>

                <!-- Footer -->
                <div class="border-t border-slate-100 dark:border-navy-800 px-4 py-2.5 text-center">
                    <Link
                        :href="localePath('/notifications')"
                        class="text-xs text-brand-500 hover:text-brand-400 font-medium transition-colors"
                        @click="close"
                    >
                        View all notifications
                    </Link>
                </div>
            </div>
        </Transition>
    </div>
</template>
