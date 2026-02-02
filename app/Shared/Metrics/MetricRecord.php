<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use App\Shared\Metrics\Enums\MetricType;
use Illuminate\Database\Eloquent\Model;

final class MetricRecord extends Model
{
    protected $table = 'metrics';

    protected $fillable = [
        'name',
        'type',
        'value',
        'labels',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MetricType::class,
            'value' => 'decimal:4',
            'labels' => 'array',
            'recorded_at' => 'datetime',
        ];
    }
}
