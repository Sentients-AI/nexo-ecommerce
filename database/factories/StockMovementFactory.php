<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Product\Models\Product;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<StockMovement>
 */
final class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'stock_id' => Stock::factory(),
            'type' => fake()->randomElement(StockMovementType::getTypes()),
            'quantity' => fake()->numberBetween(1, 100),
            'reference_type' => fake()->optional()->randomElement([\App\Domain\Order\Models\Order::class, \App\Domain\Cart\Models\Cart::class]),
            'reference_id' => fake()->optional()->numberBetween(1, 100),
            'reason' => fake()->text,
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
        ];
    }

    /**
     * Associate the stock movement with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
