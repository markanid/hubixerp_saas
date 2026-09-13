<?php

namespace Modules\Product\app\Services;

use Modules\Product\app\Models\Product;
use Modules\Settings\app\Models\Company;

class ProductStockService
{
    public function inventoryMode(): string
    {
        $mode = (string) (Company::query()->value('inventory_mode') ?? 'standard');

        return in_array($mode, ['standard', 'mrp', 'batch'], true) ? $mode : 'standard';
    }

    public function usesTrackedInventory(Product $product, ?string $inventoryMode = null): bool
    {
        $inventoryMode ??= $this->inventoryMode();

        return $inventoryMode === 'mrp'
            || ($inventoryMode === 'batch' && (bool) $product->is_batch_managed);
    }

    public function rawQuantity(Product $product, ?string $inventoryMode = null): float
    {
        $inventoryMode ??= $this->inventoryMode();

        if ($inventoryMode === 'mrp') {
            if (array_key_exists('tracked_stock_qty', $product->getAttributes())) {
                return (float) ($product->getAttribute('tracked_stock_qty') ?? 0);
            }

            return (float) ($product->relationLoaded('mrpStockLots')
                ? $product->mrpStockLots->sum('available_quantity')
                : $product->mrpStockLots()->sum('available_quantity'));
        }

        if ($inventoryMode === 'batch' && $product->is_batch_managed) {
            if (array_key_exists('tracked_stock_qty', $product->getAttributes())) {
                return (float) ($product->getAttribute('tracked_stock_qty') ?? 0);
            }

            return (float) ($product->relationLoaded('stockBatches')
                ? $product->stockBatches->sum('available_quantity')
                : $product->stockBatches()->sum('available_quantity'));
        }

        if ($product->relationLoaded('stock')) {
            return (float) ($product->stock?->stock_qty ?? 0);
        }

        return (float) ($product->stock()->value('stock_qty') ?? 0);
    }

    public function displayQuantity(Product $product, ?string $inventoryMode = null): float
    {
        $unitQuantity = (float) $product->uqty;

        return $this->rawQuantity($product, $inventoryMode) / ($unitQuantity > 0 ? $unitQuantity : 1);
    }

    public function canApplyDirectAdjustment(
        Product $product,
        string $inventoryMode,
        bool $wasTracked,
        bool $hasTransactions,
        bool $stockWasSubmitted
    ): bool {
        return $stockWasSubmitted
            && (int) $product->typeid === 2
            && !$wasTracked
            && !$hasTransactions
            && !$this->usesTrackedInventory($product, $inventoryMode);
    }
}
