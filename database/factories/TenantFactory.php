<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => fake()->unique()->slug(2),
            'domain' => null,
            'email' => fake()->companyEmail(),
            'is_active' => true,
            'settings' => [
                'currency' => 'MYR',
                'timezone' => 'Asia/Kuala_Lumpur',
                'tax_rate' => 0,
            ],
            'trial_ends_at' => null,
            'subscribed_at' => null,
        ];
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the tenant is on trial.
     */
    public function onTrial(int $days = 14): self
    {
        return $this->state(fn (array $attributes): array => [
            'trial_ends_at' => now()->addDays($days),
            'subscribed_at' => null,
        ]);
    }

    /**
     * Indicate that the tenant has an active subscription.
     */
    public function subscribed(): self
    {
        return $this->state(fn (array $attributes): array => [
            'trial_ends_at' => null,
            'subscribed_at' => now(),
        ]);
    }

    /**
     * Indicate the tenant has a custom domain.
     */
    public function withDomain(string $domain): self
    {
        return $this->state(fn (array $attributes): array => [
            'domain' => $domain,
        ]);
    }
}
