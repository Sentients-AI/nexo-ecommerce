<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Actions;

use App\Domain\Promotion\DTOs\PromotionData;
use App\Domain\Promotion\Models\Promotion;
use Illuminate\Support\Facades\DB;

final readonly class CreatePromotionAction
{
    public function execute(PromotionData $data): Promotion
    {
        return DB::transaction(function () use ($data): Promotion {
            $promotion = Promotion::query()->create([
                'name' => $data->name,
                'code' => $data->code,
                'description' => $data->description,
                'discount_type' => $data->discountType,
                'discount_value' => $data->discountValue,
                'scope' => $data->scope,
                'auto_apply' => $data->autoApply,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'minimum_order_cents' => $data->minimumOrderCents,
                'maximum_discount_cents' => $data->maximumDiscountCents,
                'usage_limit' => $data->usageLimit,
                'per_user_limit' => $data->perUserLimit,
                'is_active' => $data->isActive,
            ]);

            // Attach products if scope is 'product'
            if ($data->productIds !== null && count($data->productIds) > 0) {
                $promotion->products()->attach($data->productIds);
            }

            // Attach categories if scope is 'category'
            if ($data->categoryIds !== null && count($data->categoryIds) > 0) {
                $promotion->categories()->attach($data->categoryIds);
            }

            return $promotion->fresh(['products', 'categories']);
        });
    }
}
