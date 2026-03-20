<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Loyalty\Enums\TransactionType;
use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Loyalty\Models\LoyaltyTransaction;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<LoyaltyTransaction>
 */
final class LoyaltyTransactionFactory extends Factory
{
    protected $model = LoyaltyTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $points = $this->faker->numberBetween(10, 500);

        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'user_id' => User::factory(),
            'loyalty_account_id' => LoyaltyAccount::factory(),
            'type' => TransactionType::Earned,
            'points' => $points,
            'balance_after' => $points,
            'description' => $this->faker->sentence(),
        ];
    }

    /**
     * Associate the loyalty transaction with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
