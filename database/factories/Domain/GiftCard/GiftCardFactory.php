<?php

declare(strict_types=1);

namespace Database\Factories\Domain\GiftCard;

use App\Domain\GiftCard\Models\GiftCard;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * @extends Factory<GiftCard>
 */
final class GiftCardFactory extends Factory
{
    protected $model = GiftCard::class;

    public function definition(): array
    {
        $balance = fake()->numberBetween(1000, 50000);

        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'code' => mb_strtoupper(Str::random(10)),
            'initial_balance_cents' => $balance,
            'balance_cents' => $balance,
            'expires_at' => fake()->optional(0.3)->dateTimeBetween('+1 month', '+1 year'),
            'is_active' => true,
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function depleted(): self
    {
        return $this->state(fn (array $attributes): array => [
            'balance_cents' => 0,
        ]);
    }
}
