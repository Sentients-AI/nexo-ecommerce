<?php

declare(strict_types=1);

namespace App\Domain\Chat\DTOs;

use App\Shared\DTOs\BaseData;

final class CreateConversationData extends BaseData
{
    public function __construct(
        public int $userId,
        public string $type,
        public ?string $subject,
        public string $initialMessage,
        public ?int $tenantId,
    ) {}
}
