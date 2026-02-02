<?php

declare(strict_types=1);

namespace App\Domain\Shared\Guards;

use App\Domain\Shared\Events\InvariantViolationAttempted;
use DomainException;

abstract class AbstractGuard implements Guard
{
    protected string $violationMessage = 'Invariant violation';

    abstract public function check(): bool;

    abstract protected function getEntityType(): string;

    abstract protected function getEntityId(): int;

    final public function getViolationMessage(): string
    {
        return $this->violationMessage;
    }

    final public function getGuardName(): string
    {
        return class_basename(static::class);
    }

    final public function enforce(): void
    {
        if (! $this->check()) {
            InvariantViolationAttempted::dispatch(
                $this->getGuardName(),
                $this->getViolationMessage(),
                $this->getEntityType(),
                $this->getEntityId(),
                $this->getContext(),
            );

            throw new DomainException($this->getViolationMessage());
        }
    }

    protected function getContext(): array
    {
        return [];
    }
}
