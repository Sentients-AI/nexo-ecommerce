<script setup lang="ts">
import { ref, nextTick, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useChat } from '@/Composables/useChat';
import type { ConversationType } from '@/types/api';

type View = 'list' | 'conversation' | 'new';

const {
    conversations,
    activeConversation,
    isLoading,
    sendMessage,
    openConversation,
    startConversation,
} = useChat();

const page = usePage();
const currentView = ref<View>('list');
const messageBody = ref('');
const isSending = ref(false);
const messagesContainer = ref<HTMLElement | null>(null);

// New conversation form
const newType = ref<ConversationType>('store');
const newSubject = ref('');
const newInitialMessage = ref('');
const isStarting = ref(false);

async function handleOpenConversation(id: number): Promise<void> {
    await openConversation(id);
    currentView.value = 'conversation';
    await scrollToBottom();
}

async function handleSend(): Promise<void> {
    if (!messageBody.value.trim() || isSending.value) {
        return;
    }

    isSending.value = true;
    const success = await sendMessage(messageBody.value.trim());
    if (success) {
        messageBody.value = '';
        await scrollToBottom();
    }
    isSending.value = false;
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleSend();
    }
}

async function handleStartConversation(): Promise<void> {
    if (!newInitialMessage.value.trim() || isStarting.value) {
        return;
    }

    isStarting.value = true;
    const conversation = await startConversation({
        type: newType.value,
        subject: newSubject.value.trim() || undefined,
        initial_message: newInitialMessage.value.trim(),
    });

    if (conversation) {
        newSubject.value = '';
        newInitialMessage.value = '';
        currentView.value = 'conversation';
        await scrollToBottom();
    }
    isStarting.value = false;
}

async function scrollToBottom(): Promise<void> {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
}

watch(
    () => activeConversation.value?.messages?.length,
    async () => {
        if (currentView.value === 'conversation') {
            await scrollToBottom();
        }
    }
);

const currentUserId = page.props.auth?.user?.id;

