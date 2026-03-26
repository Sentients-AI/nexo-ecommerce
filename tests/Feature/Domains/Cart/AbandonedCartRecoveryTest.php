<?php

declare(strict_types=1);

use App\Console\Commands\SendAbandonedCartRecoveryCommand;
use App\Domain\Cart\Actions\SendAbandonedCartRecoveryEmailsAction;
use App\Domain\Cart\Models\Cart;
use App\Domain\User\Models\User;
use App\Notifications\AbandonedCartNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('SendAbandonedCartRecoveryEmailsAction', function () {
    it('sends email to user with abandoned cart', function () {
        Notification::fake();

        $user = User::factory()->create();
        Cart::factory()
            ->for($user)
            ->withItems(2)
            ->create(['updated_at' => now()->subHours(25)]);

        $action = app(SendAbandonedCartRecoveryEmailsAction::class);
        $sent = $action->execute(idleHours: 24);

        expect($sent)->toBe(1);
        Notification::assertSentTo($user, AbandonedCartNotification::class);
    });

    it('does not send email to cart not idle long enough', function () {
        Notification::fake();

        $user = User::factory()->create();
        Cart::factory()
            ->for($user)
            ->withItems(1)
            ->create(['updated_at' => now()->subHours(10)]);

        $sent = app(SendAbandonedCartRecoveryEmailsAction::class)->execute(idleHours: 24);

        expect($sent)->toBe(0);
        Notification::assertNothingSent();
    });

    it('does not send email to completed carts', function () {
        Notification::fake();

        $user = User::factory()->create();
        Cart::factory()
            ->for($user)
            ->withItems(1)
            ->create(['updated_at' => now()->subHours(25), 'completed_at' => now()]);

        $sent = app(SendAbandonedCartRecoveryEmailsAction::class)->execute(idleHours: 24);

        expect($sent)->toBe(0);
        Notification::assertNothingSent();
    });

    it('does not send email to empty carts', function () {
        Notification::fake();

        $user = User::factory()->create();
        Cart::factory()
            ->for($user)
            ->create(['updated_at' => now()->subHours(25)]);

        $sent = app(SendAbandonedCartRecoveryEmailsAction::class)->execute(idleHours: 24);

        expect($sent)->toBe(0);
        Notification::assertNothingSent();
    });

    it('does not send email to guest carts (no user_id)', function () {
        Notification::fake();

        Cart::factory()
            ->withItems(1)
            ->create(['user_id' => null, 'session_id' => 'guest-session', 'updated_at' => now()->subHours(25)]);

        $sent = app(SendAbandonedCartRecoveryEmailsAction::class)->execute(idleHours: 24);

        expect($sent)->toBe(0);
        Notification::assertNothingSent();
    });

    it('does not resend email if recovery_email_sent_at is already set', function () {
        Notification::fake();

        $user = User::factory()->create();
        Cart::factory()
            ->for($user)
            ->withItems(1)
            ->create([
                'updated_at' => now()->subHours(25),
                'recovery_email_sent_at' => now()->subHours(2),
            ]);

        $sent = app(SendAbandonedCartRecoveryEmailsAction::class)->execute(idleHours: 24);

        expect($sent)->toBe(0);
        Notification::assertNothingSent();
    });

    it('marks cart with recovery_email_sent_at after sending', function () {
        Notification::fake();

        $user = User::factory()->create();
        $cart = Cart::factory()
            ->for($user)
            ->withItems(1)
            ->create(['updated_at' => now()->subHours(25)]);

        app(SendAbandonedCartRecoveryEmailsAction::class)->execute(idleHours: 24);

        expect($cart->fresh()->recovery_email_sent_at)->not->toBeNull();
    });

    it('handles multiple eligible carts', function () {
        Notification::fake();

        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            Cart::factory()
                ->for($user)
                ->withItems(1)
                ->create(['updated_at' => now()->subHours(25)]);
        }

        $sent = app(SendAbandonedCartRecoveryEmailsAction::class)->execute(idleHours: 24);

        expect($sent)->toBe(3);
        foreach ($users as $user) {
            Notification::assertSentTo($user, AbandonedCartNotification::class);
        }
    });
});

describe('SendAbandonedCartRecoveryCommand', function () {
    it('runs and reports count', function () {
        Notification::fake();

        $user = User::factory()->create();
        Cart::factory()
            ->for($user)
            ->withItems(1)
            ->create(['updated_at' => now()->subHours(25)]);

        $this->artisan(SendAbandonedCartRecoveryCommand::class)
            ->expectsOutputToContain('Sent 1 abandoned cart recovery email(s).')
            ->assertExitCode(0);
    });

    it('accepts custom --hours option', function () {
        Notification::fake();

        $user = User::factory()->create();
        Cart::factory()
            ->for($user)
            ->withItems(1)
            ->create(['updated_at' => now()->subHours(5)]);

        $this->artisan(SendAbandonedCartRecoveryCommand::class, ['--hours' => 4])
            ->expectsOutputToContain('Sent 1 abandoned cart recovery email(s).')
            ->assertExitCode(0);

        Notification::assertSentTo($user, AbandonedCartNotification::class);
    });
});
