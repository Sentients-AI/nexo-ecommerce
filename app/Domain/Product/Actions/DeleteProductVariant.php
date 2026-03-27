<?php

declare(strict_types=1);

namespace App\Domain\Product\Actions;

use App\Domain\Product\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

final class DeleteProductVariant
{
    /**
     * Execute the action to delete a product variant.
     *
     * Detaches attribute values and removes the variant's stock record before deleting.
     */
    public function execute(ProductVariant $variant): void
    {
        DB::transaction(function () use ($variant) {
            $variant->attributeValues()->detach();
            $variant->stock()->delete();
            $variant->delete();
        });
    }
}
