<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Shared\Alerting\AlertDefinition;
use Illuminate\Database\Seeder;

final class AlertDefinitionsSeeder extends Seeder
{
    public function run(): void
    {
        $alerts = [
            [
                'name' => 'high_payment_failure_rate',
                'description' => 'Payment failure rate exceeds 2% over 5 minutes',
                'metric_name' => 'payments_failed_total',
                'condition' => 'rate_gt',
                'threshold' => 2.0,
                'window_minutes' => 5,
                'severity' => 'critical',
            ],
            [
                'name' => 'stuck_approved_refund',
                'description' => 'Refund stuck in APPROVED state for over 10 minutes',
                'metric_name' => 'refunds_approved_total',
                'condition' => 'gt',
                'threshold' => 0,
                'window_minutes' => 10,
                'severity' => 'warning',
            ],
            [
                'name' => 'negative_inventory',
                'description' => 'Inventory available went below zero',
                'metric_name' => 'inventory_underflow_attempts_total',
                'condition' => 'gt',
                'threshold' => 0,
                'window_minutes' => 5,
                'severity' => 'critical',
            ],
            [
                'name' => 'idempotency_conflict_spike',
                'description' => 'High number of idempotency conflicts detected',
                'metric_name' => 'idempotency_conflicts_total',
                'condition' => 'gt',
                'threshold' => 10,
                'window_minutes' => 10,
                'severity' => 'warning',
            ],
            [
                'name' => 'checkout_failure_spike',
                'description' => 'High number of checkout failures',
                'metric_name' => 'orders_checkout_failed_total',
                'condition' => 'gt',
                'threshold' => 5,
                'window_minutes' => 5,
                'severity' => 'warning',
            ],
        ];

        foreach ($alerts as $alert) {
            AlertDefinition::query()->updateOrCreate(
                ['name' => $alert['name']],
                $alert
            );
        }
    }
}
