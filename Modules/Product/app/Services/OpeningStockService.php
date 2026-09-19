<?php

namespace Modules\Product\app\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\Stock;
use Modules\Product\app\Models\StockLedger;

class OpeningStockService
{
    public function __construct(
        private readonly BatchInventoryService $batchInventory,
        private readonly MrpInventoryService $mrpInventory
    ) {}

    public function record(
        Product $product,
        string $inventoryMode,
        array $entries,
        string $date,
        bool $useExistingLegacyBalance = false
    ): void {
        if ((int) $product->typeid !== 2 || $entries === []) {
            return;
        }

        if ($inventoryMode !== 'mrp' && ! ($inventoryMode === 'batch' && $product->is_batch_managed)) {
            throw ValidationException::withMessages([
                'opening_stock' => 'Tracked opening stock is only available for MRP or batch-managed inventory.',
            ]);
        }

        DB::transaction(function () use (
            $product,
            $inventoryMode,
            $entries,
            $date,
            $useExistingLegacyBalance
        ): void {
            Stock::where('stock_product_id', $product->product_code)->lockForUpdate()->get();

            $unitQuantity = max((float) ($product->uqty ?: 1), 1);
            $totalRawQuantity = 0.0;

            foreach ($entries as $entry) {
                $rawQuantity = round((float) $entry['quantity'] * $unitQuantity, 2);
                $purchaseRate = round((float) ($entry['purchase_price'] ?? 0) / $unitQuantity, 2);
                $trackedEntry = [
                    ...$entry,
                    'purchase_rate' => $purchaseRate,
                    'sale_price' => round((float) ($entry['sale_price'] ?? $product->price ?? 0), 2),
                ];

                if ($inventoryMode === 'mrp') {
                    $this->mrpInventory->receiveOpening(
                        $product,
                        $trackedEntry,
                        (int) $product->id,
                        $date,
                        $rawQuantity
                    );
                } else {
                    $this->batchInventory->receiveOpening(
                        $product,
                        $trackedEntry,
                        (int) $product->id,
                        $date,
                        $rawQuantity
                    );
                }

                $totalRawQuantity += $rawQuantity;
            }

            if ($totalRawQuantity <= 0) {
                return;
            }

            if (! $useExistingLegacyBalance) {
                Stock::updateStock($date, $product->product_code, $totalRawQuantity);
            }
            StockLedger::updateStockLedger($date, $product->product_code, $totalRawQuantity, $product->id, 'OPENING');
        }, 5);
    }
}
