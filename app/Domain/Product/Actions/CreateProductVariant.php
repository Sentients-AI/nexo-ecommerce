<?php

declare(strict_types=1);

namespace App\Domain\Product\Actions;

use App\Domain\Product\DTOs\ProductVariantData;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

final class CreateProductVariant
{
    /**
     * Execute the action to create a new product variant.
     */
    public function execute(ProductVariantData $data): ProductVariant
    {
        return DB::transaction(function () use ($data) {
            $variant = ProductVariant::query()->create([
                'product_id' => $data->productId,
                'sku' => $data->sku,
                'price_cents' => $data->priceCents,
                'sale_price' => $data->salePrice,
                'is_active' => $data->isActive,
                'sort_order' => $data->sortOrder,
                'images' => $data->images,
            ]);

            if (! empty($data->attributeValueIds)) {
                $variant->attributeValues()->sync($data->attributeValueIds);
            }

            // Create a stock record for this variant (product_id omitted — use variant relationship)
            $variant->stock()->create([
                'product_id' => null,
                'quantity_available' => 0,
                'quantity_reserved' => 0,
            ]);

            return $variant->load(['attributeValues.attributeType', 'stock']);
        });
    }
}
