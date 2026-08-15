<?php

namespace Modules\Pos\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\InventoryLabel;
use Modules\Product\app\Services\BatchInventoryService;
use Modules\Product\app\Services\InventoryLabelService;
use Modules\Product\app\Services\MrpInventoryService;

class StockService
{
    public function __construct(
        private readonly BatchInventoryService $batchInventory,
        private readonly MrpInventoryService $mrpInventory,
        private readonly InventoryLabelService $inventoryLabels
    )
    {
    }

    public function search(string $query): Collection
    {
        $inventoryLabel = $this->inventoryLabels->resolveActive($query);
        if ($inventoryLabel) {
            return collect([$this->formatProduct($inventoryLabel->product, $inventoryLabel)]);
        }

        return Product::with($this->stockRelations())
            ->where('typeid', '!=', 3)
            ->where('status', '1')
            ->where(function ($builder) use ($query) {
                $builder->where('product_code', 'like', "%{$query}%")
                    ->orWhere('product', 'like', "%{$query}%")
                    ->orWhere('bar_code', 'like', "%{$query}%");
            })
            ->limit(20)
            ->get()
            ->map(fn (Product $product) => $this->formatProduct($product));
    }

    public function details(array $productCodes): Collection
    {
        return Product::with($this->stockRelations())
            ->whereIn('product_code', array_values(array_unique($productCodes)))
            ->get()
            ->map(fn (Product $product) => $this->formatProduct($product))
            ->keyBy('product_code');
    }

