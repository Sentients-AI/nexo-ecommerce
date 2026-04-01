<?php

declare(strict_types=1);

use App\Domain\Tax\Actions\CalculateTax;
use App\Domain\Tax\DTOs\TaxCalculationData;
use App\Domain\Tax\Models\TaxZone;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function (): void {
    $this->setUpTenant();
    config(['tax.enabled' => true, 'tax.rate' => 0.0]);
});

describe('CalculateTax — disabled', function (): void {
    it('returns zero when tax is globally disabled', function (): void {
        config(['tax.enabled' => false]);

        TaxZone::factory()->withRate(0.1)->create();

        $result = app(CalculateTax::class)->execute(
            new TaxCalculationData(subtotalCents: 10000)
        );

        expect($result)->toBe(0);
    });
});

describe('CalculateTax — config fallback', function (): void {
    it('falls back to config rate when no zones exist', function (): void {
        config(['tax.rate' => 0.06]);

        $result = app(CalculateTax::class)->execute(
            new TaxCalculationData(subtotalCents: 10000)
        );

        expect($result)->toBe(600);
    });

    it('returns zero when no zones and config rate is zero', function (): void {
        config(['tax.rate' => 0.0]);

        $result = app(CalculateTax::class)->execute(
            new TaxCalculationData(subtotalCents: 10000)
        );

        expect($result)->toBe(0);
    });
});

describe('CalculateTax — global zone', function (): void {
    it('applies global zone (null country) to all orders', function (): void {
        TaxZone::factory()->withRate(0.1)->create([
            'country_code' => null,
            'region_code' => null,
        ]);

        $result = app(CalculateTax::class)->execute(
            new TaxCalculationData(subtotalCents: 10000)
        );

        expect($result)->toBe(1000);
    });

    it('global zone applies even when country is specified but has no matching zone', function (): void {
        TaxZone::factory()->withRate(0.08)->create([
            'country_code' => null,
            'region_code' => null,
        ]);

        $result = app(CalculateTax::class)->execute(
            new TaxCalculationData(subtotalCents: 10000, countryCode: 'DE')
        );

        expect($result)->toBe(800);
    });
});

describe('CalculateTax — country-specific zone', function (): void {
    it('matches country-specific zone over global zone', function (): void {
        TaxZone::factory()->withRate(0.05)->create([
            'country_code' => null,
            'region_code' => null,
        ]);
        TaxZone::factory()->forCountry('MY')->withRate(0.06)->create();

        $result = app(CalculateTax::class)->execute(
            new TaxCalculationData(subtotalCents: 10000, countryCode: 'MY')
        );

        expect($result)->toBe(600);
    });

    it('does not apply country zone to other countries', function (): void {
        TaxZone::factory()->forCountry('MY')->withRate(0.06)->create();
        config(['tax.rate' => 0.0]);

        $result = app(CalculateTax::class)->execute(
            new TaxCalculationData(subtotalCents: 10000, countryCode: 'SG')
        );

        expect($result)->toBe(0);
    });
});

describe('CalculateTax — region-specific zone', function (): void {
    it('matches region-specific zone over country zone', function (): void {
        TaxZone::factory()->forCountry('US')->withRate(0.05)->create();
        TaxZone::factory()->forCountry('US', 'CA')->withRate(0.0725)->create();

        $result = app(CalculateTax::class)->execute(
            new TaxCalculationData(subtotalCents: 10000, countryCode: 'US', regionCode: 'CA')
        );

        expect($result)->toBe(725);
    });

    it('falls back to country zone when region has no specific zone', function (): void {
        TaxZone::factory()->forCountry('US')->withRate(0.05)->create();
        TaxZone::factory()->forCountry('US', 'CA')->withRate(0.0725)->create();

        $result = app(CalculateTax::class)->execute(
            new TaxCalculationData(subtotalCents: 10000, countryCode: 'US', regionCode: 'TX')
        );

        expect($result)->toBe(500);
    });
});

describe('CalculateTax — inactive zones', function (): void {
    it('skips inactive zones and falls back to next match', function (): void {
        TaxZone::factory()->forCountry('MY')->withRate(0.06)->inactive()->create();
        TaxZone::factory()->withRate(0.1)->create([
            'country_code' => null,
            'region_code' => null,
        ]);

        $result = app(CalculateTax::class)->execute(
            new TaxCalculationData(subtotalCents: 10000, countryCode: 'MY')
        );

        expect($result)->toBe(1000);
    });

    it('skips inactive global zone and falls back to config', function (): void {
        TaxZone::factory()->withRate(0.2)->inactive()->create([
            'country_code' => null,
            'region_code' => null,
        ]);
        config(['tax.rate' => 0.05]);

        $result = app(CalculateTax::class)->execute(
            new TaxCalculationData(subtotalCents: 10000)
        );

        expect($result)->toBe(500);
    });
});

describe('CalculateTax — tenant priority', function (): void {
    it('tenant-specific zone takes priority over platform-wide zone', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);

        // Platform-wide zone (null tenant_id)
        TaxZone::factory()->withRate(0.05)->create([
            'tenant_id' => null,
            'country_code' => 'MY',
            'region_code' => null,
        ]);

        // Tenant-specific zone
        TaxZone::factory()->forTenant($tenant)->forCountry('MY')->withRate(0.06)->create();

        $result = app(CalculateTax::class)->execute(
            new TaxCalculationData(subtotalCents: 10000, countryCode: 'MY')
        );

        expect($result)->toBe(600);
    });

    it('falls back to platform-wide zone when tenant has no matching zone', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);

        TaxZone::factory()->withRate(0.07)->create([
            'tenant_id' => null,
            'country_code' => 'MY',
            'region_code' => null,
        ]);

        $result = app(CalculateTax::class)->execute(
            new TaxCalculationData(subtotalCents: 10000, countryCode: 'MY')
        );

        expect($result)->toBe(700);
    });
});

describe('TaxZone model', function (): void {
    it('coverageLabel returns Global for null country', function (): void {
        $zone = TaxZone::factory()->make(['country_code' => null, 'region_code' => null]);

        expect($zone->coverageLabel())->toBe('Global (all countries)');
    });

    it('coverageLabel returns country code only when no region', function (): void {
        $zone = TaxZone::factory()->make(['country_code' => 'MY', 'region_code' => null]);

        expect($zone->coverageLabel())->toBe('MY');
    });

    it('coverageLabel returns country — region when both set', function (): void {
        $zone = TaxZone::factory()->make(['country_code' => 'US', 'region_code' => 'CA']);

        expect($zone->coverageLabel())->toBe('US — CA');
    });
});
