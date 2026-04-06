<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Order\Enums\EarningStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\VendorEarning;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<VendorEarning>
 */
final class VendorEarningFactory extends Factory
{
    protected $model = VendorEarning::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gross = fake()->numberBetween(1000, 50000);
        $fee = (int) round($gross * 0.02);

        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'order_id' => Order::factory(),
            'gross_amount_cents' => $gross,
            'platform_fee_cents' => $fee,
            'net_amount_cents' => $gross - $fee,
            'refunded_amount_cents' => 0,
            'status' => EarningStatus::Pending,
            'available_at' => now()->addDays(7),
            'paid_out_at' => null,
        ];
    }

    public function available(): static
    {
        return $this->state([
            'status' => EarningStatus::Available,
            'available_at' => now()->subDay(),
        ]);
    }

    public function paidOut(): static
    {
        return $this->state([
            'status' => EarningStatus::PaidOut,
            'available_at' => now()->subDays(14),
            'paid_out_at' => now()->subDays(7),
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
