<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Order\Models\Order;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotalCents = $this->faker->numberBetween(10000, 100000);
        $taxCents = (int) ($subtotalCents * 0.1);
        $shippingCents = $this->faker->numberBetween(500, 2000);
        $totalCents = $subtotalCents + $taxCents + $shippingCents;

        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-'.$this->faker->unique()->bothify('########'),
            'status' => $this->faker->randomElement(['pending', 'paid', 'cancelled', 'shipped', 'delivered']),
            'subtotal_cents' => $subtotalCents,
            'tax_cents' => $taxCents,
            'shipping_cost_cents' => $shippingCents,
            'total_cents' => $totalCents,
            'currency' => 'USD',
            'refunded_amount_cents' => 0,
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
        ];
    }

    /**
     * Associate the order with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'pending',
        ]);
    }

    public function paid(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'paid',
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'fulfilled',
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'cancelled',
        ]);
    }

    public function shipped(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'shipped',
        ]);
    }
}
