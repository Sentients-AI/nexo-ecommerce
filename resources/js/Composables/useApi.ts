import { ref } from 'vue';
import axios, { type AxiosError, type AxiosRequestConfig } from 'axios';
import { ErrorCode, isRetryable, messageForCode } from '@/types/errors';
import type { ApiResponse, ApiErrorResponse, ApiSuccessResponse } from '@/types/api';

interface UseApiOptions {
    /**
     * Idempotency key for POST/PUT requests
     */
    idempotencyKey?: string;
    /**
     * Timeout in milliseconds
     */
    timeout?: number;
}

interface ApiError {
    code: ErrorCode;
    message: string;
    retryable: boolean;
}

export function useApi() {
    const loading = ref(false);
    const error = ref<ApiError | null>(null);

    /**
     * Makes an API request with proper error handling
     */
    async function request<T>(
        method: 'get' | 'post' | 'put' | 'patch' | 'delete',
        url: string,
        data?: Record<string, unknown>,
        options?: UseApiOptions
    ): Promise<T | null> {
        loading.value = true;
        error.value = null;

        const config: AxiosRequestConfig = {
            method,
            url,
            withCredentials: true,
            timeout: options?.timeout ?? 30000,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        };

        // Add idempotency key for non-GET requests
        if (options?.idempotencyKey && method !== 'get') {
            config.headers = {
                ...config.headers,
                'Idempotency-Key': options.idempotencyKey,
            };
        }

        // Add data based on method
        if (data) {
            if (method === 'get') {
                config.params = data;
            } else {
                config.data = data;
            }
        }

        try {
            const response = await axios.request<T>(config);
            loading.value = false;
            return response.data;
        } catch (err) {
            loading.value = false;
            error.value = parseError(err);
            return null;
        }
    }

    /**
     * Parses an error into a standardized format
     */
    function parseError(err: unknown): ApiError {
        if (axios.isAxiosError(err)) {
            const axiosError = err as AxiosError<ApiErrorResponse>;

            // Check for our API error format
            if (axiosError.response?.data?.error) {
                const apiError = axiosError.response.data.error;
                return {
                    code: apiError.code as ErrorCode,
                    message: apiError.message || messageForCode(apiError.code as ErrorCode),
                    retryable: apiError.retryable ?? isRetryable(apiError.code as ErrorCode),
                };
            }

            // Handle HTTP status codes
            const status = axiosError.response?.status;

            if (status === 401) {
                return {
                    code: ErrorCode.Unauthorized,
                    message: messageForCode(ErrorCode.Unauthorized),
                    retryable: false,
                };
            }

            if (status === 403) {
                return {
                    code: ErrorCode.Forbidden,
                    message: messageForCode(ErrorCode.Forbidden),
                    retryable: false,
                };
            }

            if (status === 404) {
                return {
                    code: ErrorCode.ProductNotFound,
                    message: 'The requested resource was not found.',
                    retryable: false,
                };
            }

            if (status === 422) {
                // Laravel validation errors have message at root level, not in error object
                const validationData = axiosError.response?.data as { message?: string } | undefined;
                return {
                    code: ErrorCode.ValidationFailed,
                    message: validationData?.message || messageForCode(ErrorCode.ValidationFailed),
                    retryable: false,
                };
            }

            if (status === 429) {
                return {
                    code: ErrorCode.RateLimitExceeded,
                    message: messageForCode(ErrorCode.RateLimitExceeded),
                    retryable: true,
                };
            }

            if (status === 503) {
                return {
                    code: ErrorCode.ServiceUnavailable,
                    message: messageForCode(ErrorCode.ServiceUnavailable),
                    retryable: true,
                };
            }

            // Network error
            if (!axiosError.response) {
                return {
                    code: ErrorCode.ServiceUnavailable,
                    message: 'Unable to connect to the server. Please check your internet connection.',
                    retryable: true,
                };
            }
        }

        // Fallback to internal error
        return {
            code: ErrorCode.InternalError,
            message: messageForCode(ErrorCode.InternalError),
            retryable: true,
        };
    }

    /**
     * Convenience method for GET requests
     */
    async function get<T>(url: string, params?: Record<string, unknown>, options?: UseApiOptions): Promise<T | null> {
        return request<T>('get', url, params, options);
    }

    /**
     * Convenience method for POST requests
     */
    async function post<T>(url: string, data?: Record<string, unknown>, options?: UseApiOptions): Promise<T | null> {
        return request<T>('post', url, data, options);
    }

    /**
     * Convenience method for PUT requests
     */
    async function put<T>(url: string, data?: Record<string, unknown>, options?: UseApiOptions): Promise<T | null> {
        return request<T>('put', url, data, options);
    }

    /**
     * Convenience method for PATCH requests
     */
    async function patch<T>(url: string, data?: Record<string, unknown>, options?: UseApiOptions): Promise<T | null> {
        return request<T>('patch', url, data, options);
    }

    /**
     * Convenience method for DELETE requests
     */
    async function destroy<T>(url: string, options?: UseApiOptions): Promise<T | null> {
        return request<T>('delete', url, undefined, options);
    }

    /**
     * Clears the current error state
     */
    function clearError() {
        error.value = null;
    }

    return {
        loading,
        error,
        request,
        get,
        post,
        put,
        patch,
        destroy,
        clearError,
    };
}

/**
 * Generates a unique idempotency key for checkout operations.
 * Keys are stored in sessionStorage to survive page refreshes.
 */
export function getIdempotencyKey(operation: string): string {
    const storageKey = `idempotency_${operation}`;
    let key = sessionStorage.getItem(storageKey);

    if (!key) {
        key = `${operation}_${Date.now()}_${Math.random().toString(36).substring(2, 11)}`;
        sessionStorage.setItem(storageKey, key);
    }

    return key;
}

/**
 * Clears an idempotency key after successful operation
 */
export function clearIdempotencyKey(operation: string): void {
    sessionStorage.removeItem(`idempotency_${operation}`);
}
