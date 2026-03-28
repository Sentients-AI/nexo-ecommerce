<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Idempotency\Models\IdempotencyKey;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<IdempotencyKey>
 */
final class IdempotencyKeyFactory extends Factory
{
    protected $model = IdempotencyKey::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->uuid(),
            'user_id' => User::factory(),
            'response_body' => hash('sha256', fake()->text()),
            'response_code' => fake()->randomElement([200, 201]),
            'created_at' => fake()->dateTime(),
            'expires_at' => fake()->dateTime('+1 month'),
            'request_fingerprint' => fake()->sha256(),
            'action' => fake()->word(),
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
        ];
    }

    /**
     * Associate the idempotency key with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
