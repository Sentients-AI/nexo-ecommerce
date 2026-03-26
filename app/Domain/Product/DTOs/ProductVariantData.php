<?php

declare(strict_types=1);

namespace App\Domain\Product\DTOs;

use App\Shared\DTOs\BaseData;

final class ProductVariantData extends BaseData
{
    /**
     * @param  array<int>  $attributeValueIds
     * @param  array<string>|null  $images
     */
    public function __construct(
        public string $productId,
        public string $sku,
        public array $attributeValueIds,
        public ?string $priceCents = null,
        public ?string $salePrice = null,
        public bool $isActive = true,
        public int $sortOrder = 0,
        public ?array $images = null,
    ) {}

    /**
     * Create from request data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            productId: $data['product_id'],
            sku: $data['sku'],
            attributeValueIds: $data['attribute_value_ids'] ?? [],
            priceCents: $data['price_cents'] ?? null,
            salePrice: $data['sale_price'] ?? null,
            isActive: $data['is_active'] ?? true,
            sortOrder: $data['sort_order'] ?? 0,
            images: $data['images'] ?? null,
        );
    }
}
