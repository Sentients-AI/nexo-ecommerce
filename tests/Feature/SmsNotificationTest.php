<?php

declare(strict_types=1);

use App\Domain\Order\Models\Order;
use App\Domain\User\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\OrderConfirmedNotification;
use App\Notifications\OrderShippedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function (): void {
    $this->actingAsUserInTenant();
});

describe('SMS channel inclusion in via()', function (): void {
    it('includes SMS channel when user has phone and sms enabled', function (): void {
        $user = User::factory()->create([
            'phone_number' => '+15551234567',
            'sms_notifications_enabled' => true,
        ]);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $notification = new OrderShippedNotification($order);
        $channels = $notification->via($user);

        expect($channels)->toContain(SmsChannel::class);
    });

    it('excludes SMS channel when user has no phone number', function (): void {
        $user = User::factory()->create([
            'phone_number' => null,
            'sms_notifications_enabled' => true,
        ]);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $notification = new OrderShippedNotification($order);
        $channels = $notification->via($user);

        expect($channels)->not->toContain(SmsChannel::class);
    });

    it('excludes SMS channel when user has not enabled SMS notifications', function (): void {
        $user = User::factory()->create([
            'phone_number' => '+15551234567',
            'sms_notifications_enabled' => false,
        ]);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $notification = new OrderShippedNotification($order);
        $channels = $notification->via($user);

        expect($channels)->not->toContain(SmsChannel::class);
    });
});

describe('SMS message content', function (): void {
    it('generates order shipped SMS message', function (): void {
        $user = User::factory()->create(['phone_number' => '+15551234567', 'sms_notifications_enabled' => true]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-SMSTEST',
            'carrier' => 'FedEx',
        ]);

        $notification = new OrderShippedNotification($order);
        $sms = $notification->toSms($user);

        expect($sms)->toContain('ORD-SMSTEST')
            ->and($sms)->toContain('FedEx');
    });

    it('generates order confirmed SMS message with total', function (): void {
        $user = User::factory()->create(['phone_number' => '+15551234567', 'sms_notifications_enabled' => true]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-CONFIRM',
            'total_cents' => 9999,
            'currency' => 'usd',
        ]);

        $notification = new OrderConfirmedNotification($order);
        $sms = $notification->toSms($user);

        expect($sms)->toContain('ORD-CONFIRM')
            ->and($sms)->toContain('99.99');
    });
});

describe('User SMS preferences', function (): void {
    it('saves phone number to user', function (): void {
        $user = User::factory()->create(['phone_number' => '+15559876543']);
        expect($user->phone_number)->toBe('+15559876543');
    });

    it('saves sms_notifications_enabled flag', function (): void {
        $user = User::factory()->create(['sms_notifications_enabled' => true]);
        expect($user->sms_notifications_enabled)->toBeTrue();
    });
});
