<?php

declare(strict_types=1);

namespace App\Domain\Product\Actions;

use App\Domain\Product\DTOs\ChangePriceData;
use App\Domain\Product\Events\PriceChanged;
use App\Domain\Product\Models\PriceHistory;
use App\Domain\Product\Models\Product;
use App\Events\ProductPriceUpdated;
use App\Shared\Domain\AuditLog;
use Illuminate\Support\Facades\DB;

final class ChangePriceAction
{
    public function execute(ChangePriceData $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::query()
                ->lockForUpdate()
                ->findOrFail($data->productId);

            $oldPriceCents = $product->price_cents;
            $oldSalePrice = $product->sale_price;

            PriceHistory::query()->create([
                'product_id' => $product->id,
                'old_price_cents' => $oldPriceCents,
                'new_price_cents' => $data->newPriceCents,
                'old_sale_price' => $oldSalePrice,
                'new_sale_price' => $data->newSalePrice,
                'effective_at' => $data->effectiveAt ?? now(),
                'expires_at' => $data->expiresAt,
                'changed_by' => $data->changedBy,
                'reason' => $data->reason,
                'created_at' => now(),
            ]);

            $product->update([
                'price_cents' => $data->newPriceCents,
                'sale_price' => $data->newSalePrice,
            ]);

            AuditLog::log(
                action: 'price_changed',
                targetType: 'product',
                targetId: $product->id,
                payload: [
                    'sku' => $product->sku,
                    'old_price_cents' => $oldPriceCents,
                    'new_price_cents' => $data->newPriceCents,
                    'old_sale_price' => $oldSalePrice,
                    'new_sale_price' => $data->newSalePrice,
                    'reason' => $data->reason,
                ],
            );

            $fresh = $product->fresh();

            PriceChanged::dispatch(
                $fresh->id,
                $fresh->tenant_id,
                (int) $fresh->price_cents,
                $fresh->sale_price !== null ? (int) $fresh->sale_price : null,
            );

            ProductPriceUpdated::dispatch(
                $fresh->id,
                (int) $fresh->price_cents,
                $fresh->sale_price !== null ? (int) $fresh->sale_price : null,
            );

            return $fresh;
        });
    }
}
