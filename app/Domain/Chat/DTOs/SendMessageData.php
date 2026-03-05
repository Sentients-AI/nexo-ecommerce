<?php

declare(strict_types=1);

namespace App\Domain\Chat\DTOs;

use App\Shared\DTOs\BaseData;

final class SendMessageData extends BaseData
{
    public function __construct(
        public int $conversationId,
        public int $senderId,
        public string $body,
    ) {}
}
