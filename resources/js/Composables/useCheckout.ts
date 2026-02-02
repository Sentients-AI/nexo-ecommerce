import { ref, computed } from 'vue';
import { useApi, getIdempotencyKey, clearIdempotencyKey } from './useApi';
import type { CheckoutSuccessData, ConfirmPaymentSuccessData, OrderApiResource } from '@/types/api';

export function useCheckout() {
    const { loading, error, post, clearError } = useApi();

    const order = ref<OrderApiResource | null>(null);
    const clientSecret = ref<string | null>(null);
    const checkoutStatus = ref<'idle' | 'processing' | 'pending_payment' | 'completed' | 'failed'>('idle');

    /**
     * Initiates the checkout process
     */
    async function initiateCheckout(cartId: number, currency: string = 'USD'): Promise<boolean> {
        checkoutStatus.value = 'processing';

        const idempotencyKey = getIdempotencyKey('checkout');

        const result = await post<CheckoutSuccessData>('/api/v1/checkout', {
            cart_id: cartId,
            currency,
        }, {
            idempotencyKey,
        });

        if (result) {
            order.value = result.order;
            clientSecret.value = result.client_secret;
            checkoutStatus.value = 'pending_payment';
            return true;
        }

        checkoutStatus.value = 'failed';
        return false;
    }

    /**
     * Confirms the payment after Stripe Elements completes
     */
    async function confirmPayment(orderId: number, paymentIntentId: string): Promise<boolean> {
        checkoutStatus.value = 'processing';

        const result = await post<ConfirmPaymentSuccessData>('/api/v1/checkout/confirm-payment', {
            order_id: orderId,
            payment_intent_id: paymentIntentId,
        });

        if (result) {
            order.value = result.order;

            if (result.status === 'succeeded') {
                checkoutStatus.value = 'completed';
                clearIdempotencyKey('checkout');
                return true;
            }

            if (result.status === 'requires_action') {
                checkoutStatus.value = 'pending_payment';
                return true;
            }
        }

        checkoutStatus.value = 'failed';
        return false;
    }

    /**
     * Resets checkout state
     */
    function resetCheckout() {
        order.value = null;
        clientSecret.value = null;
        checkoutStatus.value = 'idle';
        clearError();
    }

    return {
        order,
        clientSecret,
        checkoutStatus,
        loading,
        error,
        initiateCheckout,
        confirmPayment,
        resetCheckout,
        clearError,
    };
}
