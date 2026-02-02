<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Shared\Alerting\Actions\DispatchAlertNotificationAction;
use App\Shared\Alerting\Actions\EvaluateAlertsAction;
use Illuminate\Console\Command;

final class EvaluateAlertsCommand extends Command
{
    protected $signature = 'alerts:evaluate';

    protected $description = 'Evaluate alert definitions against current metrics and trigger notifications';

    public function handle(
        EvaluateAlertsAction $evaluateAlerts,
        DispatchAlertNotificationAction $dispatchNotification
    ): int {
        $this->info('Evaluating alerts...');

        $triggers = $evaluateAlerts->execute();

        if (count($triggers) === 0) {
            $this->info('No alerts triggered.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d alert(s) triggered:', count($triggers)));

        foreach ($triggers as $trigger) {
            $definition = $trigger->definition;

            $this->line(sprintf(
                '  - [%s] %s: %s (actual: %.2f, threshold: %.2f)',
                mb_strtoupper((string) $definition->severity->value),
                $definition->name,
                $definition->description,
                $trigger->actual_value,
                $trigger->threshold_value
            ));

            $dispatchNotification->execute($trigger);
        }

        return self::SUCCESS;
    }
}
