import { ref, computed, watch } from 'vue';
import type { ProductApiResource } from '@/types/api';

const STORAGE_KEY = 'wishlist';

// Global state - shared across all component instances
const wishlistIds = ref<number[]>(loadFromStorage());

function loadFromStorage(): number[] {
    if (typeof window === 'undefined') return [];
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        return stored ? JSON.parse(stored) : [];
    } catch {
        return [];
    }
}

function saveToStorage(ids: number[]) {
    if (typeof window === 'undefined') return;
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    } catch {
        // Storage might be full or unavailable
    }
}

// Watch for changes and persist
watch(wishlistIds, (ids) => {
    saveToStorage(ids);
}, { deep: true });

export function useWishlist() {
    const count = computed(() => wishlistIds.value.length);

    function isInWishlist(productId: number): boolean {
        return wishlistIds.value.includes(productId);
    }

    function addToWishlist(productId: number) {
        if (!isInWishlist(productId)) {
            wishlistIds.value = [...wishlistIds.value, productId];
        }
    }

    function removeFromWishlist(productId: number) {
        wishlistIds.value = wishlistIds.value.filter(id => id !== productId);
    }

    function toggleWishlist(productId: number) {
        if (isInWishlist(productId)) {
            removeFromWishlist(productId);
        } else {
            addToWishlist(productId);
        }
    }

    function clearWishlist() {
        wishlistIds.value = [];
    }

    return {
        wishlistIds: computed(() => wishlistIds.value),
        count,
        isInWishlist,
        addToWishlist,
        removeFromWishlist,
        toggleWishlist,
        clearWishlist,
    };
}
