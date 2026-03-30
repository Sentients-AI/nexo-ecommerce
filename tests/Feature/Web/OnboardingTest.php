<?php

declare(strict_types=1);

use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Seed roles so admin role is available
    $this->seed(RoleSeeder::class);
});

function validOnboardingPayload(array $overrides = []): array
{
    return array_merge([
        'store_name' => 'Test Store',
        'store_slug' => 'test-store',
        'store_email' => 'store@test.com',
        'name' => 'Jane Smith',
        'email' => 'jane@test.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ], $overrides);
}

describe('Onboarding page', function () {
    it('renders the onboarding page for guests', function () {
        $response = $this->get('/start');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Onboarding/Create')
            ->has('baseDomain')
            ->has('reservedSlugs')
        );
    });

    it('redirects authenticated users away from onboarding', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->create();

        $response = $this->actingAs($user)->get('/start');

        $response->assertRedirect();
    });
});

describe('Onboarding submission', function () {
    it('creates a tenant and admin user on valid submission', function () {
        $response = $this->post('/start', validOnboardingPayload());

        $response->assertRedirect(route('vendor.dashboard'));

        expect(Tenant::withoutGlobalScopes()->where('slug', 'test-store')->exists())->toBeTrue();
        expect(User::withoutGlobalScopes()->where('email', 'jane@test.com')->exists())->toBeTrue();

        $tenant = Tenant::withoutGlobalScopes()->where('slug', 'test-store')->first();
        expect($tenant->name)->toBe('Test Store')
            ->and($tenant->is_active)->toBeTrue()
            ->and($tenant->trial_ends_at)->not->toBeNull()
            ->and($tenant->isOnTrial())->toBeTrue();
    });

    it('assigns the admin role to the new user', function () {
        $this->post('/start', validOnboardingPayload());

        $user = User::withoutGlobalScopes()->where('email', 'jane@test.com')->first();
        $adminRole = Role::where('name', 'admin')->first();

        expect($user->role_id)->toBe($adminRole->id);
    });

    it('associates the user with the new tenant', function () {
        $this->post('/start', validOnboardingPayload());

        $tenant = Tenant::withoutGlobalScopes()->where('slug', 'test-store')->first();
        $user = User::withoutGlobalScopes()->where('email', 'jane@test.com')->first();

        expect($user->tenant_id)->toBe($tenant->id);
    });

    it('logs the user in after onboarding', function () {
        $this->post('/start', validOnboardingPayload());

        expect(Auth::check())->toBeTrue();
        expect(Auth::user()->email)->toBe('jane@test.com');
    });

    it('auto-verifies email on registration', function () {
        $this->post('/start', validOnboardingPayload());

        $user = User::withoutGlobalScopes()->where('email', 'jane@test.com')->first();

        expect($user->email_verified_at)->not->toBeNull();
    });

    it('applies default tenant settings', function () {
        $this->post('/start', validOnboardingPayload());

        $tenant = Tenant::withoutGlobalScopes()->where('slug', 'test-store')->first();
        $defaults = config('tenancy.default_settings', []);

        foreach ($defaults as $key => $value) {
            expect($tenant->getSetting($key))->toBe($value);
        }
    });
});

describe('Onboarding validation', function () {
    it('rejects a reserved subdomain', function () {
        $response = $this->post('/start', validOnboardingPayload(['store_slug' => 'admin']));

        $response->assertSessionHasErrors('store_slug');
    });

    it('rejects a duplicate slug', function () {
        Tenant::factory()->create(['slug' => 'my-shop']);

        $response = $this->post('/start', validOnboardingPayload(['store_slug' => 'my-shop']));

        $response->assertSessionHasErrors('store_slug');
    });

    it('rejects a slug with invalid characters', function () {
        $response = $this->post('/start', validOnboardingPayload(['store_slug' => 'My Shop!']));

        $response->assertSessionHasErrors('store_slug');
    });

    it('rejects a duplicate user email', function () {
        $tenant = Tenant::factory()->create();
        User::factory()->forTenant($tenant)->create(['email' => 'taken@test.com']);

        $response = $this->post('/start', validOnboardingPayload(['email' => 'taken@test.com']));

        $response->assertSessionHasErrors('email');
    });

    it('rejects mismatched passwords', function () {
        $response = $this->post('/start', validOnboardingPayload([
            'password' => 'Password123!',
            'password_confirmation' => 'Different123!',
        ]));

        $response->assertSessionHasErrors('password');
    });

    it('requires all fields', function () {
        $response = $this->post('/start', []);

        $response->assertSessionHasErrors([
            'store_name', 'store_slug', 'store_email', 'name', 'email', 'password',
        ]);
    });

    it('rejects a slug shorter than 3 characters', function () {
        $response = $this->post('/start', validOnboardingPayload(['store_slug' => 'ab']));

        $response->assertSessionHasErrors('store_slug');
    });
});
