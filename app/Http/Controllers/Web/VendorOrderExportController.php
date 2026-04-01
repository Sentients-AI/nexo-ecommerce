<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class VendorOrderExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $statusFilter = $request->query('status');

        $orders = Order::query()
            ->with(['user:id,name,email', 'items:id,order_id,quantity'])
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->oldest()
            ->get(['id', 'order_number', 'status', 'subtotal_cents', 'discount_cents', 'shipping_cost_cents', 'total_cents', 'created_at', 'user_id', 'guest_email', 'guest_name']);

        $filename = 'orders-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($orders): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Order Number',
                'Status',
                'Customer Name',
                'Customer Email',
                'Items',
                'Subtotal',
                'Discount',
                'Shipping',
                'Total',
                'Date',
            ]);

            foreach ($orders as $order) {
                $name = $order->user?->name ?? $order->guest_name ?? '—';
                $email = $order->user?->email ?? $order->guest_email ?? '—';

                fputcsv($handle, [
                    $order->order_number,
                    $order->status->value,
                    $name,
                    $email,
                    $order->items->sum('quantity'),
                    number_format($order->subtotal_cents / 100, 2),
                    number_format($order->discount_cents / 100, 2),
                    number_format($order->shipping_cost_cents / 100, 2),
                    number_format($order->total_cents / 100, 2),
                    $order->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
