<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Category\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenantId = Context::get('tenant_id');

        return [
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'price_cents' => fake()->numberBetween(1000, 100000),
            'currency' => 'MYR',
            'is_active' => fake()->boolean(90),
            'slug' => fake()->slug(),
            'images' => ['https://placehold.co/640x480/'.mb_substr(md5(fake()->word()), 0, 6).'/white?text='.urlencode(fake()->word())],
            'meta_title' => fake()->optional()->sentence(6),
            'meta_description' => json_encode([
                'content' => fake()->optional()->paragraph(),
            ]),
            'sale_price' => fake()->numberBetween(1000, 100000),
            'category_id' => $tenantId
                ? Category::query()->inRandomOrder()->first()?->id ?? Category::factory()->create()->id
                : Category::factory()->create()->id,
            'tenant_id' => $tenantId ?? Tenant::factory(),
        ];
    }

    /**
     * Associate the product with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
