<?php

declare(strict_types=1);

namespace App\Shared\Alerting\Enums;

enum AlertCondition: string
{
    case GreaterThan = 'gt';
    case LessThan = 'lt';
    case GreaterThanOrEqual = 'gte';
    case LessThanOrEqual = 'lte';
    case Equal = 'eq';
    case RateGreaterThan = 'rate_gt';

    public function evaluate(float $actual, float $threshold): bool
    {
        return match ($this) {
            self::GreaterThan => $actual > $threshold,
            self::LessThan => $actual < $threshold,
            self::GreaterThanOrEqual => $actual >= $threshold,
            self::LessThanOrEqual => $actual <= $threshold,
            self::Equal => abs($actual - $threshold) < 0.0001,
            self::RateGreaterThan => $actual > $threshold,
        };
    }
}
