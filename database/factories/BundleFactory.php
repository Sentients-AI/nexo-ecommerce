<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Bundle\Models\Bundle;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * @extends Factory<Bundle>
 */
final class BundleFactory extends Factory
{
    protected $model = Bundle::class;

    public function definition(): array
    {
        $name = fake()->words(3, true).' Bundle';

        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->optional()->paragraph(),
            'price_cents' => fake()->numberBetween(1999, 9999),
            'compare_at_price_cents' => fake()->numberBetween(10000, 19999),
            'images' => [],
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
