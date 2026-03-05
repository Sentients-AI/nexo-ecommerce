import { ref, computed } from 'vue';
import { useApi } from './useApi';
import type { ConversationApiResource, ChatMessageApiResource, StoreConversationRequest, PaginatedApiResponse } from '@/types/api';

// Module-level shared state
const conversations = ref<ConversationApiResource[]>([]);
const activeConversation = ref<ConversationApiResource | null>(null);
const isOpen = ref(false);
const isLoading = ref(false);
const subscribedChannels = new Set<number>();

const unreadTotal = computed(() =>
    conversations.value.reduce((total, conv) => total + (conv.unread_count ?? 0), 0)
);

export function useChat() {
    const { get, post, patch, error } = useApi();

    async function fetchConversations(): Promise<void> {
        isLoading.value = true;
        const result = await get<PaginatedApiResponse<ConversationApiResource>>('/api/v1/conversations');
        if (result?.data) {
            conversations.value = result.data;
            conversations.value.forEach((conv) => {
                subscribeToConversation(conv.id);
            });
        }
        isLoading.value = false;
    }

    async function openConversation(id: number): Promise<void> {
        const found = conversations.value.find((c) => c.id === id);
        if (found) {
            activeConversation.value = found;
        }

        const result = await get<{ conversation: ConversationApiResource }>(`/api/v1/conversations/${id}`);
        if (result?.conversation) {
            activeConversation.value = result.conversation;
            subscribeToConversation(id);
            await markRead(id);
        }
    }

    async function startConversation(payload: StoreConversationRequest): Promise<ConversationApiResource | null> {
        const result = await post<{ conversation: ConversationApiResource }>('/api/v1/conversations', payload as unknown as Record<string, unknown>);
        if (result?.conversation) {
            conversations.value.unshift(result.conversation);
            activeConversation.value = result.conversation;
            subscribeToConversation(result.conversation.id);
            return result.conversation;
        }
        return null;
    }

    async function sendMessage(body: string): Promise<boolean> {
        if (!activeConversation.value) {
            return false;
        }

        const result = await post<{ message: ChatMessageApiResource }>(
            `/api/v1/conversations/${activeConversation.value.id}/messages`,
            { body }
        );

        if (result?.message) {
            if (activeConversation.value.messages) {
                activeConversation.value.messages.push(result.message);
            } else {
                activeConversation.value.messages = [result.message];
            }
            activeConversation.value.latest_message = result.message;
            return true;
        }

        return false;
    }

    async function markRead(conversationId: number): Promise<void> {
        await post(`/api/v1/conversations/${conversationId}/read`);

        const conv = conversations.value.find((c) => c.id === conversationId);
        if (conv) {
            conv.unread_count = 0;
        }

        if (activeConversation.value?.id === conversationId) {
            if (activeConversation.value.messages) {
                activeConversation.value.messages.forEach((m) => {
                    m.read_at = m.read_at ?? new Date().toISOString();
                });
            }
        }
    }

    function openPanel(): void {
        isOpen.value = true;
    }

    function closePanel(): void {
        isOpen.value = false;
    }

    function subscribeToConversation(id: number): void {
        if (subscribedChannels.has(id) || !window.Echo) {
            return;
        }

        subscribedChannels.add(id);

        window.Echo.private(`conversation.${id}`).listen('.message.sent', (data: {
            id: number;
            conversation_id: number;
            sender_id: number;
            sender_name: string;
            body: string;
            created_at: string;
        }) => {
            const message: ChatMessageApiResource = {
                id: data.id,
                conversation_id: data.conversation_id,
                sender_id: data.sender_id,
                sender_name: data.sender_name,
                body: data.body,
                read_at: null,
                created_at: data.created_at,
            };

            // Update active conversation messages
            if (activeConversation.value?.id === data.conversation_id) {
                if (activeConversation.value.messages) {
                    const exists = activeConversation.value.messages.some((m) => m.id === data.id);
                    if (!exists) {
                        activeConversation.value.messages.push(message);
                    }
                } else {
                    activeConversation.value.messages = [message];
                }
                activeConversation.value.latest_message = message;
            }

            // Update conversation list
            const conv = conversations.value.find((c) => c.id === data.conversation_id);
            if (conv) {
                conv.latest_message = message;
                conv.last_message_at = data.created_at;
                if (activeConversation.value?.id !== data.conversation_id) {
                    conv.unread_count = (conv.unread_count ?? 0) + 1;
                }
            }
        });
    }

    return {
        conversations,
        activeConversation,
        isOpen,
        isLoading,
        unreadTotal,
        error,
        fetchConversations,
        openConversation,
        startConversation,
        sendMessage,
        markRead,
        openPanel,
        closePanel,
        subscribeToConversation,
    };
}
