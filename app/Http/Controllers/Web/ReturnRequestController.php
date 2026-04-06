<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Order\Models\Order;
use App\Domain\Refund\Actions\CreateReturnRequestAction;
use App\Domain\Refund\Enums\ReturnReason;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ReturnRequestController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $orderId = (int) $request->route('orderId');

        $order = Order::query()
            ->where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->with('items.product')
            ->firstOrFail();

        if (! $order->isRefundable()) {
            return redirect()->route('orders.show', ['orderId' => $order->id, 'locale' => app()->getLocale()])
                ->with('error', 'This order is not eligible for a return.');
        }

        $reasons = collect(ReturnReason::cases())->map(fn (ReturnReason $r) => [
            'value' => $r->value,
            'label' => $r->label(),
        ]);

        return Inertia::render('Refunds/ReturnRequest', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'currency' => $order->currency,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product?->name ?? 'Unknown',
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->price_cents_snapshot,
                    'total_cents' => $item->price_cents_snapshot * $item->quantity,
                ]),
            ],
            'reasons' => $reasons,
        ]);
    }

    public function store(Request $request, CreateReturnRequestAction $action): RedirectResponse
    {
        $orderId = (int) $request->route('orderId');

        $order = Order::query()
            ->where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validReasons = collect(ReturnReason::cases())->map->value->all();

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['required', 'string', 'in:'.implode(',', $validReasons)],
        ]);

        try {
            $action->execute($order, $request->user(), $validated['items'], $validated['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('orders.show', ['orderId' => $order->id, 'locale' => app()->getLocale()])
            ->with('success', 'Your return request has been submitted. We will review it shortly.');
    }
}
