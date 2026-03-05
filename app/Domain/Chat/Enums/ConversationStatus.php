<?php

declare(strict_types=1);

namespace App\Domain\Chat\Enums;

enum ConversationStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
