<script setup lang="ts">
import { onMounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useChat } from '@/Composables/useChat';
import ChatPanel from './ChatPanel.vue';

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth.user);

const { isOpen, unreadTotal, fetchConversations, openPanel, closePanel } = useChat();

onMounted(() => {
    if (isAuthenticated.value) {
        fetchConversations();
    }
});

function toggle(): void {
    if (isOpen.value) {
        closePanel();
    } else {
        openPanel();
    }
}
</script>

<template>
    <div v-if="isAuthenticated" class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
        <!-- Chat panel -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-4 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-4 scale-95"
        >
            <div
                v-if="isOpen"
                class="w-80 h-[500px] rounded-2xl shadow-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex flex-col overflow-hidden origin-bottom-right"
            >
                <ChatPanel />
            </div>
        </Transition>

        <!-- Toggle button -->
        <button
            @click="toggle"
            class="relative h-14 w-14 rounded-full bg-indigo-600 text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition-all duration-200 flex items-center justify-center"
            :aria-label="isOpen ? 'Close chat' : 'Open chat'"
        >
            <!-- Unread badge -->
            <Transition
                enter-active-class="duration-200 ease-out"
                enter-from-class="scale-0 opacity-0"
                enter-to-class="scale-100 opacity-100"
                leave-active-class="duration-150 ease-in"
                leave-from-class="scale-100 opacity-100"
                leave-to-class="scale-0 opacity-0"
            >
                <span
                    v-if="!isOpen && unreadTotal > 0"
                    class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
                >
                    {{ unreadTotal > 99 ? '99+' : unreadTotal }}
                </span>
            </Transition>

            <!-- Chat icon (closed) -->
            <Transition
                enter-active-class="duration-200 ease-out"
                enter-from-class="opacity-0 rotate-90"
                enter-to-class="opacity-100 rotate-0"
                leave-active-class="duration-150 ease-in absolute"
                leave-from-class="opacity-100 rotate-0"
                leave-to-class="opacity-0 rotate-90"
                mode="out-in"
            >
                <svg
                    v-if="!isOpen"
                    class="h-6 w-6"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                </svg>
                <!-- X icon (open) -->
                <svg
                    v-else
                    class="h-6 w-6"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </Transition>
        </button>
    </div>
</template>
