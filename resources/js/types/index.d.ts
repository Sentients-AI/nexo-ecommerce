import type { PageProps as InertiaPageProps } from '@inertiajs/core';

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps {
        auth: {
            user: User | null;
        };
        flash: {
            success: string | null;
            error: string | null;
        };
        locale: string;
        supportedLocales: string[];
        isRtl: boolean;
        translations: Record<string, string>;
    }
}

declare global {
    interface Window {
        axios: import('axios').AxiosInstance;
        Echo: import('laravel-echo').default;
        Pusher: unknown;
    }
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface PaginatedResponse<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
}
