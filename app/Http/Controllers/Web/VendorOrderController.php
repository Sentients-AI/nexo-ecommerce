<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Order\Actions\ShipOrderAction;
use App\Domain\Order\DTOs\ShipOrderData;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

final class VendorOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $statusFilter = $request->query('status');

        $orders = Order::query()
            ->with(['user:id,name,email', 'items:id,order_id,product_id,variant_id,quantity,price_cents_snapshot', 'items.product:id,name,slug'])
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->latest()
            ->paginate(20)
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'subtotal_cents' => $order->subtotal_cents,
                'discount_cents' => $order->discount_cents,
                'shipping_cost_cents' => $order->shipping_cost_cents,
                'total_cents' => $order->total_cents,
                'items_count' => $order->items->count(),
                'customer' => $order->user ? [
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ] : null,
                'created_at' => $order->created_at->toIso8601String(),
            ]);

        $statusCounts = Order::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $statuses = collect(OrderStatus::cases())->map(fn (OrderStatus $s) => [
            'value' => $s->value,
            'label' => ucfirst(str_replace('_', ' ', $s->value)),
            'count' => $statusCounts->get($s->value, 0),
        ]);

        return Inertia::render('Vendor/Orders', [
            'orders' => $orders,
            'statuses' => $statuses,
            'status_filter' => $statusFilter,
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', new Enum(OrderStatus::class)],
        ]);

        $order->update(['status' => $validated['status']]);

        return back()->with('success', 'Order status updated.');
    }

    public function shipOrder(Request $request, Order $order, ShipOrderAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'carrier' => ['required', 'string', 'max:100'],
            'tracking_number' => ['required', 'string', 'max:100'],
            'estimated_delivery_at' => ['nullable', 'date', 'after:today'],
        ]);

        try {
            $action->execute($order, ShipOrderData::fromRequest($validated));
        } catch (Exception $e) {
            return back()->withErrors(['ship' => $e->getMessage()]);
        }

        return back()->with('success', 'Order marked as shipped and customer notified.');
    }
}
