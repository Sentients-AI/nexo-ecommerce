<?php

declare(strict_types=1);

use App\Domain\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function (): void {
    $this->actingAsUserInTenant();
});

// ── Index ─────────────────────────────────────────────────────────────────────

describe('GET /vendor/storefront', function (): void {
    it('returns the storefront page', function (): void {
        $this->get('/vendor/storefront')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/Storefront'));
    });

    it('includes current storefront data', function (): void {
        $tenant = Context::get('tenant');
        $tenant->update([
            'description' => 'Our awesome store',
            'accent_color' => '#ff5500',
        ]);

        $this->get('/vendor/storefront')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('storefront.description', 'Our awesome store')
                ->where('storefront.accent_color', '#ff5500')
            );
    });
});

// ── Update ────────────────────────────────────────────────────────────────────

describe('PATCH /vendor/storefront', function (): void {
    it('saves storefront customization', function (): void {
        $this->patch('/vendor/storefront', [
            'description' => 'Fresh branding for our store',
            'accent_color' => '#123456',
            'logo_path' => 'https://example.com/logo.png',
            'banner_path' => 'https://example.com/banner.jpg',
            'social_links' => [
                'twitter' => 'https://twitter.com/mystore',
                'instagram' => 'https://instagram.com/mystore',
            ],
        ])->assertRedirect();

        $tenant = Tenant::query()->find(Context::get('tenant_id'));

        expect($tenant->description)->toBe('Fresh branding for our store')
            ->and($tenant->accent_color)->toBe('#123456')
            ->and($tenant->logo_path)->toBe('https://example.com/logo.png')
            ->and($tenant->banner_path)->toBe('https://example.com/banner.jpg')
            ->and($tenant->social_links['twitter'])->toBe('https://twitter.com/mystore');
    });

    it('accepts null fields to clear customization', function (): void {
        $tenant = Context::get('tenant');
        $tenant->update(['description' => 'Old description']);

        $this->patch('/vendor/storefront', ['description' => null])
            ->assertRedirect();

        expect(Tenant::query()->find($tenant->id)->description)->toBeNull();
    });

    it('rejects invalid accent color', function (): void {
        $this->patch('/vendor/storefront', ['accent_color' => 'not-a-color'])
            ->assertSessionHasErrors('accent_color');
    });

    it('rejects invalid logo url', function (): void {
        $this->patch('/vendor/storefront', ['logo_path' => 'not-a-url'])
            ->assertSessionHasErrors('logo_path');
    });
});
