<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTOs\AdjustStockData;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Events\StockUpdated;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Inventory\Models\StockMovement;
use App\Events\InventoryStockUpdated;
use Exception;
use Illuminate\Support\Facades\DB;

final class AdjustStockAction
{
    /**
     * Execute the action to adjust stock quantity.
     */
    public function execute(AdjustStockData $data): Stock
    {
        return DB::transaction(function () use ($data) {
            $stock = Stock::query()
                ->where('product_id', $data->productId)
                ->lockForUpdate()
                ->firstOrFail();

            $newQuantity = $stock->quantity_available + $data->quantityChange;

            if ($newQuantity < 0) {
                throw new Exception('Stock adjustment would result in negative quantity');
            }

            $stock->update(['quantity_available' => $newQuantity]);

            StockMovement::query()->create([
                'stock_id' => $stock->id,
                'product_id' => $data->productId,
                'type' => $data->quantityChange > 0 ? StockMovementType::In : StockMovementType::Out,
                'quantity' => abs($data->quantityChange),
                'reason' => $data->reason,
                'user_id' => $data->userId,
            ]);

            $fresh = $stock->fresh();

            StockUpdated::dispatch(
                (int) $data->productId,
                $fresh->id,
                $fresh->tenant_id,
                $fresh->quantity_available,
                $fresh->quantity_reserved,
                'adjusted',
            );

            InventoryStockUpdated::dispatch(
                (int) $data->productId,
                $fresh->tenant_id,
                $fresh->quantity_available,
                $fresh->quantity_reserved,
                'adjusted',
            );

            return $fresh;
        });
    }
}
