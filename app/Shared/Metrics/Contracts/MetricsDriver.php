<?php

declare(strict_types=1);

namespace App\Shared\Metrics\Contracts;

use DateTimeInterface;

interface MetricsDriver
{
    /**
     * Increment a counter metric.
     *
     * @param  array<string, string>  $labels
     */
    public function increment(string $name, array $labels = [], float $value = 1.0): void;

    /**
     * Set a gauge metric.
     *
     * @param  array<string, string>  $labels
     */
    public function gauge(string $name, float $value, array $labels = []): void;

    /**
     * Record a histogram observation (e.g., latency).
     *
     * @param  array<string, string>  $labels
     */
    public function histogram(string $name, float $value, array $labels = []): void;

    /**
     * Get metrics for a given name and time window.
     *
     * @param  array<string, string>  $labels
     * @return array<int, array{value: float, labels: array, recorded_at: string}>
     */
    public function query(
        string $name,
        ?DateTimeInterface $since = null,
        ?DateTimeInterface $until = null,
        array $labels = []
    ): array;

    /**
     * Get aggregated count for a metric within a time window.
     *
     * @param  array<string, string>  $labels
     */
    public function count(
        string $name,
        ?DateTimeInterface $since = null,
        ?DateTimeInterface $until = null,
        array $labels = []
    ): int;

    /**
     * Get aggregated sum for a metric within a time window.
     *
     * @param  array<string, string>  $labels
     */
    public function sum(
        string $name,
        ?DateTimeInterface $since = null,
        ?DateTimeInterface $until = null,
        array $labels = []
    ): float;

    /**
     * Get the average value for a metric within a time window.
     *
     * @param  array<string, string>  $labels
     */
    public function average(
        string $name,
        ?DateTimeInterface $since = null,
        ?DateTimeInterface $until = null,
        array $labels = []
    ): float;

    /**
     * Get percentile value for a metric within a time window (useful for histograms).
     *
     * @param  array<string, string>  $labels
     */
    public function percentile(
        string $name,
        float $percentile,
        ?DateTimeInterface $since = null,
        ?DateTimeInterface $until = null,
        array $labels = []
    ): float;
}
