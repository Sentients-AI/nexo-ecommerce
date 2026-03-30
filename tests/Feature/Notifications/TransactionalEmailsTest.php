<?php

declare(strict_types=1);

use App\Domain\Order\Events\OrderCreated;
use App\Domain\Order\Listeners\SendOrderConfirmationEmail;
use App\Domain\Order\Models\Order;
use App\Domain\User\Actions\RegisterUser;
use App\Domain\User\DTOs\RegisterUserData;
use App\Domain\User\Models\User;
use App\Notifications\LoyaltyPointsEarnedNotification;
use App\Notifications\OrderConfirmedNotification;
use App\Notifications\RefundApprovedNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    Notification::fake();
    $this->user = User::factory()->create();
});

describe('OrderConfirmedNotification', function () {
    it('sends via mail, database, and broadcast channels', function () {
        $order = Order::factory()->create(['user_id' => $this->user->id]);
        $notification = new OrderConfirmedNotification($order);

        expect($notification->via($this->user))
            ->toContain('mail')
            ->toContain('database')
            ->toContain('broadcast');
    });

    it('has correct subject and order number in mail', function () {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-TEST01',
        ]);
        $notification = new OrderConfirmedNotification($order);
        $mail = $notification->toMail($this->user);

        expect($mail->subject)->toContain('ORD-TEST01');
    });

    it('toArray includes correct type and order data', function () {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-TEST01',
        ]);
        $notification = new OrderConfirmedNotification($order);
        $data = $notification->toArray($this->user);

        expect($data['type'])->toBe('order_confirmed')
            ->and($data['order_number'])->toBe('ORD-TEST01')
            ->and($data['url'])->toContain("/orders/{$order->id}");
    });

    it('listener sends confirmation email on OrderCreated event', function () {
        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $listener = new SendOrderConfirmationEmail;
        $listener->handle(new OrderCreated(
            orderId: $order->id,
            userId: $this->user->id,
            totalCents: $order->total_cents,
            currency: $order->currency,
        ));

        Notification::assertSentTo($this->user, OrderConfirmedNotification::class);
    });

    it('listener does nothing when order does not exist', function () {
        $listener = new SendOrderConfirmationEmail;
        $listener->handle(new OrderCreated(
            orderId: 999999,
            userId: $this->user->id,
            totalCents: 1000,
            currency: 'usd',
        ));

        Notification::assertNothingSent();
    });
});

describe('WelcomeNotification', function () {
    it('sends via mail only', function () {
        $notification = new WelcomeNotification;

        expect($notification->via($this->user))->toBe(['mail']);
    });

    it('mail includes app name in subject', function () {
        $notification = new WelcomeNotification;
        $mail = $notification->toMail($this->user);

        expect($mail->subject)->toContain(config('app.name'));
    });

    it('RegisterUser sends welcome notification', function () {
        $roleId = $this->user->role_id;

        $action = new RegisterUser;
        $newUser = $action->execute(new RegisterUserData(
            name: 'Jane Doe',
            email: 'jane@example.com',
            password: 'password',
            roleId: $roleId,
        ));

        Notification::assertSentTo($newUser, WelcomeNotification::class);
    });
});

describe('RefundApprovedNotification mail channel', function () {
    it('now includes mail channel', function () {
        $notification = new RefundApprovedNotification(orderId: 1, amountCents: 5000, currency: 'usd');

        expect($notification->via($this->user))->toContain('mail');
    });

    it('toMail has correct subject and amount', function () {
        $notification = new RefundApprovedNotification(orderId: 1, amountCents: 5000, currency: 'usd');
        $mail = $notification->toMail($this->user);

        expect($mail->subject)->toContain('Refund')
            ->and(collect($mail->introLines)->implode(' '))->toContain('50.00');
    });
});

describe('LoyaltyPointsEarnedNotification mail channel', function () {
    it('now includes mail channel', function () {
        $notification = new LoyaltyPointsEarnedNotification(points: 100, newBalance: 350);

        expect($notification->via($this->user))->toContain('mail');
    });

    it('toMail contains points and balance', function () {
        $notification = new LoyaltyPointsEarnedNotification(points: 100, newBalance: 350);
        $mail = $notification->toMail($this->user);

        $lines = collect($mail->introLines)->implode(' ');

        expect($mail->subject)->toContain('100')
            ->and($lines)->toContain('100')
            ->and($lines)->toContain('350');
    });
});
