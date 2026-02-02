<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use App\Shared\Metrics\Contracts\MetricsDriver;

final class MetricsRecorder
{
    /**
     * Increment a counter metric.
     *
     * @param  array<string, string>  $labels
     */
    public static function increment(string $name, array $labels = [], float $value = 1.0): void
    {
        self::driver()->increment($name, $labels, $value);
    }

    /**
     * Set a gauge metric value.
     *
     * @param  array<string, string>  $labels
     */
    public static function gauge(string $name, float $value, array $labels = []): void
    {
        self::driver()->gauge($name, $value, $labels);
    }

    /**
     * Record a histogram observation (e.g., latency in ms).
     *
     * @param  array<string, string>  $labels
     */
    public static function histogram(string $name, float $value, array $labels = []): void
    {
        self::driver()->histogram($name, $value, $labels);
    }

    /**
     * Measure execution time and record as histogram.
     *
     * @param  array<string, string>  $labels
     */
    public static function timing(string $name, callable $callback, array $labels = []): mixed
    {
        $start = microtime(true);

        try {
            return $callback();
        } finally {
            $durationMs = (microtime(true) - $start) * 1000;
            self::histogram($name, $durationMs, $labels);
        }
    }

    /**
     * Get the metrics driver instance.
     */
    public static function driver(): MetricsDriver
    {
        return app(MetricsDriver::class);
    }
}
