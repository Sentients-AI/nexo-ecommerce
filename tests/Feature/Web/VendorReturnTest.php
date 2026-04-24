<?php

declare(strict_types=1);

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Refund\Enums\ReturnReason;
use App\Domain\Refund\Enums\ReturnStatus;
use App\Domain\Refund\Models\ReturnRequest;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function (): void {
    $this->actingAsUserInTenant();
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

describe('Vendor returns index', function (): void {
    it('renders the returns page', function (): void {
        $this->get('/vendor/returns')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Returns')
                ->has('returns')
                ->has('status_filter')
                ->has('counts')
            );
    });

    it('filters returns by status', function (): void {
        $order = Order::factory()->create();
        $user = User::factory()->create();

        ReturnRequest::factory()->count(2)->create(['order_id' => $order->id, 'user_id' => $user->id]);
        ReturnRequest::factory()->approved()->count(1)->create(['order_id' => $order->id, 'user_id' => $user->id]);

        $this->get('/vendor/returns?status=pending')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('status_filter', 'pending')
                ->where('returns.total', 2)
            );
    });

    it('shows all returns when status=all', function (): void {
        $order = Order::factory()->create();
        $user = User::factory()->create();

        ReturnRequest::factory()->count(2)->create(['order_id' => $order->id, 'user_id' => $user->id]);
        ReturnRequest::factory()->rejected()->count(1)->create(['order_id' => $order->id, 'user_id' => $user->id]);

        $this->get('/vendor/returns?status=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('returns.total', 3)
            );
    });

    it('redirects guests to login', function (): void {
        auth()->logout();

        $this->get('/vendor/returns')->assertRedirect();
    });
});

// ---------------------------------------------------------------------------
// Approve
// ---------------------------------------------------------------------------

describe('Vendor return approve', function (): void {
    it('approves a pending return request', function (): void {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'total_cents' => 11000,
        ]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'price_cents_snapshot' => 5000,
            'quantity' => 2,
        ]);
        $returnRequest = ReturnRequest::factory()->forTenant($this->tenant)->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);
        $returnRequest->items()->create([
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason' => ReturnReason::Defective->value,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/returns/{$returnRequest->id}/approve")
            ->assertRedirect();

        expect($returnRequest->fresh()->status)->toBe(ReturnStatus::Approved);
    });

    it('saves admin_notes when approving', function (): void {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'total_cents' => 11000,
        ]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'price_cents_snapshot' => 5000,
            'quantity' => 2,
        ]);
        $returnRequest = ReturnRequest::factory()->forTenant($this->tenant)->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);
        $returnRequest->items()->create([
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason' => ReturnReason::Defective->value,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/returns/{$returnRequest->id}/approve", [
                'admin_notes' => 'Defect confirmed',
            ])
            ->assertRedirect();

        expect($returnRequest->fresh()->admin_notes)->toBe('Defect confirmed');
    });

    it('returns a session error when the return is not pending', function (): void {
        $order = Order::factory()->create();
        $user = User::factory()->create();
        $returnRequest = ReturnRequest::factory()->approved()->create(['order_id' => $order->id, 'user_id' => $user->id]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/returns/{$returnRequest->id}/approve")
            ->assertRedirect()
            ->assertSessionHas('error', 'Only pending return requests can be approved.');
    });

    it('returns 404 for a non-existent return request', function (): void {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch('/vendor/returns/99999/approve')
            ->assertNotFound();
    });
});

// ---------------------------------------------------------------------------
// Reject
// ---------------------------------------------------------------------------

describe('Vendor return reject', function (): void {
    it('rejects a pending return request', function (): void {
        $order = Order::factory()->create();
        $user = User::factory()->create();
        $returnRequest = ReturnRequest::factory()->create(['order_id' => $order->id, 'user_id' => $user->id]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/returns/{$returnRequest->id}/reject")
            ->assertRedirect();

        expect($returnRequest->fresh()->status)->toBe(ReturnStatus::Rejected);
    });

    it('saves admin_notes when rejecting', function (): void {
        $order = Order::factory()->create();
        $user = User::factory()->create();
        $returnRequest = ReturnRequest::factory()->create(['order_id' => $order->id, 'user_id' => $user->id]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/returns/{$returnRequest->id}/reject", [
                'admin_notes' => 'Outside return window',
            ])
            ->assertRedirect();

        expect($returnRequest->fresh()->admin_notes)->toBe('Outside return window');
    });

    it('returns a session error when the return is not pending', function (): void {
        $order = Order::factory()->create();
        $user = User::factory()->create();
        $returnRequest = ReturnRequest::factory()->rejected()->create(['order_id' => $order->id, 'user_id' => $user->id]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/returns/{$returnRequest->id}/reject")
            ->assertRedirect()
            ->assertSessionHas('error', 'Only pending return requests can be rejected.');
    });

    it('returns 404 for a non-existent return request', function (): void {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch('/vendor/returns/99999/reject')
            ->assertNotFound();
    });
});
