<?php

declare(strict_types=1);

namespace App\Shared\Alerting;

use App\Shared\Alerting\Enums\AlertTriggerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AlertTrigger extends Model
{
    protected $table = 'alert_triggers';

    protected $fillable = [
        'alert_definition_id',
        'actual_value',
        'threshold_value',
        'status',
        'triggered_at',
        'resolved_at',
        'notified_at',
        'context',
    ];

    /**
     * @return BelongsTo<AlertDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(AlertDefinition::class, 'alert_definition_id');
    }

    public function isActive(): bool
    {
        return $this->status === AlertTriggerStatus::Active;
    }

    public function resolve(): void
    {
        $this->update([
            'status' => AlertTriggerStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    protected function casts(): array
    {
        return [
            'actual_value' => 'decimal:4',
            'threshold_value' => 'decimal:4',
            'status' => AlertTriggerStatus::class,
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
            'notified_at' => 'datetime',
            'context' => 'array',
        ];
    }
}
