<?php

declare(strict_types=1);

use App\Domain\Order\Models\Order;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function (): void {
    $this->setUpTenant();
});

describe('GET /en/track', function (): void {
    it('renders the tracking page', function (): void {
        $this->get('/en/track')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orders/Track')
                ->where('result', null)
            );
    });

    it('is accessible without authentication', function (): void {
        $this->get('/en/track')->assertOk();
    });
});

describe('POST /en/track', function (): void {
    it('finds a guest order by order number and email', function (): void {
        $order = Order::factory()->create([
            'order_number' => 'ORD-TESTGUEST',
            'guest_email' => 'guest@example.com',
            'user_id' => null,
            'status' => 'shipped',
            'carrier' => 'fedex',
            'tracking_number' => '123456789',
        ]);

        $this->post('/en/track', [
            'order_number' => 'ORD-TESTGUEST',
            'email' => 'guest@example.com',
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('result.found', true)
                ->where('result.order_number', 'ORD-TESTGUEST')
                ->where('result.status', 'shipped')
                ->where('result.carrier', 'fedex')
                ->where('result.tracking_number', '123456789')
                ->has('result.tracking_url')
            );
    });

    it('finds an authenticated user order by order number and user email', function (): void {
        $user = User::factory()->create(['email' => 'auth@example.com']);
        $order = Order::factory()->create([
            'order_number' => 'ORD-AUTHUSER',
            'user_id' => $user->id,
            'guest_email' => null,
            'status' => 'shipped',
            'tracking_number' => 'TRACK123',
        ]);

        $this->post('/en/track', [
            'order_number' => 'ORD-AUTHUSER',
            'email' => 'auth@example.com',
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('result.found', true));
    });

    it('returns not found for wrong email', function (): void {
        Order::factory()->create([
            'order_number' => 'ORD-SECRET',
            'guest_email' => 'real@example.com',
            'user_id' => null,
        ]);

        $this->post('/en/track', [
            'order_number' => 'ORD-SECRET',
            'email' => 'wrong@example.com',
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('result.found', false));
    });

    it('returns not found for unknown order number', function (): void {
        $this->post('/en/track', [
            'order_number' => 'ORD-DOESNOTEXIST',
            'email' => 'any@example.com',
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('result.found', false));
    });

    it('validates required fields', function (): void {
        $this->post('/en/track', [])
            ->assertSessionHasErrors(['order_number', 'email']);
    });

    it('generates carrier tracking url for known carriers', function (): void {
        Order::factory()->create([
            'order_number' => 'ORD-FEDEX',
            'guest_email' => 'track@example.com',
            'user_id' => null,
            'carrier' => 'fedex',
            'tracking_number' => 'FX123456',
        ]);

        $this->post('/en/track', [
            'order_number' => 'ORD-FEDEX',
            'email' => 'track@example.com',
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('result.tracking_url', fn ($url) => str_contains($url, 'fedex.com') && str_contains($url, 'FX123456'))
            );
    });
});
