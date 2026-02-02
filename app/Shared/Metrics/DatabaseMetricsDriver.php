<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use App\Shared\Metrics\Contracts\MetricsDriver;
use App\Shared\Metrics\Enums\MetricType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

final class DatabaseMetricsDriver implements MetricsDriver
{
    public function increment(string $name, array $labels = [], float $value = 1.0): void
    {
        MetricRecord::query()->create([
            'name' => $name,
            'type' => MetricType::Counter,
            'value' => $value,
            'labels' => $labels ?: null,
            'recorded_at' => now(),
        ]);
    }

    public function gauge(string $name, float $value, array $labels = []): void
    {
        MetricRecord::query()->create([
            'name' => $name,
            'type' => MetricType::Gauge,
            'value' => $value,
            'labels' => $labels ?: null,
            'recorded_at' => now(),
        ]);
    }

    public function histogram(string $name, float $value, array $labels = []): void
    {
        MetricRecord::query()->create([
            'name' => $name,
            'type' => MetricType::Histogram,
            'value' => $value,
            'labels' => $labels ?: null,
            'recorded_at' => now(),
        ]);
    }

    public function query(
        string $name,
        ?DateTimeInterface $since = null,
        ?DateTimeInterface $until = null,
        array $labels = []
    ): array {
        return $this->buildQuery($name, $since, $until, $labels)
            ->orderBy('recorded_at', 'desc')
            ->get()
            ->map(fn (MetricRecord $record): array => [
                'value' => (float) $record->value,
                'labels' => $record->labels ?? [],
                'recorded_at' => $record->recorded_at->toISOString(),
            ])
            ->all();
    }

    public function count(
        string $name,
        ?DateTimeInterface $since = null,
        ?DateTimeInterface $until = null,
        array $labels = []
    ): int {
        return $this->buildQuery($name, $since, $until, $labels)->count();
    }

    public function sum(
        string $name,
        ?DateTimeInterface $since = null,
        ?DateTimeInterface $until = null,
        array $labels = []
    ): float {
        return (float) $this->buildQuery($name, $since, $until, $labels)->sum('value');
    }

    public function average(
        string $name,
        ?DateTimeInterface $since = null,
        ?DateTimeInterface $until = null,
        array $labels = []
    ): float {
        return (float) $this->buildQuery($name, $since, $until, $labels)->avg('value');
    }

    public function percentile(
        string $name,
        float $percentile,
        ?DateTimeInterface $since = null,
        ?DateTimeInterface $until = null,
        array $labels = []
    ): float {
        $values = $this->buildQuery($name, $since, $until, $labels)
            ->orderBy('value')
            ->pluck('value')
            ->map(fn ($v): float => (float) $v)
            ->all();

        if (empty($values)) {
            return 0.0;
        }

        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return $values[$index];
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function buildQuery(
        string $name,
        ?DateTimeInterface $since,
        ?DateTimeInterface $until,
        array $labels
    ): Builder {
        $query = MetricRecord::query()->where('name', $name);

        if ($since instanceof DateTimeInterface) {
            $query->where('recorded_at', '>=', $since);
        }

        if ($until instanceof DateTimeInterface) {
            $query->where('recorded_at', '<=', $until);
        }

        foreach ($labels as $key => $value) {
            $query->whereJsonContains("labels->{$key}", $value);
        }

        return $query;
    }
}
