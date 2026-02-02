<?php

declare(strict_types=1);

namespace App\Domain\Shared\Guards;

interface Guard
{
    public function check(): bool;

    public function getViolationMessage(): string;

    public function getGuardName(): string;
}
