<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Product\Models\Product;
use App\Domain\Review\Models\Review;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<Review>
 */
final class ReviewFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Review::class;

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
            'user_id' => $tenantId
                ? User::query()->inRandomOrder()->first()?->id ?? User::factory()->create()->id
                : User::factory()->create()->id,
            'rating' => $this->faker->numberBetween(1, 5),
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(),
            'is_approved' => true,
        ];
    }

    /**
     * Associate the review with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Mark the review as unapproved.
     */
    public function unapproved(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_approved' => false,
        ]);
    }
}
