<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Inertia\Response;

final class VendorSettingsController extends Controller
{
    public function index(): Response
    {
        $tenant = Context::get('tenant');

        $productCount = Product::query()->count();
        $customerCount = User::query()->whereNotNull('tenant_id')->count();
        $orderCount = Order::query()->count();

        return Inertia::render('Vendor/Settings', [
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
                'email' => $tenant->email,
                'description' => $tenant->description,
                'is_active' => $tenant->isActive(),
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                'subscribed_at' => $tenant->subscribed_at?->toIso8601String(),
                'settings' => $tenant->settings ?? [],
            ] : null,
            'usage' => [
                'products' => $productCount,
                'customers' => $customerCount,
                'orders' => $orderCount,
            ],
        ]);
    }
}
