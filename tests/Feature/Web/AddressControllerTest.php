<?php

declare(strict_types=1);

use App\Domain\User\Models\Address;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->actingAsUserInTenant();
});

describe('GET /addresses', function () {
    it('requires authentication', function () {
        auth()->logout();

        $this->get('/en/addresses')->assertRedirect();
    });

    it('renders addresses page', function () {
        Address::factory()->for(auth()->user())->count(2)->create(['tenant_id' => $this->tenant->id]);

        $this->get('/en/addresses')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Addresses')
                ->has('addresses', 2)
            );
    });

    it('does not return other users addresses', function () {
        Address::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->get('/en/addresses')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('addresses', 0)
            );
    });
});

describe('POST /addresses', function () {
    it('creates an address', function () {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/en/addresses', [
                'name' => 'Home',
                'address_line_1' => '123 Main St',
                'city' => 'Kuala Lumpur',
                'postal_code' => '50000',
                'country' => 'MY',
                'is_default' => true,
            ])
            ->assertRedirect('/en/addresses');

        expect(Address::where('user_id', auth()->id())->where('city', 'Kuala Lumpur')->exists())->toBeTrue();
    });

    it('validates required fields', function () {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/en/addresses', [])
            ->assertSessionHasErrors(['name', 'address_line_1', 'city', 'postal_code', 'country']);
    });
});

describe('PATCH /addresses/{address}', function () {
    it('updates an address', function () {
        $address = Address::factory()->for(auth()->user())->create(['tenant_id' => $this->tenant->id, 'city' => 'Old City']);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/en/addresses/{$address->id}", [
                'name' => 'Home',
                'address_line_1' => '123 St',
                'city' => 'New City',
                'postal_code' => '50000',
                'country' => 'MY',
            ])
            ->assertRedirect('/en/addresses');

        expect($address->fresh()->city)->toBe('New City');
    });

    it('returns 403 when address belongs to another user', function () {
        $address = Address::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/en/addresses/{$address->id}", [
                'name' => 'Home',
                'address_line_1' => '1 St',
                'city' => 'KL',
                'postal_code' => '50000',
                'country' => 'MY',
            ])
            ->assertForbidden();
    });
});

describe('DELETE /addresses/{address}', function () {
    it('deletes an address', function () {
        $address = Address::factory()->for(auth()->user())->create(['tenant_id' => $this->tenant->id]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->delete("/en/addresses/{$address->id}")
            ->assertRedirect('/en/addresses');

        expect(Address::find($address->id))->toBeNull();
    });

    it('returns 403 when address belongs to another user', function () {
        $address = Address::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->delete("/en/addresses/{$address->id}")
            ->assertForbidden();
    });
});

describe('PATCH /addresses/{address}/default', function () {
    it('sets address as default', function () {
        $address = Address::factory()->for(auth()->user())->create(['tenant_id' => $this->tenant->id, 'is_default' => false]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/en/addresses/{$address->id}/default")
            ->assertRedirect('/en/addresses');

        expect($address->fresh()->is_default)->toBeTrue();
    });

    it('returns 403 when address belongs to another user', function () {
        $address = Address::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/en/addresses/{$address->id}/default")
            ->assertForbidden();
    });
});
