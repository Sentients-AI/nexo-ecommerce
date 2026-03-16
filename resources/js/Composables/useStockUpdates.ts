import { onUnmounted, type Ref } from 'vue';

interface StockUpdatedPayload {
    product_id: number;
    quantity_available: number;
    quantity_reserved: number;
    quantity_in_stock: number;
    change_type: string;
    occurred_at: string;
}

type StockUpdateCallback = (payload: StockUpdatedPayload) => void;

export function useStockUpdates(productId: number, onUpdate: StockUpdateCallback) {
    const channelName = `product.${productId}`;

    if (!window.Echo) {
        return;
    }

    window.Echo.channel(channelName).listen('.stock.updated', onUpdate);

    onUnmounted(() => {
        window.Echo?.leaveChannel(channelName);
    });
}

export function usePriceUpdates(productId: number, onUpdate: (payload: { product_id: number; price_cents: number; sale_price: number | null; occurred_at: string }) => void) {
    const channelName = `product.${productId}`;

    if (!window.Echo) {
        return;
    }

    window.Echo.channel(channelName).listen('.price.updated', onUpdate);

    onUnmounted(() => {
        window.Echo?.leaveChannel(channelName);
    });
}
