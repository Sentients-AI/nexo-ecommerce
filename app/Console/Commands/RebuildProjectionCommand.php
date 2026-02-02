<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Projections\Actions\ReplayDomainEventsAction;
use Illuminate\Console\Command;

final class RebuildProjectionCommand extends Command
{
    protected $signature = 'projections:rebuild
        {--event-type= : Filter by event type}
        {--from= : Start from event ID}
        {--to= : End at event ID}';

    protected $description = 'Rebuild projections by replaying domain events';

    public function handle(ReplayDomainEventsAction $action): int
    {
        $eventType = $this->option('event-type');
        $fromId = $this->option('from') ? (int) $this->option('from') : null;
        $toId = $this->option('to') ? (int) $this->option('to') : null;

        $this->info('Starting projection rebuild...');

        if ($eventType) {
            $this->info("Filtering by event type: {$eventType}");
        }

        $replayed = $action->execute(
            $eventType,
            $fromId,
            $toId,
            function ($current, $total) {
                if ($current % 100 === 0 || $current === $total) {
                    $this->output->write("\rProcessed {$current}/{$total} events...");
                }
            }
        );

        $this->newLine();
        $this->info("Projection rebuild complete. Replayed {$replayed} events.");

        return self::SUCCESS;
    }
}
