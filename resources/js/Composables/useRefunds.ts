import { ref } from 'vue';
import { useApi } from './useApi';
import type { RefundApiResource } from '@/types/api';

export function useRefunds() {
    const { loading, error, post, get, clearError } = useApi();

    const currentRefund = ref<RefundApiResource | null>(null);

    async function requestRefund(
        orderId: number,
        reason: string,
        amountCents?: number
    ): Promise<RefundApiResource | null> {
        const data: Record<string, unknown> = {
            order_id: orderId,
            reason,
        };

        if (amountCents !== undefined) {
            data.amount_cents = amountCents;
        }

        const result = await post<{ refund: RefundApiResource }>(
            `/api/v1/orders/${orderId}/refunds`,
            data
        );

        if (result?.refund) {
            currentRefund.value = result.refund;
            return result.refund;
        }

        return null;
    }

    async function fetchRefund(refundId: number): Promise<RefundApiResource | null> {
        const result = await get<{ refund: RefundApiResource }>(`/api/v1/refunds/${refundId}`);

        if (result?.refund) {
            currentRefund.value = result.refund;
            return result.refund;
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

    return {
        currentRefund,
        loading,
        error,
        requestRefund,
        fetchRefund,
        clearError,
        formatPrice,
        formatDate,
    };
}
