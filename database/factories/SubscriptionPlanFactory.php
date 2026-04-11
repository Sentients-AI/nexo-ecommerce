<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Subscription\Enums\BillingInterval;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<SubscriptionPlan>
 */
final class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'name' => fake()->words(3, true).' Plan',
            'description' => fake()->sentence(),
            'billing_interval' => fake()->randomElement(BillingInterval::cases())->value,
            'price_cents' => fake()->randomElement([999, 1999, 4999, 9999]),
            'stripe_price_id' => 'price_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'features' => ['Feature A', 'Feature B', 'Feature C'],
            'is_active' => true,
        ];
    }
}