function formatTime(isoString: string): string {
    return new Date(isoString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatDate(isoString: string | null): string {
    if (!isoString) {
        return '';
    }
    const date = new Date(isoString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    if (diffDays === 1) {
        return 'Yesterday';
    }
    return date.toLocaleDateString();
}
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- List view -->
        <template v-if="currentView === 'list'">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Support</h3>
                <button
                    @click="currentView = 'new'"
                    class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-medium"
                >
                    + New
                </button>
            </div>

            <!-- Loading skeleton -->
            <div v-if="isLoading" class="flex-1 overflow-y-auto p-3 space-y-2">
                <div
                    v-for="i in 3"
                    :key="i"
                    class="h-14 rounded-lg bg-gray-200 dark:bg-gray-700 animate-pulse"
                />
            </div>

            <!-- Empty state -->
            <div
                v-else-if="conversations.length === 0"
                class="flex-1 flex flex-col items-center justify-center p-6 text-center"
            >
                <svg class="h-10 w-10 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">No conversations yet</p>
                <button
                    @click="currentView = 'new'"
                    class="text-xs bg-indigo-600 text-white px-3 py-1.5 rounded-md hover:bg-indigo-700 transition-colors"
                >
                    Start one
                </button>
            </div>

            <!-- Conversation list -->
            <ul v-else class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                <li
                    v-for="conv in conversations"
                    :key="conv.id"
                    @click="handleOpenConversation(conv.id)"
                    class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-750 cursor-pointer transition-colors"
                >
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate">
                                {{ conv.subject ?? (conv.type === 'support' ? 'Platform Support' : 'Store Support') }}
                            </span>
                            <span class="text-xs text-gray-400 shrink-0">{{ formatDate(conv.last_message_at) }}</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                            {{ conv.latest_message?.body ?? 'No messages yet' }}
                        </p>
                    </div>
                    <span
                        v-if="(conv.unread_count ?? 0) > 0"
                        class="shrink-0 flex h-5 w-5 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white"
                    >
                        {{ conv.unread_count }}
                    </span>
                </li>
            </ul>
        </template>

        <!-- Conversation view -->
        <template v-else-if="currentView === 'conversation'">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <button
                    @click="currentView = 'list'"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </button>
                <span class="text-sm font-medium text-gray-900 dark:text-white truncate">
                    {{ activeConversation?.subject ?? (activeConversation?.type === 'support' ? 'Platform Support' : 'Store Support') }}
                </span>
                <span
                    v-if="activeConversation?.status === 'closed'"
                    class="ml-auto text-xs bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded-full"
                >
                    Closed
                </span>
            </div>

            <!-- Messages -->
            <div ref="messagesContainer" class="flex-1 overflow-y-auto p-3 space-y-2">
                <div
                    v-for="msg in activeConversation?.messages ?? []"
                    :key="msg.id"
                    :class="['flex', msg.sender_id === currentUserId ? 'justify-end' : 'justify-start']"
                >
                    <div
                        :class="[
                            'max-w-[75%] px-3 py-2 rounded-2xl text-sm',
                            msg.sender_id === currentUserId
                                ? 'bg-indigo-600 text-white rounded-br-sm'
                                : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-bl-sm'
                        ]"
                    >
                        <p class="whitespace-pre-wrap break-words">{{ msg.body }}</p>
                        <p
                            :class="[
                                'text-[10px] mt-0.5',
                                msg.sender_id === currentUserId ? 'text-indigo-200 text-right' : 'text-gray-400'
                            ]"
                        >
                            {{ formatTime(msg.created_at) }}
                        </p>
                    </div>
                </div>

                <p v-if="(activeConversation?.messages ?? []).length === 0" class="text-xs text-center text-gray-400 py-4">
                    No messages yet
                </p>
            </div>

            <!-- Input -->
            <div v-if="activeConversation?.status === 'open'" class="border-t border-gray-200 dark:border-gray-700 p-3">
                <div class="flex items-end gap-2">
                    <textarea
                        v-model="messageBody"
                        @keydown="handleKeydown"
                        placeholder="Type a message..."
                        rows="1"
                        class="flex-1 resize-none rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 max-h-24 overflow-y-auto"
                    />
                    <button
                        @click="handleSend"
                        :disabled="!messageBody.trim() || isSending"
                        class="shrink-0 rounded-lg bg-indigo-600 p-2 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                        </svg>
                    </button>
                </div>
                <p class="text-[10px] text-gray-400 mt-1">Enter to send · Shift+Enter for newline</p>
            </div>
        </template>

        <!-- New conversation view -->
        <template v-else-if="currentView === 'new'">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <button
                    @click="currentView = 'list'"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </button>
                <span class="text-sm font-medium text-gray-900 dark:text-white">New Conversation</span>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                <!-- Type selector -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Contact</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            @click="newType = 'store'"
                            :class="[
                                'rounded-lg border px-3 py-2 text-xs font-medium transition-colors',
                                newType === 'store'
                                    ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-indigo-400'
                            ]"
                        >
                            Store Support
                        </button>
                        <button
                            @click="newType = 'support'"
                            :class="[
                                'rounded-lg border px-3 py-2 text-xs font-medium transition-colors',
                                newType === 'support'
                                    ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-indigo-400'
                            ]"
                        >
                            Platform Support
                        </button>
                    </div>
                </div>

                <!-- Subject -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Subject (optional)</label>
                    <input
                        v-model="newSubject"
                        type="text"
                        placeholder="e.g. Order issue, Returns..."
                        maxlength="255"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <!-- Message -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Message</label>
                    <textarea
                        v-model="newInitialMessage"
                        placeholder="Describe your issue or question..."
                        rows="4"
                        maxlength="5000"
                        class="w-full resize-none rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <button
                    @click="handleStartConversation"
                    :disabled="!newInitialMessage.trim() || isStarting"
                    class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    {{ isStarting ? 'Sending...' : 'Send Message' }}
                </button>
            </div>
        </template>
    </div>
</template>
