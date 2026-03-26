<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class VendorCustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');

        $customers = User::query()
            ->whereNotNull('tenant_id')
            ->whereHas('orders')
            ->withCount('orders')
            ->withSum('orders', 'total_cents')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderByDesc('orders_sum_total_cents')
            ->paginate(25)
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'orders_count' => $u->orders_count,
                'total_spent_cents' => (int) ($u->orders_sum_total_cents ?? 0),
                'created_at' => $u->created_at->toIso8601String(),
            ]);

        $totalCustomers = User::query()
            ->whereNotNull('tenant_id')
            ->whereHas('orders')
            ->count();

        $newThisMonth = User::query()
            ->whereNotNull('tenant_id')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return Inertia::render('Vendor/Customers', [
            'customers' => $customers,
            'search' => $search,
            'stats' => [
                'total_customers' => $totalCustomers,
                'new_this_month' => $newThisMonth,
            ],
        ]);
    }
}
