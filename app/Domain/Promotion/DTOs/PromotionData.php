<?php

declare(strict_types=1);

namespace App\Domain\Promotion\DTOs;

use App\Domain\Promotion\Enums\DiscountType;
use App\Domain\Promotion\Enums\PromotionScope;
use App\Shared\DTOs\BaseData;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class PromotionData extends BaseData
{
    /**
     * @param  array<int>|null  $productIds
     * @param  array<int>|null  $categoryIds
     */
    public function __construct(
        public string $name,
        public DiscountType $discountType,
        public int $discountValue,
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
        public ?string $code = null,
        public ?string $description = null,
        public PromotionScope $scope = PromotionScope::All,
        public bool $autoApply = false,
        public ?int $minimumOrderCents = null,
        public ?int $maximumDiscountCents = null,
        public ?int $usageLimit = null,
        public ?int $perUserLimit = null,
        public bool $isActive = true,
        public ?array $productIds = null,
        public ?array $categoryIds = null,
    ) {}

    /**
     * Create from request data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            discountType: DiscountType::from($data['discount_type']),
            discountValue: (int) $data['discount_value'],
            startsAt: Carbon::parse($data['starts_at']),
            endsAt: Carbon::parse($data['ends_at']),
            code: $data['code'] ?? null,
            description: $data['description'] ?? null,
            scope: PromotionScope::from($data['scope'] ?? 'all'),
            autoApply: (bool) ($data['auto_apply'] ?? false),
            minimumOrderCents: isset($data['minimum_order_cents']) ? (int) $data['minimum_order_cents'] : null,
            maximumDiscountCents: isset($data['maximum_discount_cents']) ? (int) $data['maximum_discount_cents'] : null,
            usageLimit: isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            perUserLimit: isset($data['per_user_limit']) ? (int) $data['per_user_limit'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
            productIds: $data['product_ids'] ?? null,
            categoryIds: $data['category_ids'] ?? null,
        );
    }
}
