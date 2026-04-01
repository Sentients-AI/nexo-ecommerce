<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class VendorDashboardController extends Controller
{
    public function index(): Response
    {
        $revenueToday = (int) Order::query()
            ->where('status', '!=', 'cancelled')
            ->whereDate('created_at', today())
            ->sum('total_cents');

        $revenueYesterday = (int) Order::query()
            ->where('status', '!=', 'cancelled')
            ->whereDate('created_at', today()->subDay())
            ->sum('total_cents');

        $revenueThisMonth = (int) Order::query()
            ->where('status', '!=', 'cancelled')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_cents');

        $revenueLastMonth = (int) Order::query()
            ->where('status', '!=', 'cancelled')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('total_cents');

        $ordersToday = Order::query()
            ->whereDate('created_at', today())
            ->count();

        $ordersYesterday = Order::query()
            ->whereDate('created_at', today()->subDay())
            ->count();

        $pendingOrders = Order::query()
            ->where('status', 'pending')
            ->count();

        $totalProducts = Product::query()
            ->where('is_active', true)
            ->count();

        $recentOrders = Order::query()
            ->with(['user:id,name,email'])
            ->latest()
            ->limit(10)
            ->get(['id', 'order_number', 'status', 'total_cents', 'created_at', 'user_id']);

        $lowStockProducts = Product::query()
            ->whereHas('stock', fn ($q) => $q->where('quantity_available', '<=', 5)->where('quantity_available', '>', 0))
            ->with('stock:id,product_id,quantity_available')
            ->limit(8)
            ->get(['id', 'name', 'slug']);

        $chartData = collect(range(6, 0))->map(function (int $daysAgo): array {
            $date = now()->subDays($daysAgo);

            $revenue = Order::query()
                ->where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [
                    $date->copy()->startOfDay(),
                    $date->copy()->endOfDay(),
                ])
                ->sum('total_cents');

            return [
                'date' => $date->format('M d'),
                'revenue' => (float) ($revenue / 100),
            ];
        })->values()->all();

        return Inertia::render('Vendor/Dashboard', [
            'stats' => [
                'revenue_today' => $revenueToday,
                'revenue_yesterday' => $revenueYesterday,
                'revenue_this_month' => $revenueThisMonth,
                'revenue_last_month' => $revenueLastMonth,
                'orders_today' => $ordersToday,
                'orders_yesterday' => $ordersYesterday,
                'pending_orders' => $pendingOrders,
                'total_products' => $totalProducts,
            ],
            'recent_orders' => $recentOrders,
            'low_stock_products' => $lowStockProducts,
            'chart_data' => $chartData,
        ]);
    }
}
