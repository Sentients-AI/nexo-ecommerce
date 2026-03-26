<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Review\Models\Review;
use App\Domain\Review\Models\ReviewReply;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<ReviewReply>
 */
final class ReviewReplyFactory extends Factory
{
    /**
     * @var class-string<Model>
     */
    protected $model = ReviewReply::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'review_id' => Review::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'is_merchant_reply' => false,
        ];
    }

    /**
     * Associate the reply with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Mark as a merchant reply.
     */
    public function merchantReply(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_merchant_reply' => true,
        ]);
    }
}
