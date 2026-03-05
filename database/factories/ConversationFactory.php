<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Chat\Enums\ConversationStatus;
use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<Conversation>
 */
final class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject' => $this->faker->optional()->sentence(4),
            'status' => ConversationStatus::Open,
            'type' => ConversationType::Store,
            'last_message_at' => now(),
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
        ];
    }

    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function open(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ConversationStatus::Open,
        ]);
    }

    public function closed(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ConversationStatus::Closed,
        ]);
    }

    public function asStore(): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ConversationType::Store,
        ]);
    }

    public function support(): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ConversationType::Support,
            'tenant_id' => null,
        ]);
    }
}
