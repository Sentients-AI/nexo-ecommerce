<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Actions;

use App\Domain\Order\Models\Order;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Promotion\Models\PromotionUsage;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class RecordPromotionUsageAction
{
    public function execute(
        Promotion $promotion,
        User $user,
        Order $order,
        int $discountCents,
    ): PromotionUsage {
        return DB::transaction(function () use ($promotion, $user, $order, $discountCents): PromotionUsage {
            if (! $promotion->incrementUsageCount()) {
                throw new RuntimeException('Promotion usage limit has been reached.');
            }

            return PromotionUsage::query()->create([
                'promotion_id' => $promotion->id,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'discount_cents' => $discountCents,
            ]);
        });
    }
}
