<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Order\Models\Order;
use App\Domain\Refund\Enums\ReturnStatus;
use App\Domain\Refund\Models\ReturnRequest;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<ReturnRequest>
 */
final class ReturnRequestFactory extends Factory
{
    protected $model = ReturnRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'status' => ReturnStatus::Pending,
            'notes' => fake()->optional()->sentence(),
            'admin_notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'refund_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'status' => ReturnStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => ReturnStatus::Rejected,
            'admin_notes' => fake()->sentence(),
            'reviewed_at' => now(),
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
