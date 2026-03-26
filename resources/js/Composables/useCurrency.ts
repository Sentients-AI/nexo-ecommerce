import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useCurrency() {
    const page = usePage();
    const currency = computed(() => (page.props as Record<string, unknown>).currency as string || 'MYR');

    function formatPrice(cents: number, overrideCurrency?: string): string {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: overrideCurrency ?? currency.value,
        }).format(cents / 100);
    }

    return { currency, formatPrice };
}
