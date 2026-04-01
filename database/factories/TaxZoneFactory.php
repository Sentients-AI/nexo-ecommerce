<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tax\Models\TaxZone;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<TaxZone>
 */
final class TaxZoneFactory extends Factory
{
    protected $model = TaxZone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'name' => fake()->randomElement(['Standard Rate', 'Reduced Rate', 'GST', 'VAT', 'SST']),
            'country_code' => null,
            'region_code' => null,
            'rate' => '0.1000',
            'is_active' => true,
        ];
    }

    public function forCountry(string $countryCode, ?string $regionCode = null): static
    {
        return $this->state([
            'country_code' => $countryCode,
            'region_code' => $regionCode,
        ]);
    }

    public function withRate(float $rate): static
    {
        return $this->state(['rate' => number_format($rate, 4)]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
