<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;

final readonly class InvariantViolationAttempted
{
    use Dispatchable;

    public function __construct(
        public string $guardName,
        public string $violationMessage,
        public string $entityType,
        public int $entityId,
        public array $context = [],
    ) {
        Log::channel('security')->critical('Invariant violation attempted', [
            'guard' => $this->guardName,
            'message' => $this->violationMessage,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'context' => $this->context,
        ]);
    }
}
