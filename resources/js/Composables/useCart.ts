import { ref, computed } from 'vue';
import { useApi } from './useApi';
import type { CartApiResource } from '@/types/api';

const cart = ref<CartApiResource | null>(null);
const cartLoading = ref(false);

export function useCart() {
    const { loading, error, get, post, put, destroy, clearError } = useApi();

    const totalItems = computed(() => cart.value?.total_items ?? 0);
    const subtotal = computed(() => cart.value?.subtotal ?? 0);
    const items = computed(() => cart.value?.items ?? []);

    async function fetchCart(): Promise<void> {
        cartLoading.value = true;
        const result = await get<{ cart: CartApiResource }>('/api/v1/cart');
        if (result?.cart) {
            cart.value = result.cart;
        }
        cartLoading.value = false;
    }

    async function addToCart(productId: number, quantity: number = 1, variantId?: number | null): Promise<boolean> {
        const result = await post<{ cart: CartApiResource }>('/api/v1/cart/items', {
            product_id: productId,
            quantity,
            ...(variantId ? { variant_id: variantId } : {}),
        });

        if (result?.cart) {
            cart.value = result.cart;
            return true;
        }

        return false;
    }

    async function updateItem(itemId: number, quantity: number): Promise<boolean> {
        if (quantity <= 0) {
            return removeItem(itemId);
        }

        const result = await put<{ cart: CartApiResource }>(`/api/v1/cart/items/${itemId}`, {
            quantity,
        });

        if (result?.cart) {
            cart.value = result.cart;
            return true;
        }

        return false;
    }

    async function removeItem(itemId: number): Promise<boolean> {
        const result = await destroy<{ cart: CartApiResource }>(`/api/v1/cart/items/${itemId}`);

        if (result?.cart) {
            cart.value = result.cart;
            return true;
        }

        return false;
    }

    async function clearCart(): Promise<boolean> {
        const result = await destroy<{ cart: CartApiResource }>('/api/v1/cart');

        if (result?.cart) {
            cart.value = result.cart;
            return true;
        }

        return false;
    }

    function formatPrice(cents: number): string {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(cents / 100);
    }

    return {
        cart,
        items,
        totalItems,
        subtotal,
        loading: computed(() => loading.value || cartLoading.value),
        error,
        fetchCart,
        addToCart,
        updateItem,
        removeItem,
        clearCart,
        clearError,
        formatPrice,
    };
}
