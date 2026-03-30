<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Shipping\Enums\ShippingType;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<ShippingMethod>
 */
final class ShippingMethodFactory extends Factory
{
    protected $model = ShippingMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'name' => fake()->randomElement(['Standard Shipping', 'Express Delivery', 'Free Shipping']),
            'description' => fake()->sentence(),
            'type' => ShippingType::FlatRate,
            'rate_cents' => fake()->randomElement([500, 1000, 1500, 2000]),
            'min_order_cents' => null,
            'estimated_days_min' => 3,
            'estimated_days_max' => 7,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state([
            'name' => 'Free Shipping',
            'type' => ShippingType::Free,
            'rate_cents' => 0,
        ]);
    }

    public function freeOverAmount(int $minOrderCents = 10000): static
    {
        return $this->state([
            'name' => 'Free Shipping over '.number_format($minOrderCents / 100, 2),
            'type' => ShippingType::FreeOverAmount,
            'rate_cents' => 1000,
            'min_order_cents' => $minOrderCents,
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
