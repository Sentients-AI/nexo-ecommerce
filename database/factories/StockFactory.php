<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Models\Stock;
use App\Domain\Product\Models\Product;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<Stock>
 */
final class StockFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Stock::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity_available' => $this->faker->numberBetween(1, 100),
            'quantity_reserved' => $this->faker->numberBetween(1, 100),
            'updated_at' => $this->faker->dateTimeBetween('-1 years', 'now'),
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
        ];
    }

    /**
     * Associate the stock with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
