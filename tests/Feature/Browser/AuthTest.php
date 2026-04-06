<?php

declare(strict_types=1);

use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Login', function () {
    it('loads the login page', function () {
        $this->get('/en/login')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Auth/Login'));
    });

    it('redirects authenticated users away from login page', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/en/login')
            ->assertRedirect();
    });

    it('logs in with valid credentials', function () {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $this->post('/en/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    });

    it('fails login with wrong password', function () {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->post('/en/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('fails login with non-existent email', function () {
        $this->post('/en/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ])->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('validates required fields on login', function () {
        $this->post('/en/login', [])
            ->assertSessionHasErrors(['email', 'password']);
    });

    it('validates email format on login', function () {
        $this->post('/en/login', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');
    });
});

describe('Registration', function () {
    it('loads the register page', function () {
        $this->get('/en/register')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Auth/Register'));
    });

    it('redirects authenticated users away from register page', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/en/register')
            ->assertRedirect();
    });

    it('registers a new user with valid data', function () {
        $this->post('/en/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas(User::class, [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    });

    it('fails registration with duplicate email', function () {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/en/register', [
            'name' => 'Another User',
            'email' => 'taken@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('fails registration when passwords do not match', function () {
        $this->post('/en/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'DifferentPass1!',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    });

    it('validates required fields on registration', function () {
        $this->post('/en/register', [])
            ->assertSessionHasErrors(['name', 'email', 'password']);
    });

    it('validates name max length on registration', function () {
        $this->post('/en/register', [
            'name' => str_repeat('a', 256),
            'email' => 'jane@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertSessionHasErrors('name');
    });
});

describe('Logout', function () {
    it('logs out an authenticated user', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/en/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    });

    it('redirects guests away from logout', function () {
        $this->post('/en/logout')
            ->assertRedirect();
    });
});
