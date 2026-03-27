<?php

declare(strict_types=1);

use App\Domain\User\Models\User;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $this->actingAsUserInTenant();
});

function sendTestNotification(User $user): DatabaseNotification
{
    Notification::fake();

    // Use the real DB channel to create the notification record
    $user->notify(new OrderStatusChangedNotification(
        orderId: 1,
        orderNumber: 'ORD-001',
        status: 'paid',
    ));

    // Force the sync notification to write to DB by using the real channel
    $notification = new OrderStatusChangedNotification(1, 'ORD-001', 'paid');

    return DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => OrderStatusChangedNotification::class,
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'data' => $notification->toArray($user),
        'read_at' => null,
    ]);
}

describe('Notifications index', function () {
    it('renders the notifications page', function () {
        $user = auth()->user();
        sendTestNotification($user);

        $this->get('/en/notifications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Notifications/Index')
                ->has('notifications')
                ->has('notifications.data', 1)
            );
    });

    it('guests are redirected to login', function () {
        auth()->logout();

        $this->get('/en/notifications')->assertRedirect();
    });
});

describe('Mark notification as read', function () {
    it('marks a notification as read', function () {
        $user = auth()->user();
        $notification = sendTestNotification($user);

        expect($notification->read_at)->toBeNull();

        $this->patch("/en/notifications/{$notification->id}/read")
            ->assertRedirect();

        expect(
            DatabaseNotification::query()->where('id', $notification->id)->value('read_at')
        )->not->toBeNull();
    });

    it('does not mark another user\'s notification as read', function () {
        $other = User::factory()->create();
        $notification = sendTestNotification($other);

        $this->patch("/en/notifications/{$notification->id}/read")
            ->assertRedirect();

        expect(
            DatabaseNotification::query()->where('id', $notification->id)->value('read_at')
        )->toBeNull();
    });
});

describe('Mark all notifications as read', function () {
    it('marks all unread notifications as read', function () {
        $user = auth()->user();
        sendTestNotification($user);
        sendTestNotification($user);

        $this->patch('/en/notifications/read-all')->assertRedirect();

        expect(
            DatabaseNotification::query()
                ->where('notifiable_id', $user->getKey())
                ->whereNull('read_at')
                ->count()
        )->toBe(0);
    });
});

describe('Delete notification', function () {
    it('deletes own notification', function () {
        $user = auth()->user();
        $notification = sendTestNotification($user);
        $id = $notification->id;

        $this->delete("/en/notifications/{$id}")
            ->assertRedirect();

        expect(
            DatabaseNotification::query()->where('id', $id)->exists()
        )->toBeFalse();
    });

    it('cannot delete another user\'s notification', function () {
        $other = User::factory()->create();
        $notification = sendTestNotification($other);
        $id = $notification->id;

        $this->delete("/en/notifications/{$id}")
            ->assertRedirect();

        expect(
            DatabaseNotification::query()->where('id', $id)->exists()
        )->toBeTrue();
    });
});
