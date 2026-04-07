<?php

declare(strict_types=1);

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use App\Filament\Pages\FraudDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Role::factory()->create(['name' => 'super_admin']);
    Role::factory()->create(['name' => 'admin']);
    Role::factory()->create(['name' => 'customer']);

    $this->tenant = Tenant::factory()->create();
    Context::add('tenant_id', $this->tenant->id);
});

function makeSuperAdmin(): User
{
    $role = Role::where('name', 'super_admin')->first();

    return User::factory()->create(['tenant_id' => null, 'role_id' => $role->id]);
}

function makeAdmin(Tenant $tenant): User
{
    $role = Role::where('name', 'admin')->first();

    return User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $role->id]);
}

function makeCustomer(Tenant $tenant): User
{
    $role = Role::where('name', 'customer')->first();

    return User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $role->id]);
}

// ── Access Control ────────────────────────────────────────────────────────────

describe('FraudDashboard access', function (): void {
    it('allows super admin to access', function (): void {
        $this->actingAs(makeSuperAdmin());

        expect(FraudDashboard::canAccess())->toBeTrue();
    });

    it('allows admin to access', function (): void {
        $this->actingAs(makeAdmin($this->tenant));

        expect(FraudDashboard::canAccess())->toBeTrue();
    });

    it('denies customers', function (): void {
        $this->actingAs(makeCustomer($this->tenant));

        expect(FraudDashboard::canAccess())->toBeFalse();
    });

    it('denies unauthenticated users', function (): void {
        expect(FraudDashboard::canAccess())->toBeFalse();
    });
});

// ── Navigation Badge ──────────────────────────────────────────────────────────

describe('FraudDashboard::getNavigationBadge', function (): void {
    it('returns null when no flagged orders or users exist', function (): void {
        expect(FraudDashboard::getNavigationBadge())->toBeNull();
    });

    it('returns count when flagged orders exist', function (): void {
        $newUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDay(),
        ]);

        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $newUser->id,
            'total_cents' => 100_000,
            'status' => OrderStatus::Paid,
        ]);

        expect(FraudDashboard::getNavigationBadge())->toBe('1');
    });

    it('returns null badge when count is zero', function (): void {
        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'total_cents' => 1000,
            'status' => OrderStatus::Paid,
        ]);

        expect(FraudDashboard::getNavigationBadge())->toBeNull();
    });
});

// ── Flagged Orders Logic ──────────────────────────────────────────────────────

describe('flagged order signals', function (): void {
    it('flags orders with multiple payment attempts', function (): void {
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => OrderStatus::Paid,
        ]);

        PaymentIntent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'attempts' => 4,
            'status' => PaymentStatus::Succeeded,
        ]);

        $lookback = now()->subDays(config('fraud.lookback_days', 7));
        $threshold = (int) config('fraud.payment_attempts_threshold', 3);

        $flagged = Order::query()
            ->where('created_at', '>=', $lookback)
            ->whereHas('paymentIntent', fn ($pi) => $pi->where('attempts', '>=', $threshold))
            ->count();

        expect($flagged)->toBe(1);
    });

    it('flags high-value orders from new users', function (): void {
        $newUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDay(),
        ]);

        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $newUser->id,
            'total_cents' => 100_000,
            'status' => OrderStatus::Paid,
        ]);

        $lookback = now()->subDays(config('fraud.lookback_days', 7));
        $highValue = (int) config('fraud.high_value_threshold_cents', 50000);
        $newUserDays = (int) config('fraud.new_user_days', 7);

        $flagged = Order::query()
            ->where('created_at', '>=', $lookback)
            ->where('total_cents', '>=', $highValue)
            ->whereHas('user', fn ($u) => $u->where('created_at', '>=', now()->subDays($newUserDays)))
            ->count();

        expect($flagged)->toBe(1);
    });

    it('flags high-value guest orders', function (): void {
        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
            'guest_email' => 'guest@example.com',
            'guest_name' => 'Guest User',
            'total_cents' => 100_000,
            'status' => OrderStatus::Paid,
        ]);

        $lookback = now()->subDays(config('fraud.lookback_days', 7));
        $highValue = (int) config('fraud.high_value_threshold_cents', 50000);

        $flagged = Order::query()
            ->where('created_at', '>=', $lookback)
            ->whereNotNull('guest_email')
            ->where('total_cents', '>=', $highValue)
            ->count();

        expect($flagged)->toBe(1);
    });

    it('does not flag normal orders from established users', function (): void {
        $establishedUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subMonths(3),
        ]);

        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $establishedUser->id,
            'total_cents' => 1500,
            'status' => OrderStatus::Paid,
        ]);

        $lookback = now()->subDays(config('fraud.lookback_days', 7));
        $highValue = (int) config('fraud.high_value_threshold_cents', 50000);
        $newUserDays = (int) config('fraud.new_user_days', 7);
        $attemptsThreshold = (int) config('fraud.payment_attempts_threshold', 3);

        $flagged = Order::query()
            ->where('created_at', '>=', $lookback)
            ->where(function ($q) use ($attemptsThreshold, $highValue, $newUserDays): void {
                $q->whereHas('paymentIntent', fn ($pi) => $pi->where('attempts', '>=', $attemptsThreshold))
                    ->orWhere(function ($sub) use ($highValue, $newUserDays): void {
                        $sub->where('total_cents', '>=', $highValue)
                            ->whereHas('user', fn ($u) => $u->where('created_at', '>=', now()->subDays($newUserDays)));
                    })
                    ->orWhere(function ($sub) use ($highValue): void {
                        $sub->whereNotNull('guest_email')->where('total_cents', '>=', $highValue);
                    });
            })
            ->count();

        expect($flagged)->toBe(0);
    });
});

// ── Suspicious Users Logic ────────────────────────────────────────────────────

describe('suspicious user signals', function (): void {
    it('detects users with high velocity orders', function (): void {
        $user = makeCustomer($this->tenant);
        $velocityOrders = (int) config('fraud.velocity_orders', 3);
        $velocityWindowHours = (int) config('fraud.velocity_window_hours', 1);

        Order::factory()->count($velocityOrders)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'created_at' => now()->subMinutes(30),
        ]);

        $flagged = User::query()
            ->whereRaw(
                '(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND orders.created_at >= ?) >= ?',
                [now()->subHours($velocityWindowHours), $velocityOrders]
            )
            ->count();

        expect($flagged)->toBe(1);
    });

    it('detects users with high refund rate', function (): void {
        $user = makeCustomer($this->tenant);
        $highRefundRate = (float) config('fraud.high_refund_rate', 0.5);

        Order::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'refunded_amount_cents' => 1000,
        ]);

        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'refunded_amount_cents' => 0,
        ]);

        $flagged = User::query()
            ->whereRaw('(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) >= 2')
            ->whereRaw(
                '(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND refunded_amount_cents > 0) / (SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) >= ?',
                [$highRefundRate]
            )
            ->count();

        expect($flagged)->toBe(1);
    });
});
