<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Review\Models\Review;
use App\Domain\Review\Models\ReviewPhoto;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<ReviewPhoto>
 */
final class ReviewPhotoFactory extends Factory
{
    /**
     * @var class-string<Model>
     */
    protected $model = ReviewPhoto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'review_id' => Review::factory(),
            'path' => 'review-photos/'.fake()->uuid().'.jpg',
            'disk' => 'public',
            'order' => fake()->numberBetween(0, 4),
        ];
    }

    /**
     * Associate the photo with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
