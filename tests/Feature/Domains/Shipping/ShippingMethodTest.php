<?php

declare(strict_types=1);

use App\Domain\Shipping\Actions\CalculateShippingCostAction;
use App\Domain\Shipping\Enums\ShippingType;
use App\Domain\Shipping\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('ShippingMethod cost calculation', function () {
    it('flat rate always charges the set rate', function () {
        $method = ShippingMethod::factory()->create([
            'type' => ShippingType::FlatRate,
            'rate_cents' => 999,
        ]);

        expect($method->calculateCost(500))->toBe(999)
            ->and($method->calculateCost(50000))->toBe(999);
    });

    it('free type always returns zero', function () {
        $method = ShippingMethod::factory()->free()->create();

        expect($method->calculateCost(0))->toBe(0)
            ->and($method->calculateCost(100000))->toBe(0);
    });

    it('free over amount returns zero when subtotal meets threshold', function () {
        $method = ShippingMethod::factory()->create([
            'type' => ShippingType::FreeOverAmount,
            'rate_cents' => 599,
            'min_order_cents' => 10000,
        ]);

        expect($method->calculateCost(10000))->toBe(0)
            ->and($method->calculateCost(15000))->toBe(0);
    });

    it('free over amount charges rate when subtotal is below threshold', function () {
        $method = ShippingMethod::factory()->create([
            'type' => ShippingType::FreeOverAmount,
            'rate_cents' => 599,
            'min_order_cents' => 10000,
        ]);

        expect($method->calculateCost(9999))->toBe(599)
            ->and($method->calculateCost(500))->toBe(599);
    });
});

describe('CalculateShippingCostAction', function () {
    it('returns zero when no shipping method is selected', function () {
        $action = new CalculateShippingCostAction;

        expect($action->execute(null, 5000))->toBe(0);
    });

    it('returns zero when shipping method does not exist', function () {
        $action = new CalculateShippingCostAction;

        expect($action->execute(999999, 5000))->toBe(0);
    });

    it('returns zero for inactive shipping methods', function () {
        $method = ShippingMethod::factory()->create([
            'type' => ShippingType::FlatRate,
            'rate_cents' => 999,
            'is_active' => false,
        ]);
        $action = new CalculateShippingCostAction;

        expect($action->execute($method->id, 5000))->toBe(0);
    });

    it('calculates cost correctly for active method', function () {
        $method = ShippingMethod::factory()->create([
            'type' => ShippingType::FlatRate,
            'rate_cents' => 1500,
            'is_active' => true,
        ]);
        $action = new CalculateShippingCostAction;

        expect($action->execute($method->id, 5000))->toBe(1500);
    });
});

describe('ShippingMethod API', function () {
    it('returns active shipping methods', function () {
        ShippingMethod::factory()->create(['name' => 'Standard', 'is_active' => true, 'sort_order' => 1]);
        ShippingMethod::factory()->create(['name' => 'Express', 'is_active' => true, 'sort_order' => 2]);
        ShippingMethod::factory()->create(['name' => 'Hidden', 'is_active' => false]);

        $response = $this->getJson('/api/v1/shipping-methods');

        $response->assertOk();
        $data = $response->json('data');
        $names = collect($data)->pluck('name');

        expect($names)->toContain('Standard')
            ->and($names)->toContain('Express')
            ->and($names)->not->toContain('Hidden');
    });

    it('calculates cost_cents based on subtotal_cents query parameter', function () {
        ShippingMethod::factory()->create([
            'name' => 'Free Over 100',
            'type' => ShippingType::FreeOverAmount,
            'rate_cents' => 599,
            'min_order_cents' => 10000,
            'is_active' => true,
        ]);

        $belowThreshold = $this->getJson('/api/v1/shipping-methods?subtotal_cents=5000');
        $aboveThreshold = $this->getJson('/api/v1/shipping-methods?subtotal_cents=10000');

        $belowThreshold->assertOk();
        $aboveThreshold->assertOk();

        $below = collect($belowThreshold->json('data'))->firstWhere('name', 'Free Over 100');
        $above = collect($aboveThreshold->json('data'))->firstWhere('name', 'Free Over 100');

        expect($below['cost_cents'])->toBe(599)
            ->and($above['cost_cents'])->toBe(0);
    });

    it('returns estimated delivery label', function () {
        ShippingMethod::factory()->create([
            'is_active' => true,
            'estimated_days_min' => 3,
            'estimated_days_max' => 5,
        ]);

        $response = $this->getJson('/api/v1/shipping-methods');
        $method = collect($response->json('data'))->first();

        expect($method['estimated_delivery'])->toContain('3')
            ->and($method['estimated_delivery'])->toContain('5');
    });
});
