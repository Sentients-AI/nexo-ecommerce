import { ref, onMounted, onUnmounted, type Ref } from 'vue';
import { loadStripe, type Stripe, type StripeElements, type PaymentIntent } from '@stripe/stripe-js';

let stripePromise: Promise<Stripe | null> | null = null;

export function usePayments(stripePublicKey: string) {
    const stripe = ref<Stripe | null>(null);
    const elements = ref<StripeElements | null>(null);
    const loading = ref(true);
    const error = ref<string | null>(null);
    const paymentProcessing = ref(false);

    /**
     * Initialize Stripe
     */
    async function initStripe(): Promise<void> {
        loading.value = true;
        error.value = null;

        try {
            if (!stripePromise) {
                stripePromise = loadStripe(stripePublicKey);
            }

            const stripeInstance = await stripePromise;

            if (!stripeInstance) {
                error.value = 'Failed to load payment provider';
                return;
            }

            stripe.value = stripeInstance;
        } catch (e) {
            error.value = 'Failed to initialize payment provider';
            console.error('Stripe initialization error:', e);
        } finally {
            loading.value = false;
        }
    }

    /**
     * Create Elements instance with a client secret
     */
    function createElements(clientSecret: string): StripeElements | null {
        if (!stripe.value) {
            error.value = 'Payment provider not initialized';
            return null;
        }

        const newElements = stripe.value.elements({
            clientSecret,
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary: '#4F46E5',
                    colorBackground: '#ffffff',
                    colorText: '#1f2937',
                    colorDanger: '#dc2626',
                    fontFamily: 'Inter, system-ui, sans-serif',
                    spacingUnit: '4px',
                    borderRadius: '6px',
                },
            },
        });

        elements.value = newElements;
        return newElements;
    }

    /**
     * Confirm the payment
     */
    async function confirmPayment(
        returnUrl: string
    ): Promise<{ success: boolean; error?: string; paymentIntent?: PaymentIntent }> {
        if (!stripe.value || !elements.value) {
            return { success: false, error: 'Payment provider not initialized' };
        }

        paymentProcessing.value = true;
        error.value = null;

        try {
            const { error: submitError } = await elements.value.submit();

            if (submitError) {
                error.value = submitError.message ?? 'Payment submission failed';
                return { success: false, error: error.value };
            }

            const { error: confirmError, paymentIntent } = await stripe.value.confirmPayment({
                elements: elements.value,
                confirmParams: {
                    return_url: returnUrl,
                },
                redirect: 'if_required',
            });

            if (confirmError) {
                error.value = confirmError.message ?? 'Payment confirmation failed';
                return { success: false, error: error.value };
            }

            if (paymentIntent?.status === 'succeeded') {
                return { success: true, paymentIntent };
            }

            if (paymentIntent?.status === 'requires_action') {
                // 3D Secure or similar - Stripe handles the redirect
                return { success: true, paymentIntent };
            }

            return { success: false, error: 'Payment failed' };
        } catch (e) {
            error.value = 'An unexpected error occurred';
            console.error('Payment error:', e);
            return { success: false, error: error.value };
        } finally {
            paymentProcessing.value = false;
        }
    }

    /**
     * Retrieve payment intent status (for polling)
     */
    async function retrievePaymentIntent(clientSecret: string): Promise<PaymentIntent | null> {
        if (!stripe.value) {
            return null;
        }

        try {
            const { paymentIntent } = await stripe.value.retrievePaymentIntent(clientSecret);
            return paymentIntent ?? null;
        } catch (e) {
            console.error('Failed to retrieve payment intent:', e);
            return null;
        }
    }

    onMounted(() => {
        initStripe();
    });

    return {
        stripe,
        elements,
        loading,
        error,
        paymentProcessing,
        initStripe,
        createElements,
        confirmPayment,
        retrievePaymentIntent,
    };
}
