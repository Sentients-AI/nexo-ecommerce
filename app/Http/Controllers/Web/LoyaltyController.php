<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Loyalty\Models\LoyaltyTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Inertia\Response;

final class LoyaltyController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $account = LoyaltyAccount::query()->firstOrCreate(
            [
                'tenant_id' => Context::get('tenant_id'),
                'user_id' => $user->id,
            ],
            [
                'points_balance' => 0,
                'total_points_earned' => 0,
                'total_points_redeemed' => 0,
            ]
        );

        $transactions = LoyaltyTransaction::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (LoyaltyTransaction $tx): array => [
                'id' => $tx->id,
                'type' => $tx->type->value,
                'type_label' => $tx->type->label(),
                'is_credit' => $tx->type->isCredit(),
                'points' => $tx->points,
                'balance_after' => $tx->balance_after,
                'description' => $tx->description,
                'created_at' => $tx->created_at?->toIso8601String(),
            ]);

        $pointsValueCents = (int) config('loyalty.points_value_cents', 1);
        $minimumRedemption = (int) config('loyalty.minimum_redemption', 100);

        return Inertia::render('Loyalty/Index', [
            'account' => [
                'points_balance' => $account->points_balance,
                'total_points_earned' => $account->total_points_earned,
                'total_points_redeemed' => $account->total_points_redeemed,
                'points_value_cents' => $pointsValueCents,
                'points_value_dollars' => round($account->points_balance * $pointsValueCents / 100, 2),
            ],
            'transactions' => $transactions,
            'config' => [
                'points_per_dollar' => (int) config('loyalty.points_per_dollar', 1),
                'points_value_cents' => $pointsValueCents,
                'minimum_redemption' => $minimumRedemption,
            ],
            'tiers' => [
                ['name' => 'Bronze', 'min_points' => 0, 'max_points' => 999, 'color' => 'amber'],
                ['name' => 'Silver', 'min_points' => 1000, 'max_points' => 4999, 'color' => 'slate'],
                ['name' => 'Gold', 'min_points' => 5000, 'max_points' => null, 'color' => 'yellow'],
            ],
        ]);
    }
}
