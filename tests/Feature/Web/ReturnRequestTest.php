<?php

declare(strict_types=1);

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Refund\Actions\ApproveReturnRequestAction;
use App\Domain\Refund\Actions\CreateReturnRequestAction;
use App\Domain\Refund\Actions\RejectReturnRequestAction;
use App\Domain\Refund\Enums\ReturnReason;
use App\Domain\Refund\Enums\ReturnStatus;
use App\Domain\Refund\Models\ReturnRequest;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

// ── Helper ──────────────────────────────────────────────────────────────────

function makePaidOrder(User $user): array
{
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::Paid,
        'subtotal_cents' => 10000,
        'total_cents' => 11000,
    ]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'price_cents_snapshot' => 5000,
        'quantity' => 2,
    ]);

    return [$order, $item];
}

// ── CreateReturnRequestAction ────────────────────────────────────────────────

describe('CreateReturnRequestAction', function () {
    it('creates a return request with items', function () {
        $user = $this->actingAsUserInTenant();
        [$order, $item] = makePaidOrder($user);

        $action = app(CreateReturnRequestAction::class);
        $returnRequest = $action->execute($order, $user, [
            ['order_item_id' => $item->id, 'quantity' => 1, 'reason' => ReturnReason::Defective->value],
        ], 'Item arrived cracked.');

        expect($returnRequest->status)->toBe(ReturnStatus::Pending)
            ->and($returnRequest->notes)->toBe('Item arrived cracked.')
            ->and($returnRequest->items)->toHaveCount(1)
            ->and($returnRequest->items->first()->reason)->toBe(ReturnReason::Defective);
    });

    it('throws if order is not refundable', function () {
        $user = $this->actingAsUserInTenant();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Cancelled]);
        $item = OrderItem::factory()->create(['order_id' => $order->id]);

        $action = app(CreateReturnRequestAction::class);

        expect(fn () => $action->execute($order, $user, [
            ['order_item_id' => $item->id, 'quantity' => 1, 'reason' => ReturnReason::WrongItem->value],
        ], null))->toThrow(DomainException::class, 'not eligible for a return');
    });

    it('throws if a pending return already exists for the order', function () {
        $user = $this->actingAsUserInTenant();
        [$order, $item] = makePaidOrder($user);
        ReturnRequest::factory()->forTenant($this->tenant)->create(['order_id' => $order->id, 'user_id' => $user->id]);

        $action = app(CreateReturnRequestAction::class);

        expect(fn () => $action->execute($order, $user, [
            ['order_item_id' => $item->id, 'quantity' => 1, 'reason' => ReturnReason::ChangedMind->value],
        ], null))->toThrow(DomainException::class, 'already in progress');
    });
});

// ── ApproveReturnRequestAction ───────────────────────────────────────────────

describe('ApproveReturnRequestAction', function () {
    it('approves the return, issues a refund, and updates status', function () {
        $user = $this->actingAsUserInTenant();
        $admin = $this->actingAsUserInTenant();
        [$order, $item] = makePaidOrder($user);

        $returnRequest = ReturnRequest::factory()->forTenant($this->tenant)->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);
        $returnRequest->items()->create([
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason' => ReturnReason::Defective->value,
        ]);

        $action = app(ApproveReturnRequestAction::class);
        $result = $action->execute($returnRequest, $admin, 'Approved — defect confirmed.');

        expect($result->status)->toBe(ReturnStatus::Approved)
            ->and($result->refund_id)->not->toBeNull()
            ->and($result->reviewed_by)->toBe($admin->id)
            ->and($result->admin_notes)->toBe('Approved — defect confirmed.');
    });

    it('throws if return request is not pending', function () {
        $user = $this->actingAsUserInTenant();
        $admin = $this->actingAsUserInTenant();
        [$order] = makePaidOrder($user);

        $returnRequest = ReturnRequest::factory()->forTenant($this->tenant)->approved()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        $action = app(ApproveReturnRequestAction::class);

        expect(fn () => $action->execute($returnRequest, $admin, null))
            ->toThrow(DomainException::class, 'Only pending');
    });
});

// ── RejectReturnRequestAction ────────────────────────────────────────────────

