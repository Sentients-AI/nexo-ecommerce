<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<LoyaltyAccount>
 */
final class LoyaltyAccountFactory extends Factory
{
    protected $model = LoyaltyAccount::class;

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
            'points_balance' => 0,
            'total_points_earned' => 0,
            'total_points_redeemed' => 0,
        ];
    }

    /**
     * Associate the loyalty account with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Set a specific points balance and total earned.
     */
    public function withPoints(int $points): self
    {
        return $this->state(fn (array $attributes): array => [
            'points_balance' => $points,
            'total_points_earned' => $points,
        ]);
    }
}
