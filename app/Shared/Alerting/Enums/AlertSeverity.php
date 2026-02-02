<?php

declare(strict_types=1);

namespace App\Shared\Alerting\Enums;

enum AlertSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';
}
