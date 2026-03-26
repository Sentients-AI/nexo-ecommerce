<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Product\Models\VariantAttributeType;
use App\Domain\Product\Models\VariantAttributeValue;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * @extends Factory<VariantAttributeValue>
 */
final class VariantAttributeValueFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = VariantAttributeValue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenantId = Context::get('tenant_id');
        $value = $this->faker->unique()->word();

        return [
            'tenant_id' => $tenantId ?? Tenant::factory(),
            'attribute_type_id' => $tenantId
                ? VariantAttributeType::query()->inRandomOrder()->first()?->id ?? VariantAttributeType::factory()->create()->id
                : VariantAttributeType::factory()->create()->id,
            'value' => ucfirst($value),
            'slug' => Str::slug($value),
            'sort_order' => $this->faker->numberBetween(0, 20),
            'metadata' => null,
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
     * Associate with a specific attribute type.
     */
    public function forType(VariantAttributeType $type): self
    {
        return $this->state(fn (array $attributes): array => [
            'attribute_type_id' => $type->id,
            'tenant_id' => $type->tenant_id,
        ]);
    }

    /**
     * Add color hex metadata.
     */
    public function withColor(string $hex): self
    {
        return $this->state(fn (array $attributes): array => [
            'metadata' => ['hex' => $hex],
        ]);
    }
}
