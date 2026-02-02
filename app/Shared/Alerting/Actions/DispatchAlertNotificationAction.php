<?php

declare(strict_types=1);

namespace App\Shared\Alerting\Actions;

use App\Shared\Alerting\AlertTrigger;
use Illuminate\Support\Facades\Log;

final class DispatchAlertNotificationAction
{
    public function execute(AlertTrigger $trigger): void
    {
        if ($trigger->notified_at !== null) {
            return;
        }

        $definition = $trigger->definition;

        Log::channel('daily')->warning('Alert triggered', [
            'alert_name' => $definition->name,
            'severity' => $definition->severity->value,
            'metric_name' => $definition->metric_name,
            'actual_value' => $trigger->actual_value,
            'threshold_value' => $trigger->threshold_value,
            'triggered_at' => $trigger->triggered_at->toISOString(),
        ]);

        $trigger->update([
            'notified_at' => now(),
        ]);
    }
}
