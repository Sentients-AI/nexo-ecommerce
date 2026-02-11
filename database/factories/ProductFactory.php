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
            'sku' => $this->faker->unique()->bothify('SKU-####-????'),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->paragraph(),
            'price_cents' => $this->faker->numberBetween(1000, 100000),
            'currency' => 'MYR',
            'is_active' => $this->faker->boolean(90),
            'slug' => $this->faker->slug(),
            'images' => ['https://placehold.co/640x480/'.mb_substr(md5($this->faker->word()), 0, 6).'/white?text='.urlencode($this->faker->word())],
            'meta_title' => $this->faker->optional()->sentence(6),
            'meta_description' => json_encode([
                'content' => $this->faker->optional()->paragraph(),
            ]),
            'sale_price' => $this->faker->numberBetween(1000, 100000),
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
