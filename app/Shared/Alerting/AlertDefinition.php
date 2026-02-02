<?php

declare(strict_types=1);

namespace App\Shared\Alerting;

use App\Shared\Alerting\Enums\AlertCondition;
use App\Shared\Alerting\Enums\AlertSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AlertDefinition extends Model
{
    protected $table = 'alert_definitions';

    protected $fillable = [
        'name',
        'description',
        'metric_name',
        'condition',
        'threshold',
        'window_minutes',
        'severity',
        'is_active',
        'labels',
        'notification_channels',
    ];

    /**
     * @return HasMany<AlertTrigger, $this>
     */
    public function triggers(): HasMany
    {
        return $this->hasMany(AlertTrigger::class);
    }

    /**
     * @return HasMany<AlertTrigger, $this>
     */
    public function activeTriggers(): HasMany
    {
        return $this->triggers()->where('status', 'active');
    }

    protected function casts(): array
    {
        return [
            'condition' => AlertCondition::class,
            'threshold' => 'decimal:4',
            'window_minutes' => 'integer',
            'severity' => AlertSeverity::class,
            'is_active' => 'boolean',
            'labels' => 'array',
            'notification_channels' => 'array',
        ];
    }
}
