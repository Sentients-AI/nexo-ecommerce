<?php

declare(strict_types=1);

use App\Shared\Alerting\Actions\DispatchAlertNotificationAction;
use App\Shared\Alerting\Actions\EvaluateAlertsAction;
use App\Shared\Alerting\AlertDefinition;
use App\Shared\Alerting\AlertTrigger;
use App\Shared\Alerting\Enums\AlertCondition;
use App\Shared\Alerting\Enums\AlertSeverity;
use App\Shared\Alerting\Enums\AlertTriggerStatus;
use App\Shared\Metrics\MetricsRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('EvaluateAlertsAction', function () {
    it('triggers alert when threshold is exceeded', function () {
        AlertDefinition::query()->create([
            'name' => 'test_alert',
            'description' => 'Test alert',
            'metric_name' => 'test_metric',
            'condition' => AlertCondition::GreaterThan,
            'threshold' => 5.0,
            'window_minutes' => 5,
            'severity' => AlertSeverity::Warning,
            'is_active' => true,
        ]);

        MetricsRecorder::increment('test_metric', [], 10.0);

        $action = app(EvaluateAlertsAction::class);
        $triggers = $action->execute();

        expect($triggers)->toHaveCount(1)
            ->and($triggers[0]->actual_value)->toBe('10.0000')
            ->and($triggers[0]->status)->toBe(AlertTriggerStatus::Active);
    });

    it('does not trigger alert when below threshold', function () {
        AlertDefinition::query()->create([
            'name' => 'test_alert',
            'description' => 'Test alert',
            'metric_name' => 'test_metric',
            'condition' => AlertCondition::GreaterThan,
            'threshold' => 50.0,
            'window_minutes' => 5,
            'severity' => AlertSeverity::Warning,
            'is_active' => true,
        ]);

        MetricsRecorder::increment('test_metric', [], 10.0);

        $action = app(EvaluateAlertsAction::class);
        $triggers = $action->execute();

        expect($triggers)->toBeEmpty();
    });

    it('resolves alert when condition no longer met', function () {
        $definition = AlertDefinition::query()->create([
            'name' => 'test_alert',
            'description' => 'Test alert',
            'metric_name' => 'old_metric',
            'condition' => AlertCondition::GreaterThan,
            'threshold' => 5.0,
            'window_minutes' => 5,
            'severity' => AlertSeverity::Warning,
            'is_active' => true,
        ]);

        $trigger = AlertTrigger::query()->create([
            'alert_definition_id' => $definition->id,
            'actual_value' => 10.0,
            'threshold_value' => 5.0,
            'status' => AlertTriggerStatus::Active,
            'triggered_at' => now(),
        ]);

        $action = app(EvaluateAlertsAction::class);
        $action->execute();

        $trigger->refresh();
        expect($trigger->status)->toBe(AlertTriggerStatus::Resolved)
            ->and($trigger->resolved_at)->not->toBeNull();
    });

    it('skips inactive alert definitions', function () {
        AlertDefinition::query()->create([
            'name' => 'inactive_alert',
            'description' => 'Inactive alert',
            'metric_name' => 'test_metric',
            'condition' => AlertCondition::GreaterThan,
            'threshold' => 0.0,
            'window_minutes' => 5,
            'severity' => AlertSeverity::Warning,
            'is_active' => false,
        ]);

        MetricsRecorder::increment('test_metric', [], 100.0);

        $action = app(EvaluateAlertsAction::class);
        $triggers = $action->execute();

        expect($triggers)->toBeEmpty();
    });
});

describe('DispatchAlertNotificationAction', function () {
    it('logs alert and marks as notified', function () {
        Log::shouldReceive('channel')
            ->once()
            ->with('daily')
            ->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message, $context) => $message === 'Alert triggered' && $context['alert_name'] === 'test_alert');

        $definition = AlertDefinition::query()->create([
            'name' => 'test_alert',
            'description' => 'Test alert',
            'metric_name' => 'test_metric',
            'condition' => AlertCondition::GreaterThan,
            'threshold' => 5.0,
            'window_minutes' => 5,
            'severity' => AlertSeverity::Warning,
            'is_active' => true,
        ]);

        $trigger = AlertTrigger::query()->create([
            'alert_definition_id' => $definition->id,
            'actual_value' => 10.0,
            'threshold_value' => 5.0,
            'status' => AlertTriggerStatus::Active,
            'triggered_at' => now(),
        ]);

        $action = app(DispatchAlertNotificationAction::class);
        $action->execute($trigger);

        $trigger->refresh();
        expect($trigger->notified_at)->not->toBeNull();
    });

    it('does not notify twice', function () {
        Log::shouldReceive('channel')->never();

        $definition = AlertDefinition::query()->create([
            'name' => 'test_alert',
            'description' => 'Test alert',
            'metric_name' => 'test_metric',
            'condition' => AlertCondition::GreaterThan,
            'threshold' => 5.0,
            'window_minutes' => 5,
            'severity' => AlertSeverity::Warning,
            'is_active' => true,
        ]);

        $trigger = AlertTrigger::query()->create([
            'alert_definition_id' => $definition->id,
            'actual_value' => 10.0,
            'threshold_value' => 5.0,
            'status' => AlertTriggerStatus::Active,
            'triggered_at' => now(),
            'notified_at' => now(),
        ]);

        $action = app(DispatchAlertNotificationAction::class);
        $action->execute($trigger);
    });
});

describe('AlertCondition', function () {
    it('evaluates greater than correctly', function () {
        expect(AlertCondition::GreaterThan->evaluate(10.0, 5.0))->toBeTrue()
            ->and(AlertCondition::GreaterThan->evaluate(5.0, 10.0))->toBeFalse()
            ->and(AlertCondition::GreaterThan->evaluate(5.0, 5.0))->toBeFalse();
    });

    it('evaluates less than correctly', function () {
        expect(AlertCondition::LessThan->evaluate(5.0, 10.0))->toBeTrue()
            ->and(AlertCondition::LessThan->evaluate(10.0, 5.0))->toBeFalse();
    });

    it('evaluates greater than or equal correctly', function () {
        expect(AlertCondition::GreaterThanOrEqual->evaluate(10.0, 5.0))->toBeTrue()
            ->and(AlertCondition::GreaterThanOrEqual->evaluate(5.0, 5.0))->toBeTrue()
            ->and(AlertCondition::GreaterThanOrEqual->evaluate(4.0, 5.0))->toBeFalse();
    });
});
