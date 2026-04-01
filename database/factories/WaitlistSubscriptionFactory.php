<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Models\WaitlistSubscription;
use App\Domain\Product\Models\Product;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<WaitlistSubscription>
 */
final class WaitlistSubscriptionFactory extends Factory
{
    protected $model = WaitlistSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'product_id' => Product::factory(),
            'email' => fake()->safeEmail(),
            'notified_at' => null,
        ];
    }

    public function notified(): static
    {
        return $this->state(['notified_at' => now()]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
