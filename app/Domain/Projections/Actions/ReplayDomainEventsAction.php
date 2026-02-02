<?php

declare(strict_types=1);

namespace App\Domain\Projections\Actions;

use App\Shared\Domain\DomainEventRecord;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class ReplayDomainEventsAction
{
    /**
     * Replay domain events for a specific aggregate or all events.
     *
     * @param  string|null  $eventType  Filter by event type (e.g., 'OrderCreated')
     * @param  int|null  $fromId  Start replaying from this event ID
     * @param  int|null  $toId  Stop replaying at this event ID
     * @param  callable|null  $onProgress  Callback for progress updates (current, total)
     */
    public function execute(
        ?string $eventType = null,
        ?int $fromId = null,
        ?int $toId = null,
        ?callable $onProgress = null,
    ): int {
        $query = DomainEventRecord::query()
            ->orderBy('id');

        if ($eventType !== null) {
            $query->where('event_type', 'like', "%{$eventType}%");
        }

        if ($fromId !== null) {
            $query->where('id', '>=', $fromId);
        }

        if ($toId !== null) {
            $query->where('id', '<=', $toId);
        }

        $total = $query->count();
        $replayed = 0;
        $errors = [];

        $query->chunk(100, function ($events) use (&$replayed, &$errors, $onProgress, $total) {
            foreach ($events as $event) {
                try {
                    $this->replayEvent($event);
                    $replayed++;

                    if ($onProgress !== null) {
                        $onProgress($replayed, $total);
                    }
                } catch (Throwable $e) {
                    $errors[] = [
                        'event_id' => $event->id,
                        'event_type' => $event->event_type,
                        'error' => $e->getMessage(),
                    ];

                    Log::warning('Failed to replay domain event', [
                        'event_id' => $event->id,
                        'event_type' => $event->event_type,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        if (! empty($errors)) {
            Log::warning('Domain event replay completed with errors', [
                'total' => $total,
                'replayed' => $replayed,
                'errors_count' => count($errors),
            ]);
        }

        return $replayed;
    }

    private function replayEvent(DomainEventRecord $record): void
    {
        $eventClass = $record->event_type;

        if (! class_exists($eventClass)) {
            throw new RuntimeException("Event class {$eventClass} does not exist");
        }

        $payload = $record->payload ?? [];

        // Reconstruct the event using its constructor
        $event = new $eventClass(...array_values($payload));

        // Dispatch the event
        Event::dispatch($event);
    }
}