describe('RejectReturnRequestAction', function () {
    it('rejects the return request', function () {
        $user = $this->actingAsUserInTenant();
        $admin = $this->actingAsUserInTenant();
        [$order] = makePaidOrder($user);

        $returnRequest = ReturnRequest::factory()->forTenant($this->tenant)->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        $action = app(RejectReturnRequestAction::class);
        $result = $action->execute($returnRequest, $admin, 'Outside return window.');

        expect($result->status)->toBe(ReturnStatus::Rejected)
            ->and($result->admin_notes)->toBe('Outside return window.')
            ->and($result->reviewed_by)->toBe($admin->id);
    });
});

// ── HTTP: customer return form ───────────────────────────────────────────────

describe('GET /orders/{orderId}/return', function () {
    it('renders the return request form for an eligible order', function () {
        $user = $this->actingAsUserInTenant();
        [$order] = makePaidOrder($user);

        $this->actingAs($user)
            ->get(route('returns.create', ['orderId' => $order->id, 'locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Refunds/ReturnRequest')
                ->has('order')
                ->has('reasons')
            );
    });

    it('redirects if order belongs to another user', function () {
        $user = $this->actingAsUserInTenant();
        $other = $this->actingAsUserInTenant();
        [$order] = makePaidOrder($other);

        $this->actingAs($user)
            ->get(route('returns.create', ['orderId' => $order->id, 'locale' => 'en']))
            ->assertNotFound();
    });

    it('redirects if order is not refundable', function () {
        $user = $this->actingAsUserInTenant();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Cancelled]);

        $this->actingAs($user)
            ->get(route('returns.create', ['orderId' => $order->id, 'locale' => 'en']))
            ->assertRedirect();
    });
});

describe('POST /orders/{orderId}/return', function () {
    it('creates a return request with valid data', function () {
        $user = $this->actingAsUserInTenant();
        [$order, $item] = makePaidOrder($user);

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('returns.store', ['orderId' => $order->id, 'locale' => 'en']), [
                'items' => [
                    ['order_item_id' => $item->id, 'quantity' => 1, 'reason' => 'defective'],
                ],
                'notes' => 'Broken on arrival',
            ])
            ->assertRedirect();

        expect(ReturnRequest::query()->where('order_id', $order->id)->exists())->toBeTrue();
    });

    it('validates that at least one item is provided', function () {
        $user = $this->actingAsUserInTenant();
        [$order] = makePaidOrder($user);

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('returns.store', ['orderId' => $order->id, 'locale' => 'en']), [
                'items' => [],
            ])
            ->assertSessionHasErrors('items');
    });

    it('validates reason is required per item', function () {
        $user = $this->actingAsUserInTenant();
        [$order, $item] = makePaidOrder($user);

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('returns.store', ['orderId' => $order->id, 'locale' => 'en']), [
                'items' => [
                    ['order_item_id' => $item->id, 'quantity' => 1, 'reason' => ''],
                ],
            ])
            ->assertSessionHasErrors('items.0.reason');
    });
});

// ── HTTP: vendor returns management ─────────────────────────────────────────

describe('GET /vendor/returns', function () {
    it('renders the vendor returns page', function () {
        $this->actingAsUserInTenant();

        $this->get(route('vendor.returns.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/Returns'));
    });

    it('redirects guests to login', function () {
        $this->get(route('vendor.returns.index'))->assertRedirect();
    });
});

describe('PATCH /vendor/returns/{return}/approve', function () {
    it('approves a pending return request', function () {
        $admin = $this->actingAsUserInTenant();
        $user = $this->actingAsUserInTenant();
        [$order, $item] = makePaidOrder($user);

        $returnRequest = ReturnRequest::factory()->forTenant($this->tenant)->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);
        $returnRequest->items()->create([
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason' => ReturnReason::WrongItem->value,
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patch(route('vendor.returns.approve', $returnRequest), ['admin_notes' => 'Confirmed.'])
            ->assertRedirect();

        expect(ReturnRequest::query()->find($returnRequest->id)->status)->toBe(ReturnStatus::Approved);
    });
});

describe('PATCH /vendor/returns/{return}/reject', function () {
    it('rejects a pending return request', function () {
        $admin = $this->actingAsUserInTenant();
        $user = $this->actingAsUserInTenant();
        [$order] = makePaidOrder($user);

        $returnRequest = ReturnRequest::factory()->forTenant($this->tenant)->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patch(route('vendor.returns.reject', $returnRequest), ['admin_notes' => 'Outside window.'])
            ->assertRedirect();

        expect(ReturnRequest::query()->find($returnRequest->id)->status)->toBe(ReturnStatus::Rejected);
    });
});
