<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<ProductVariant>
 */
final class ProductVariantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = ProductVariant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenantId = Context::get('tenant_id');

        return [
            'tenant_id' => $tenantId ?? Tenant::factory(),
            'product_id' => $tenantId
                ? Product::query()->inRandomOrder()->first()?->id ?? Product::factory()->create()->id
                : Product::factory()->create()->id,
            'sku' => fake()->unique()->bothify('VAR-####-????'),
            'price_cents' => fake()->optional(0.6)->numberBetween(1000, 100000),
            'sale_price' => fake()->optional(0.2)->numberBetween(500, 50000),
            'is_active' => fake()->boolean(90),
            'sort_order' => fake()->numberBetween(0, 20),
            'images' => null,
        ];
    }

    /**
     * Associate with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Associate with a specific product.
     */
    public function forProduct(Product $product): self
    {
        return $this->state(fn (array $attributes): array => [
            'product_id' => $product->id,
            'tenant_id' => $product->tenant_id,
        ]);
    }

    /**
     * Mark as active.
     */
    public function active(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
        ]);
    }
}
