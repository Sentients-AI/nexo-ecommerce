<?php

declare(strict_types=1);

namespace App\Domain\Product\Actions;

use App\Domain\Product\DTOs\ProductVariantData;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

final class UpdateProductVariant
{
    /**
     * Execute the action to update an existing product variant.
     */
    public function execute(ProductVariant $variant, ProductVariantData $data): ProductVariant
    {
        return DB::transaction(function () use ($variant, $data) {
            $variant->update([
                'sku' => $data->sku,
                'price_cents' => $data->priceCents,
                'sale_price' => $data->salePrice,
                'is_active' => $data->isActive,
                'sort_order' => $data->sortOrder,
                'images' => $data->images,
            ]);

            if ($data->attributeValueIds !== []) {
                $variant->attributeValues()->sync($data->attributeValueIds);
            }

            return $variant->load(['attributeValues.attributeType', 'stock']);
        });
    }
}
