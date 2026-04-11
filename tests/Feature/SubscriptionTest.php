<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('Subscriptions page', function () {
    it('redirects guests to login', function () {
        $this->get('/en/subscriptions')->assertRedirect();
    });

    it('renders the subscriptions page for authenticated users', function () {
        $this->actingAsUserInTenant();

        $this->get('/en/subscriptions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Subscriptions/Index')
                ->has('plans')
                ->has('subscriptions')
                ->has('has_payment_method')
            );
    });

    it('shows only active tenant-scoped plans', function () {
        $this->actingAsUserInTenant();

        SubscriptionPlan::factory()->create([
            'name' => 'Pro Monthly',
            'billing_interval' => 'monthly',
            'is_active' => true,
        ]);

        SubscriptionPlan::factory()->create([
            'name' => 'Inactive Plan',
            'is_active' => false,
        ]);

        $this->get('/en/subscriptions')
            ->assertInertia(fn ($page) => $page
                ->has('plans', 1)
                ->where('plans.0.name', 'Pro Monthly')
            );
    });

    it('includes formatted price and interval label', function () {
        $this->actingAsUserInTenant();

        SubscriptionPlan::factory()->create([
            'price_cents' => 1999,
            'billing_interval' => 'monthly',
            'is_active' => true,
        ]);

        $this->get('/en/subscriptions')
            ->assertInertia(fn ($page) => $page
                ->where('plans.0.formatted_price', '$19.99')
                ->where('plans.0.interval_label', 'month')
            );
    });

    it('includes platform-wide plans but excludes other tenants', function () {
        $this->actingAsUserInTenant();

        SubscriptionPlan::factory()->create([
            'tenant_id' => null,
            'name' => 'Platform Plan',
            'is_active' => true,
        ]);

        $otherTenant = Tenant::factory()->create();
        SubscriptionPlan::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Plan',
            'is_active' => true,
        ]);

        $this->get('/en/subscriptions')
            ->assertInertia(fn ($page) => $page
                ->has('plans', 1)
                ->where('plans.0.name', 'Platform Plan')
            );
    });

    it('shows empty subscriptions for new users', function () {
        $this->actingAsUserInTenant();

        $this->get('/en/subscriptions')
            ->assertInertia(fn ($page) => $page
                ->has('subscriptions', 0)
                ->where('has_payment_method', false)
            );
    });
});
