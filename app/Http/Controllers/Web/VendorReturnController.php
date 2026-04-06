<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Refund\Actions\ApproveReturnRequestAction;
use App\Domain\Refund\Actions\RejectReturnRequestAction;
use App\Domain\Refund\Models\ReturnRequest;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class VendorReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'pending');

        $returns = ReturnRequest::query()
            ->with([
                'order:id,order_number,total_cents,created_at',
                'user:id,name,email',
                'items.orderItem.product:id,name',
                'reviewer:id,name',
            ])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->through(fn (ReturnRequest $r) => [
                'id' => $r->id,
                'status' => $r->status->value,
                'notes' => $r->notes,
                'admin_notes' => $r->admin_notes,
                'reviewed_at' => $r->reviewed_at?->toDateString(),
                'reviewer' => $r->reviewer?->name,
                'refund_id' => $r->refund_id,
                'created_at' => $r->created_at->toDateString(),
                'order' => [
                    'id' => $r->order->id,
                    'order_number' => $r->order->order_number,
                    'total_cents' => $r->order->total_cents,
                ],
                'customer' => [
                    'name' => $r->user->name,
                    'email' => $r->user->email,
                ],
                'items' => $r->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->orderItem->product?->name ?? 'Unknown',
                    'quantity' => $item->quantity,
                    'reason' => $item->reason->value,
                    'reason_label' => $item->reason->label(),
                    'unit_price_cents' => $item->orderItem->price_cents_snapshot,
                    'subtotal_cents' => $item->orderItem->price_cents_snapshot * $item->quantity,
                ]),
                'total_refund_cents' => $r->totalRefundCents(),
            ]);

        $counts = ReturnRequest::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($c) => (int) $c);

        return Inertia::render('Vendor/Returns', [
            'returns' => $returns,
            'status_filter' => $status,
            'counts' => $counts,
        ]);
    }

    public function approve(Request $request, ReturnRequest $return, ApproveReturnRequestAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $action->execute($return, $request->user(), $validated['admin_notes'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return request approved and refund issued.');
    }

    public function reject(Request $request, ReturnRequest $return, RejectReturnRequestAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $action->execute($return, $request->user(), $validated['admin_notes'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return request rejected.');
    }
}
