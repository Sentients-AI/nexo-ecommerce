<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Product\Models\VariantAttributeType;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * @extends Factory<VariantAttributeType>
 */
final class VariantAttributeTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = VariantAttributeType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Color', 'Size', 'Material', 'Style', 'Finish', 'Weight', 'Length']);

        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sort_order' => fake()->numberBetween(0, 10),
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
}
