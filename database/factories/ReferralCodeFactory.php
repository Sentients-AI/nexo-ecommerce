<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Referral\Models\ReferralCode;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReferralCode>
 */
final class ReferralCodeFactory extends Factory
{
    protected $model = ReferralCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'user_id' => User::factory(),
            'code' => Str::upper(Str::random(12)),
            'referrer_reward_points' => 500,
            'referee_discount_percent' => 10,
            'max_uses' => 10,
            'used_count' => 0,
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ];
    }

    /**
     * Associate the referral code with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Create an expired referral code.
     */
    public function expired(): self
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDays(1),
        ]);
    }

    /**
     * Create an exhausted referral code (max uses reached).
     */
    public function exhausted(): self
    {
        return $this->state(fn (array $attributes): array => [
            'max_uses' => 5,
            'used_count' => 5,
        ]);
    }

    /**
     * Create an inactive referral code.
     */
    public function inactive(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
