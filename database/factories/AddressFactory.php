<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<Address>
 */
final class AddressFactory extends Factory
{
    /**
     * @var class-string<Model>
     */
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => 'MY',
            'is_default' => false,
        ];
    }

    public function default(): self
    {
        return $this->state(fn (array $attributes): array => ['is_default' => true]);
    }

    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => ['tenant_id' => $tenant->id]);
    }
}
