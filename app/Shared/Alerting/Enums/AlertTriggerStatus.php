<?php

declare(strict_types=1);

namespace App\Shared\Alerting\Enums;

enum AlertTriggerStatus: string
{
    case Active = 'active';
    case Resolved = 'resolved';
    case Acknowledged = 'acknowledged';
}
