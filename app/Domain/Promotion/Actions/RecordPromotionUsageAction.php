<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Actions;

use App\Domain\Order\Models\Order;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Promotion\Models\PromotionUsage;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class RecordPromotionUsageAction
{
    public function execute(
        Promotion $promotion,
        User $user,
        Order $order,
        int $discountCents,
    ): PromotionUsage {
        return DB::transaction(function () use ($promotion, $user, $order, $discountCents): PromotionUsage {
            // Increment the promotion usage count
            $promotion->incrementUsageCount();

            // Create usage record
            return PromotionUsage::query()->create([
                'promotion_id' => $promotion->id,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'discount_cents' => $discountCents,
            ]);
        });
    }
}
