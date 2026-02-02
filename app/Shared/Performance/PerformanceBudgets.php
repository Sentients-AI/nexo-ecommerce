<?php

declare(strict_types=1);

namespace App\Shared\Performance;

final class PerformanceBudgets
{
    /**
     * Maximum allowed latency for checkout operations (ms).
     */
    public const CHECKOUT_MAX_MS = 3000;

    /**
     * Maximum allowed latency for payment confirmation (ms).
     */
    public const PAYMENT_CONFIRMATION_MAX_MS = 5000;

    /**
     * Maximum allowed latency for order list API (ms).
     */
    public const ORDER_LIST_MAX_MS = 500;

    /**
     * Maximum allowed latency for order detail API (ms).
     */
    public const ORDER_DETAIL_MAX_MS = 300;

    /**
     * Maximum allowed latency for refund request API (ms).
     */
    public const REFUND_REQUEST_MAX_MS = 1000;

    /**
     * Default maximum latency for unspecified endpoints (ms).
     */
    public const DEFAULT_MAX_MS = 1000;

    /**
     * Get the performance budget for a specific route.
     */
    public static function forRoute(string $routeName): int
    {
        return match ($routeName) {
            'api.v1.checkout' => self::CHECKOUT_MAX_MS,
            'api.v1.checkout.confirm-payment' => self::PAYMENT_CONFIRMATION_MAX_MS,
            'api.v1.orders.index' => self::ORDER_LIST_MAX_MS,
            'api.v1.orders.show' => self::ORDER_DETAIL_MAX_MS,
            'api.v1.orders.refunds.store' => self::REFUND_REQUEST_MAX_MS,
            default => self::DEFAULT_MAX_MS,
        };
    }

    /**
     * Check if a latency exceeds the budget for a route.
     */
    public static function exceedsBudget(string $routeName, float $latencyMs): bool
    {
        return $latencyMs > self::forRoute($routeName);
    }
}
