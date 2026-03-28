<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<PaymentIntent>
 */
final class PaymentIntentFactory extends Factory
{
    protected $model = PaymentIntent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->create(),
            'provider' => fake()->randomElement(['stripe', 'paypal', 'square']),
            'provider_reference' => fake()->unique()->bothify('pi_??????????'),
            'amount' => fake()->numberBetween(1000, 100000),
            'currency' => 'USD',
            'status' => fake()->randomElement(['requires_payment', 'processing', 'succeeded', 'failed']),
            'idempotency_key' => fake()->unique()->uuid(),
            'attempts' => fake()->numberBetween(0, 3),
            'expires_at' => fake()->optional()->dateTimeBetween('now', '+7 days'),
            'metadata' => [
                'client_ip' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
            ],
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
        ];
    }

    /**
     * Associate the payment intent with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
