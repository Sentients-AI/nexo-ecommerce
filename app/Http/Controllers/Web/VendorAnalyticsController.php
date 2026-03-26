<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class VendorAnalyticsController extends Controller
{
    public function index(): Response
    {
        // Revenue last 30 days (daily)
        $dailyRevenue = collect(range(29, 0))->map(function (int $daysAgo): array {
            $date = now()->subDays($daysAgo);

            $revenue = Order::query()
                ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Failed->value])
                ->whereBetween('created_at', [
                    $date->copy()->startOfDay(),
                    $date->copy()->endOfDay(),
                ])
                ->sum('total_cents');

            return [
                'date' => $date->format('M d'),
                'revenue' => (float) ($revenue / 100),
                'orders' => Order::query()
                    ->whereBetween('created_at', [
                        $date->copy()->startOfDay(),
                        $date->copy()->endOfDay(),
                    ])
                    ->count(),
            ];
        })->values()->all();

        // Top 10 products by revenue
        $topProducts = OrderItem::query()
            ->select('product_id', DB::raw('SUM(price_cents_snapshot * quantity) as revenue_cents'), DB::raw('SUM(quantity) as units_sold'))
            ->with('product:id,name,sku,slug')
            ->groupBy('product_id')
            ->orderByDesc('revenue_cents')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? 'Unknown',
                'product_sku' => $item->product?->sku ?? '',
                'revenue_cents' => (int) $item->revenue_cents,
                'units_sold' => (int) $item->units_sold,
            ]);

        // Revenue by month (last 6 months)
        $monthlyRevenue = collect(range(5, 0))->map(function (int $monthsAgo): array {
            $date = now()->subMonths($monthsAgo);

            $revenue = Order::query()
                ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Failed->value])
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('total_cents');

            $orderCount = Order::query()
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            return [
                'month' => $date->format('M Y'),
                'revenue' => (float) ($revenue / 100),
                'orders' => $orderCount,
            ];
        })->values()->all();

        // Order status distribution
        $statusDistribution = Order::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count) => (int) $count);

        // Summary stats
        $totalRevenue = Order::query()
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Failed->value])
            ->sum('total_cents');

        $totalOrders = Order::query()->count();

        $avgOrderValue = $totalOrders > 0
            ? (int) round($totalRevenue / $totalOrders)
            : 0;

        $revenueThisMonth = Order::query()
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Failed->value])
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('total_cents');

        $revenueLastMonth = Order::query()
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Failed->value])
            ->whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('total_cents');

        $monthGrowth = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : 0;

        return Inertia::render('Vendor/Analytics', [
            'daily_revenue' => $dailyRevenue,
            'monthly_revenue' => $monthlyRevenue,
            'top_products' => $topProducts,
            'status_distribution' => $statusDistribution,
            'stats' => [
                'total_revenue_cents' => (int) $totalRevenue,
                'total_orders' => $totalOrders,
                'avg_order_value_cents' => $avgOrderValue,
                'revenue_this_month_cents' => (int) $revenueThisMonth,
                'month_growth_percent' => $monthGrowth,
            ],
        ]);
    }
}
