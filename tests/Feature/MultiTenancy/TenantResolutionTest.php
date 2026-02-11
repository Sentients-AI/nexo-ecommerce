<?php

declare(strict_types=1);

use App\Domain\Tenant\Models\Tenant;
use App\Http\Middleware\ResolveTenantFromSubdomain;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Tenant Resolution from Subdomain', function (): void {
    beforeEach(function (): void {
        Config::set('tenancy.base_domain', 'example.com');
        Config::set('tenancy.reserved_subdomains', ['www', 'api', 'admin']);
    });

    it('resolves tenant from subdomain', function (): void {
        $tenant = Tenant::factory()->create(['slug' => 'acme', 'is_active' => true]);

        $middleware = new ResolveTenantFromSubdomain;
        $request = Request::create('http://acme.example.com/dashboard');

        $response = $middleware->handle($request, function ($req) use ($tenant): ResponseFactory|Response {
            expect(Context::get('tenant_id'))->toBe($tenant->id);
            expect(Context::get('tenant'))->toBeInstanceOf(Tenant::class);

            return response('OK');
        });

        expect($response->getContent())->toBe('OK');
    });

    it('resolves tenant from custom domain', function (): void {
        $tenant = Tenant::factory()
            ->withDomain('custom.shop')
            ->create(['slug' => 'myshop', 'is_active' => true]);

        $middleware = new ResolveTenantFromSubdomain;
        $request = Request::create('http://custom.shop/dashboard');

        $response = $middleware->handle($request, function ($req) use ($tenant): ResponseFactory|Response {
            expect(Context::get('tenant_id'))->toBe($tenant->id);

            return response('OK');
        });

        expect($response->getContent())->toBe('OK');
    });

    it('throws not found for non-existent tenant', function (): void {
        $middleware = new ResolveTenantFromSubdomain;
        $request = Request::create('http://nonexistent.example.com/dashboard');

        $middleware->handle($request, fn () => response('OK'));
    })->throws(NotFoundHttpException::class, 'Tenant not found.');

    it('throws not found for inactive tenant', function (): void {
        Tenant::factory()->create(['slug' => 'inactive', 'is_active' => false]);

        $middleware = new ResolveTenantFromSubdomain;
        $request = Request::create('http://inactive.example.com/dashboard');

        $middleware->handle($request, fn () => response('OK'));
    })->throws(NotFoundHttpException::class, 'Tenant is inactive.');

    it('returns null for reserved subdomains', function (): void {
        $middleware = new ResolveTenantFromSubdomain;
        $request = Request::create('http://www.example.com/dashboard');

        $middleware->handle($request, fn () => response('OK'));
    })->throws(NotFoundHttpException::class);

    it('returns null for base domain without subdomain', function (): void {
        $middleware = new ResolveTenantFromSubdomain;
        $request = Request::create('http://example.com/dashboard');

        $middleware->handle($request, fn () => response('OK'));
    })->throws(NotFoundHttpException::class);
});

describe('Tenant Model', function (): void {
    it('can check if tenant is active', function (): void {
        $activeTenant = Tenant::factory()->create(['is_active' => true]);
        $inactiveTenant = Tenant::factory()->create(['is_active' => false]);

        expect($activeTenant->isActive())->toBeTrue();
        expect($inactiveTenant->isActive())->toBeFalse();
    });

    it('can check if tenant is on trial', function (): void {
        $onTrialTenant = Tenant::factory()->onTrial()->create();
        $notOnTrialTenant = Tenant::factory()->create(['trial_ends_at' => null]);
        $expiredTrialTenant = Tenant::factory()->create([
            'trial_ends_at' => now()->subDay(),
        ]);

        expect($onTrialTenant->isOnTrial())->toBeTrue();
        expect($notOnTrialTenant->isOnTrial())->toBeFalse();
        expect($expiredTrialTenant->isOnTrial())->toBeFalse();
    });

    it('can check if tenant is subscribed', function (): void {
        $subscribedTenant = Tenant::factory()->subscribed()->create();
        $unsubscribedTenant = Tenant::factory()->create(['subscribed_at' => null]);

        expect($subscribedTenant->isSubscribed())->toBeTrue();
        expect($unsubscribedTenant->isSubscribed())->toBeFalse();
    });

    it('can get and set settings', function (): void {
        $tenant = Tenant::factory()->create([
            'settings' => [
                'currency' => 'USD',
                'timezone' => 'America/New_York',
                'nested' => ['key' => 'value'],
            ],
        ]);

        expect($tenant->getSetting('currency'))->toBe('USD');
        expect($tenant->getSetting('timezone'))->toBe('America/New_York');
        expect($tenant->getSetting('nested.key'))->toBe('value');
        expect($tenant->getSetting('nonexistent', 'default'))->toBe('default');
    });

    it('has unique slug constraint', function (): void {
        Tenant::factory()->create(['slug' => 'unique-slug']);

        Tenant::factory()->create(['slug' => 'unique-slug']);
    })->throws(QueryException::class);
});
