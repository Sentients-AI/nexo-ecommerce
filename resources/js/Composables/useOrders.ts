import { ref } from 'vue';
import { useApi } from './useApi';
import type { OrderApiResource, PaginatedApiResponse } from '@/types/api';

export function useOrders() {
    const { loading, error, get, clearError } = useApi();

    const orders = ref<OrderApiResource[]>([]);
    const currentOrder = ref<OrderApiResource | null>(null);

    async function fetchOrders(page: number = 1): Promise<PaginatedApiResponse<OrderApiResource> | null> {
        const result = await get<PaginatedApiResponse<OrderApiResource>>('/api/v1/orders', { page });
        if (result) {
            orders.value = result.data;
        }
        return result;
    }

    async function fetchOrder(orderId: number): Promise<OrderApiResource | null> {
        const result = await get<{ order: OrderApiResource }>(`/api/v1/orders/${orderId}`);
        if (result?.order) {
            currentOrder.value = result.order;
            return result.order;
        }
        return null;
    }

    function formatPrice(cents: number): string {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(cents / 100);
    }

    function formatDate(dateString: string): string {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }

    function formatDateTime(dateString: string): string {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    return {
        orders,
        currentOrder,
        loading,
        error,
        fetchOrders,
        fetchOrder,
        clearError,
        formatPrice,
        formatDate,
        formatDateTime,
    };
}
