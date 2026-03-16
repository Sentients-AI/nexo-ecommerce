import { onUnmounted } from 'vue';

interface OrderStatusUpdatedPayload {
    order_id: number;
    order_number: string;
    status: string;
    occurred_at: string;
}

type OrderUpdateCallback = (payload: OrderStatusUpdatedPayload) => void;

export function useOrderUpdates(userId: number, onUpdate: OrderUpdateCallback) {
    const channelName = `orders.${userId}`;

    if (!window.Echo) {
        return;
    }

    window.Echo.private(channelName).listen('.order.status.updated', onUpdate);

    onUnmounted(() => {
        window.Echo?.leave(channelName);
    });
}
