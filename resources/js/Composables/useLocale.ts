import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

export function useLocale() {
    const page = usePage();
    const { locale: i18nLocale, setLocaleMessage } = useI18n();

    const currentLocale = computed(() => (page.props as Record<string, unknown>).locale as string || 'en');
    const supportedLocales = computed(() => (page.props as Record<string, unknown>).supportedLocales as string[] || ['en', 'ar', 'ms']);
    const isRtl = computed(() => (page.props as Record<string, unknown>).isRtl as boolean || false);
    const translations = computed(() => (page.props as Record<string, unknown>).translations as Record<string, string> || {});

    /**
     * Sync translations from Inertia page props to vue-i18n.
     */
    function syncTranslations() {
        const locale = currentLocale.value;
        const trans = translations.value;

        if (trans && Object.keys(trans).length > 0) {
            setLocaleMessage(locale, trans);
            i18nLocale.value = locale;
        }
    }

    /**
     * Get a translation string by key.
     */
    function t(key: string, replacements?: Record<string, string | number>): string {
        let value = translations.value[key] || key;

        if (replacements) {
            Object.entries(replacements).forEach(([k, v]) => {
                value = value.replace(`:${k}`, String(v));
            });
        }

        return value;
    }

    /**
     * Switch to a different locale.
     */
    function switchLocale(locale: string) {
        const currentPath = window.location.pathname;
        const pathParts = currentPath.split('/').filter(Boolean);

        // Replace the first segment (locale) with the new locale
        if (pathParts.length > 0 && supportedLocales.value.includes(pathParts[0])) {
            pathParts[0] = locale;
        } else {
            pathParts.unshift(locale);
        }

        router.visit('/' + pathParts.join('/') + window.location.search, {
            preserveState: false,
        });
    }

    /**
     * Prefix a path with the current locale.
     */
    function localePath(path: string): string {
        const locale = currentLocale.value;
        const cleanPath = path.startsWith('/') ? path : `/${path}`;
        return `/${locale}${cleanPath}`;
    }

    return {
        currentLocale,
        supportedLocales,
        isRtl,
        translations,
        t,
        switchLocale,
        localePath,
        syncTranslations,
    };
}