    public function assertAvailable(array $items): void
    {
        $products = Product::with($this->stockRelations())
            ->whereIn('product_code', collect($items)->pluck('product_id')->unique())
            ->get()
            ->keyBy('product_code');

        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id']);
            if (!$product) {
                throw ValidationException::withMessages(["items.$index.product_id" => 'Product not found.']);
            }

            $rawQuantity = $this->rawQuantity($product, (float) $item['quantity'], (string) $item['unit']);
            $available = $this->rawAvailable($product);

            if ($available + 0.00001 < $rawQuantity) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => "Only {$this->displayStock($product, $available)} {$product->unit} available for {$product->product}.",
                ]);
            }
        }
    }

    private function formatProduct(Product $product, ?InventoryLabel $inventoryLabel = null): array
    {
        $selectedBatch = $inventoryLabel?->stockBatch;
        $selectedMrpLot = $inventoryLabel?->mrpStockLot;
        $slotDetails = $inventoryLabel ? $this->inventoryLabels->slotDetails($inventoryLabel, $product) : null;
        $rawStock = $inventoryLabel
            ? $this->inventoryLabels->availableQuantity($inventoryLabel)
            : $this->rawAvailable($product);
        $batches = $this->batchInventory->enabled($product)
            ? $this->batchInventory->availableBatches($product->product_code)
            : collect();
        if ($selectedBatch && !$batches->contains('id', $selectedBatch->id)) {
            $batches->prepend($selectedBatch);
        }
        $mrpLots = $this->mrpInventory->enabled()
            ? $this->mrpInventory->availableLots($product->product_code)
            : collect();
        if ($selectedMrpLot && !$mrpLots->contains('id', $selectedMrpLot->id)) {
            $mrpLots->prepend($selectedMrpLot);
        }

        return [
            'id' => $product->id,
            'product_code' => $product->product_code,
            'barcode' => $inventoryLabel?->barcode ?: ($product->bar_code ?: $product->product_code),
            'product' => $product->product,
            'image_url' => $this->imageUrl($product),
            'hsn_code' => $product->hsn_code,
            'price' => (float) ($slotDetails['sale_price'] ?? $product->price),
            'mrp' => (float) ($slotDetails['mrp'] ?? $product->mrp),
            'unit' => $product->unit ?: 'No.s',
            'uqty' => (float) ($product->uqty ?: 1),
            'gst' => (float) ($product->gst ?: 0),
            'current_stock' => $this->displayStock($product, $rawStock),
            'raw_stock' => $rawStock,
            'stock_label' => $this->stockLabel($product, $rawStock),
            'stock_class' => $this->stockClass($product, $rawStock),
            'is_batch_managed' => (bool) $product->is_batch_managed && $this->batchInventory->enabled($product),
            'stock_batch_id' => $selectedBatch?->id,
            'mrp_stock_lot_id' => $selectedMrpLot?->id,
            'inventory_label_type' => $inventoryLabel?->label_type,
            'batches' => $this->batchInventory->enabled($product)
                ? $batches->map(fn ($batch) => [
                    'id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'expiry_date' => $batch->expiry_date?->format('d/m/Y'),
                    'available_quantity' => (float) $batch->available_quantity,
                    'display_quantity' => $this->displayStock($product, (float) $batch->available_quantity),
                    'mrp' => (float) $batch->mrp,
                ])->values()
                : [],
            'mrp_mode' => $this->mrpInventory->enabled(),
            'mrp_lots' => $this->mrpInventory->enabled()
                ? $mrpLots->map(fn ($lot) => [
                    'id' => $lot->id,
                    'purchase_voucher' => $lot->purchase_voucher,
                    'purchase_date' => $lot->purchase_date?->format('d/m/Y'),
                    'purchase_rate' => (float) $lot->purchase_rate,
                    'display_purchase_rate' => round((float) $lot->purchase_rate * max((float) ($product->uqty ?: 1), 1), 2),
                    'mrp' => (float) $lot->mrp,
                    'sale_price' => (float) $lot->sale_price,
                    'margin_percentage' => (float) $lot->margin_percentage,
                    'margin_amount' => (float) $lot->margin_amount,
                    'available_quantity' => (float) $lot->available_quantity,
                    'display_quantity' => $this->displayStock($product, (float) $lot->available_quantity),
                ])->values()
                : [],
        ];
    }

    private function rawAvailable(Product $product): float
    {
        if ($this->mrpInventory->enabled()) {
            return (float) $product->mrpStockLots->sum('available_quantity');
        }

        if ($this->batchInventory->enabled($product)) {
            return (float) $product->stockBatches->sum('available_quantity');
        }

        return (float) ($product->stock?->stock_qty ?? 0);
    }

    private function stockRelations(): array
    {
        $relations = ['stock', 'stockBatches'];
        if (Schema::hasTable('mrp_stock_lots')) {
            $relations[] = 'mrpStockLots';
        }

        return $relations;
    }

    private function rawQuantity(Product $product, float $quantity, string $unit): float
    {
        return in_array($unit, ['No.s', 'Nos.'], true)
            ? $quantity
            : $quantity * max((float) ($product->uqty ?: 1), 1);
    }

    private function displayStock(Product $product, float $rawStock): float
    {
        return round($rawStock / max((float) ($product->uqty ?: 1), 1), 2);
    }

    private function imageUrl(Product $product): string
    {
        if ($product->product_image && Storage::disk('public')->exists('product_logos/' . $product->product_image)) {
            return asset('storage/product_logos/' . $product->product_image);
        }

        return asset('uploads/avatar.png');
    }

    private function stockLabel(Product $product, float $rawStock): string
    {
        $stock = $this->displayStock($product, $rawStock);
        $minimum = (float) ($product->minquantity ?? 0);
        $maximum = (float) ($product->maxquantity ?? 0);

        if ($stock <= 0) {
            return 'Out';
        }

        if ($minimum > 0 && $stock < $minimum) {
            return 'Low';
        }

        if ($maximum > 0 && $stock > $maximum) {
            return 'High';
        }

        return 'In Stock';
    }

    private function stockClass(Product $product, float $rawStock): string
    {
        return match ($this->stockLabel($product, $rawStock)) {
            'Out' => 'stock-out',
            'Low' => 'stock-low',
            'High' => 'stock-high',
            default => 'stock-good',
        };
    }
}