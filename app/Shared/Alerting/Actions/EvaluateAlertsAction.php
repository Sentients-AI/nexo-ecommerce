<?php

declare(strict_types=1);

namespace App\Shared\Alerting\Actions;

use App\Shared\Alerting\AlertDefinition;
use App\Shared\Alerting\AlertTrigger;
use App\Shared\Alerting\Enums\AlertCondition;
use App\Shared\Alerting\Enums\AlertTriggerStatus;
use App\Shared\Metrics\MetricsRecorder;
use DateTimeInterface;

final class EvaluateAlertsAction
{
    /**
     * @return array<int, AlertTrigger>
     */
    public function execute(): array
    {
        $newTriggers = [];

        $definitions = AlertDefinition::query()
            ->where('is_active', true)
            ->get();

        foreach ($definitions as $definition) {
            $trigger = $this->evaluateDefinition($definition);

            if ($trigger instanceof AlertTrigger) {
                $newTriggers[] = $trigger;
            }
        }

        return $newTriggers;
    }

    private function evaluateDefinition(AlertDefinition $definition): ?AlertTrigger
    {
        $windowStart = now()->subMinutes($definition->window_minutes);
        $labels = $definition->labels ?? [];

        $actualValue = $this->calculateMetricValue($definition, $windowStart, $labels);

        $conditionMet = $definition->condition->evaluate(
            $actualValue,
            (float) $definition->threshold
        );

        if ($conditionMet) {
            return $this->createOrUpdateTrigger($definition, $actualValue);
        }

        $this->resolveExistingTriggers($definition);

        return null;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function calculateMetricValue(
        AlertDefinition $definition,
        DateTimeInterface $since,
        array $labels
    ): float {
        $driver = MetricsRecorder::driver();

        if ($definition->condition === AlertCondition::RateGreaterThan) {
            $total = $driver->count($definition->metric_name, $since, labels: $labels);
            $failed = $driver->count($definition->metric_name.'_failed', $since, labels: $labels);

            if ($total === 0) {
                return 0.0;
            }

            return ($failed / $total) * 100;
        }

        return $driver->sum($definition->metric_name, $since, labels: $labels);
    }

    private function createOrUpdateTrigger(AlertDefinition $definition, float $actualValue): AlertTrigger
    {
        $existingTrigger = AlertTrigger::query()
            ->where('alert_definition_id', $definition->id)
            ->where('status', AlertTriggerStatus::Active)
            ->first();

        if ($existingTrigger !== null) {
            $existingTrigger->update([
                'actual_value' => $actualValue,
            ]);

            return $existingTrigger;
        }

        return AlertTrigger::query()->create([
            'alert_definition_id' => $definition->id,
            'actual_value' => $actualValue,
            'threshold_value' => $definition->threshold,
            'status' => AlertTriggerStatus::Active,
            'triggered_at' => now(),
            'context' => [
                'metric_name' => $definition->metric_name,
                'window_minutes' => $definition->window_minutes,
            ],
        ]);
    }

    private function resolveExistingTriggers(AlertDefinition $definition): void
    {
        AlertTrigger::query()
            ->where('alert_definition_id', $definition->id)
            ->where('status', AlertTriggerStatus::Active)
            ->update([
                'status' => AlertTriggerStatus::Resolved,
                'resolved_at' => now(),
            ]);
    }
}
