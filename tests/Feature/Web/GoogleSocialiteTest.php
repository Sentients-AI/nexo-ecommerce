<?php

declare(strict_types=1);

use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Google Socialite', function () {
    it('redirects to google oauth', function () {
        Socialite::shouldReceive('driver->redirect')->andReturn(
            redirect('https://accounts.google.com/o/oauth2/auth?fake=1')
        );

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirectContains('accounts.google.com');
    });

    it('creates a new user on first google login', function () {
        $socialiteUser = (new SocialiteUser)->map([
            'id' => 'google-123',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'avatar' => null,
        ]);
        $socialiteUser->token = 'fake-token';
        $socialiteUser->refreshToken = null;
        $socialiteUser->expiresIn = 3600;

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/');
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'google_id' => 'google-123',
        ]);
    });

    it('logs in an existing user by google_id', function () {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'google_id' => 'google-123',
            'tenant_id' => null,
        ]);

        $socialiteUser = (new SocialiteUser)->map([
            'id' => 'google-123',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'avatar' => null,
        ]);
        $socialiteUser->token = 'fake-token';
        $socialiteUser->refreshToken = null;
        $socialiteUser->expiresIn = 3600;

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
    });

    it('links google_id to existing account with matching email', function () {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'google_id' => null,
            'tenant_id' => null,
        ]);

        $socialiteUser = (new SocialiteUser)->map([
            'id' => 'google-456',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'avatar' => null,
        ]);
        $socialiteUser->token = 'fake-token';
        $socialiteUser->refreshToken = null;
        $socialiteUser->expiresIn = 3600;

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-456',
        ]);
    });

});
