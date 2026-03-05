<?php

declare(strict_types=1);

namespace App\Domain\Chat\Enums;

enum ConversationType: string
{
    case Store = 'store';
    case Support = 'support';
}
