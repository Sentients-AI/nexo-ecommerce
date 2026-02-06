<?php

declare(strict_types=1);

use App\Shared\Metrics\Enums\MetricType;
use App\Shared\Metrics\MetricRecord;
use App\Shared\Metrics\MetricsRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('MetricsRecorder', function () {
    it('increments a counter metric', function () {
        MetricsRecorder::increment('orders_created_total');

        $record = MetricRecord::query()->first();
        expect($record)->not->toBeNull()
            ->and($record->name)->toBe('orders_created_total')
            ->and($record->type)->toBe(MetricType::Counter)
            ->and((float) $record->value)->toBe(1.0);
    });

    it('increments a counter with custom value', function () {
        MetricsRecorder::increment('orders_created_total', [], 5.0);

        $record = MetricRecord::query()->first();
        expect((float) $record->value)->toBe(5.0);
    });

    it('records labels with counter', function () {
        MetricsRecorder::increment('orders_created_total', ['status' => 'pending', 'currency' => 'USD']);

        $record = MetricRecord::query()->first();
        expect($record->labels)->toBe(['status' => 'pending', 'currency' => 'USD']);
    });

    it('sets a gauge metric', function () {
        MetricsRecorder::gauge('inventory_available', 150.0, ['product_id' => '123']);

        $record = MetricRecord::query()->first();
        expect($record)->not->toBeNull()
            ->and($record->name)->toBe('inventory_available')
            ->and($record->type)->toBe(MetricType::Gauge)
            ->and((float) $record->value)->toBe(150.0)
            ->and($record->labels)->toBe(['product_id' => '123']);
    });

    it('records histogram metric', function () {
        MetricsRecorder::histogram('payment_latency_ms', 245.5);

        $record = MetricRecord::query()->first();
        expect($record)->not->toBeNull()
            ->and($record->name)->toBe('payment_latency_ms')
            ->and($record->type)->toBe(MetricType::Histogram)
            ->and((float) $record->value)->toBe(245.5);
    });

    it('measures timing with callback', function () {
        $result = MetricsRecorder::timing('operation_duration_ms', fn () => 'completed');

        expect($result)->toBe('completed');

        $record = MetricRecord::query()->first();
        expect($record)->not->toBeNull()
            ->and($record->name)->toBe('operation_duration_ms')
            ->and($record->type)->toBe(MetricType::Histogram)
            ->and((float) $record->value)->toBeGreaterThanOrEqual(0.0);
    });
});

describe('MetricsDriver Query', function () {
    beforeEach(function () {
        // Record some test metrics
        MetricsRecorder::increment('test_counter', ['status' => 'success']);
        MetricsRecorder::increment('test_counter', ['status' => 'success']);
        MetricsRecorder::increment('test_counter', ['status' => 'failure']);
        MetricsRecorder::histogram('test_latency', 100.0);
        MetricsRecorder::histogram('test_latency', 200.0);
        MetricsRecorder::histogram('test_latency', 300.0);
    });

    it('counts metrics', function () {
        $count = MetricsRecorder::driver()->count('test_counter');
        expect($count)->toBe(3);
    });

    it('sums metrics', function () {
        $sum = MetricsRecorder::driver()->sum('test_counter');
        expect($sum)->toBe(3.0);
    });

    it('averages metrics', function () {
        $avg = MetricsRecorder::driver()->average('test_latency');
        expect($avg)->toBe(200.0);
    });

    it('filters by labels', function () {
        $count = MetricsRecorder::driver()->count('test_counter', labels: ['status' => 'success']);
        expect($count)->toBe(2);
    });

    it('calculates percentile', function () {
        $p50 = MetricsRecorder::driver()->percentile('test_latency', 50);
        expect($p50)->toBe(200.0);

        $p99 = MetricsRecorder::driver()->percentile('test_latency', 99);
        expect($p99)->toBe(300.0);
    });

    it('queries with time window', function () {
        // Create a metric in the past
        MetricRecord::query()->create([
            'name' => 'old_metric',
            'type' => MetricType::Counter,
            'value' => 1,
            'recorded_at' => now()->subHours(2),
        ]);

        MetricsRecorder::increment('old_metric');

        $recentCount = MetricsRecorder::driver()->count('old_metric', since: now()->subHour());
        expect($recentCount)->toBe(1);

        $allCount = MetricsRecorder::driver()->count('old_metric');
        expect($allCount)->toBe(2);
    });
});
