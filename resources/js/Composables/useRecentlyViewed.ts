import { ref, computed, watch } from 'vue';
import type { ProductApiResource } from '@/types/api';

const STORAGE_KEY = 'recently_viewed';
const MAX_ITEMS = 10;

interface RecentlyViewedItem {
    id: number;
    viewedAt: number;
}

// Global state - shared across all component instances
const recentlyViewedIds = ref<RecentlyViewedItem[]>(loadFromStorage());

function loadFromStorage(): RecentlyViewedItem[] {
    if (typeof window === 'undefined') return [];
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        return stored ? JSON.parse(stored) : [];
    } catch {
        return [];
    }
}

function saveToStorage(items: RecentlyViewedItem[]) {
    if (typeof window === 'undefined') return;
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
        // Storage might be full or unavailable
    }
}

// Watch for changes and persist
watch(recentlyViewedIds, (items) => {
    saveToStorage(items);
}, { deep: true });

export function useRecentlyViewed() {
    const productIds = computed(() =>
        recentlyViewedIds.value
            .sort((a, b) => b.viewedAt - a.viewedAt)
            .map(item => item.id)
    );

    const count = computed(() => recentlyViewedIds.value.length);

    function addToRecentlyViewed(productId: number) {
        // Remove if already exists (we'll re-add with updated timestamp)
        const filtered = recentlyViewedIds.value.filter(item => item.id !== productId);

        // Add to front with current timestamp
        const updated = [
            { id: productId, viewedAt: Date.now() },
            ...filtered,
        ].slice(0, MAX_ITEMS);

        recentlyViewedIds.value = updated;
    }

    function clearRecentlyViewed() {
        recentlyViewedIds.value = [];
    }

    function getRecentProductIds(limit: number = MAX_ITEMS): number[] {
        return productIds.value.slice(0, limit);
    }

    return {
        productIds,
        count,
        addToRecentlyViewed,
        clearRecentlyViewed,
        getRecentProductIds,
    };
}
