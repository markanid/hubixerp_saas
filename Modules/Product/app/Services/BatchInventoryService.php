<?php

namespace Modules\Product\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Product\app\Models\BatchMovement;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\StockBatch;
use Modules\Settings\app\Models\Company;

class BatchInventoryService
{
    public function __construct(private ?InventoryLabelService $inventoryLabels = null)
    {
    }

    public function enabled(?Product $product = null): bool
    {
        if ((Company::query()->value('inventory_mode') ?? 'standard') !== 'batch') {
            return false;
        }

        return $product === null || (bool) $product->is_batch_managed;
    }

    public function receivePurchase(
        Product $product,
        array $item,
        int $purchaseId,
        int $detailId,
        string $date,
        float $rawQuantity
    ): ?StockBatch {
        if (!$this->enabled($product)) {
            return null;
        }

        $batchNo = trim((string) ($item['batch_no'] ?? ''));
        if ($batchNo === '') {
            throw ValidationException::withMessages([
                'purchase_items' => "Batch number is required for {$product->product}.",
            ]);
        }

        $batch = StockBatch::where('product_id', $product->product_code)
            ->where('batch_no', $batchNo)
            ->lockForUpdate()
            ->first();

        $existingBatch = $batch !== null;

        if (!$batch) {
            $batch = new StockBatch([
                'product_id' => $product->product_code,
                'batch_no' => $batchNo,
                'quantity' => 0,
                'available_quantity' => 0,
            ]);
        } elseif ($batch->expiry_date && !empty($item['expiry_date'])) {
            $incomingExpiry = $this->normaliseExpiry($item['expiry_date']);
            if ($incomingExpiry !== $batch->expiry_date->toDateString()) {
                throw ValidationException::withMessages([
                    'purchase_items' => "Expiry date does not match existing batch {$batchNo}.",
                ]);
            }
        }

        $unitQty = max((float) ($item['unit_qty'] ?? 1), 1);
        $incomingPurchaseRate = in_array(($item['unit'] ?? ''), ['No.s', 'Nos.'], true)
            ? round((float) ($item['unit_price'] ?? 0), 2)
            : round((float) ($item['unit_price'] ?? 0) / $unitQty, 2);
        $incomingMrp = round((float) ($item['mrp'] ?? $product->mrp ?? 0), 2);
        $incomingSalePrice = round((float) ($item['sale_price'] ?? $product->price ?? 0), 2);

        if ($existingBatch && (float) $batch->purchase_rate > 0
            && abs((float) $batch->purchase_rate - $incomingPurchaseRate) > 0.009) {
            throw ValidationException::withMessages([
                'purchase_items' => "Purchase price does not match existing batch {$batchNo}. Use a different batch number for different pricing.",
            ]);
        }
        if ($existingBatch && (float) $batch->mrp > 0 && $incomingMrp > 0
            && abs((float) $batch->mrp - $incomingMrp) > 0.009) {
            throw ValidationException::withMessages([
                'purchase_items' => "MRP does not match existing batch {$batchNo}. Use a different batch number for a different MRP.",
            ]);
        }

        if ($existingBatch && Schema::hasColumn('stock_batches', 'sale_price')
            && (float) $batch->sale_price > 0
            && abs((float) $batch->sale_price - $incomingSalePrice) > 0.009) {
            throw ValidationException::withMessages([
                'purchase_items' => "Sale price does not match existing batch {$batchNo}. Use a different batch number for different pricing.",
            ]);
        }

        $batch->expiry_date = $batch->expiry_date ?: $this->normaliseExpiry($item['expiry_date'] ?? null);
        $batch->purchase_rate = (float) $batch->purchase_rate > 0
            ? $batch->purchase_rate
            : $incomingPurchaseRate;
        $batch->mrp = (float) $batch->mrp > 0 ? $batch->mrp : $incomingMrp;
        if (Schema::hasColumn('stock_batches', 'sale_price')) {
            $batch->sale_price = (float) $batch->sale_price > 0 ? $batch->sale_price : $incomingSalePrice;
        }
        $batch->quantity = (float) $batch->quantity + $rawQuantity;
        $batch->available_quantity = (float) $batch->available_quantity + $rawQuantity;
        $batch->save();

        $this->labelService()->ensureForBatch($batch);

        $this->movement($batch, 'purchase', 'purchase', $purchaseId, $detailId, $date, $rawQuantity, 0);

        return $batch;
    }

    public function allocateSale(
        Product $product,
        float $rawQuantity,
        int $saleId,
        int $detailId,
        string $date,
        ?int $selectedBatchId = null,
        float $saleRate = 0,
        string $referenceType = 'sale',
        string $movementType = 'sale',
        bool $allowOutOfStock = false
    ): Collection {
        if (!$this->enabled($product)) {
            return collect();
        }

        $query = StockBatch::where('product_id', $product->product_code)
            ->where('available_quantity', '>', 0);

        if ($selectedBatchId) {
            $query->whereKey($selectedBatchId);
        } else {
            // NULL expiry is intentionally last; dated stock follows FEFO.
            $query->orderByRaw('expiry_date IS NULL')
                ->orderBy('expiry_date')
                ->orderBy('id');
        }

        $batches = $query->lockForUpdate()->get();
        $available = (float) $batches->sum('available_quantity');
        if (!$allowOutOfStock && $available + 0.00001 < $rawQuantity) {
            throw ValidationException::withMessages([
                'sale_items' => "Insufficient batch stock for {$product->product}. Available: {$available}.",
            ]);
        }

        $remaining = $rawQuantity;
        $allocations = collect();
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $quantity = min($remaining, (float) $batch->available_quantity);
            $batch->available_quantity = (float) $batch->available_quantity - $quantity;
            $batch->save();
            $movement = $this->movement(
                $batch, $movementType, $referenceType, $saleId, $detailId, $date, 0, $quantity, $saleRate
            );
            $allocations->push($movement);
            $remaining -= $quantity;
        }

        return $allocations;
    }

    public function reverseReference(string $referenceType, int $referenceId, string $date): void
    {
        $movements = BatchMovement::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereNull('reversal_of_id')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($movements as $movement) {
            if (BatchMovement::where('reversal_of_id', $movement->id)->exists()) {
                continue;
            }
            $batch = StockBatch::whereKey($movement->stock_batch_id)->lockForUpdate()->firstOrFail();
            $net = (float) $movement->quantity_in - (float) $movement->quantity_out;
            $newAvailable = (float) $batch->available_quantity - $net;
            if ($newAvailable < -0.00001) {
                throw ValidationException::withMessages([
                    'batch' => "Batch {$batch->batch_no} no longer has enough stock to reverse this transaction.",
                ]);
            }
            $batch->available_quantity = max(0, $newAvailable);
            if ($movement->movement_type === 'purchase') {
                $batch->quantity = max(0, (float) $batch->quantity - (float) $movement->quantity_in);
            } elseif ($movement->movement_type === 'purchase_return') {
                $batch->quantity = (float) $batch->quantity + (float) $movement->quantity_out;
            }
            $batch->save();

            $this->movement(
                $batch,
                $movement->movement_type . '_reversal',
                $referenceType,
                $referenceId,
                $movement->reference_detail_id,
                $date,
                (float) $movement->quantity_out,
                (float) $movement->quantity_in,
                (float) $movement->sale_rate,
                $movement->id
            );
        }
    }

    public function returnSaleDetail(int $saleDetailId, float $rawQuantity, int $returnId, int $returnDetailId, string $date): void
    {
        $sales = BatchMovement::where('reference_type', 'sale')
            ->where('reference_detail_id', $saleDetailId)
            ->where('movement_type', 'sale')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $alreadyReturned = (float) BatchMovement::where('movement_type', 'sale_return')
            ->whereIn('reversal_of_id', $sales->pluck('id'))
            ->whereNotIn('id', BatchMovement::query()
                ->select('reversal_of_id')
                ->whereNotNull('reversal_of_id'))
            ->sum('quantity_in');
        $sold = (float) $sales->sum('quantity_out');
        if ($rawQuantity > ($sold - $alreadyReturned) + 0.00001) {
            throw ValidationException::withMessages(['return_items' => 'Sale return exceeds the batch quantity sold.']);
        }

        $remaining = $rawQuantity;
        foreach ($sales as $saleMovement) {
            if ($remaining <= 0) break;
            $returnedFromMovement = (float) BatchMovement::where('reversal_of_id', $saleMovement->id)
                ->where('movement_type', 'sale_return')
                ->whereNotIn('id', BatchMovement::query()
                    ->select('reversal_of_id')
                    ->whereNotNull('reversal_of_id'))
                ->sum('quantity_in');
            $quantity = min($remaining, (float) $saleMovement->quantity_out - $returnedFromMovement);
            if ($quantity <= 0) continue;
            $batch = StockBatch::whereKey($saleMovement->stock_batch_id)->lockForUpdate()->firstOrFail();
            $batch->increment('available_quantity', $quantity);
            $this->movement(
                $batch, 'sale_return', 'sale_return', $returnId, $returnDetailId,
                $date, $quantity, 0, (float) $saleMovement->sale_rate, $saleMovement->id
            );
            $remaining -= $quantity;
        }
    }

    public function manualSaleReturn(Product $product, float $rawQuantity, int $returnId, int $returnDetailId, string $date): void
    {
        if (!$this->enabled($product)) {
            return;
        }

        $batch = StockBatch::where('product_id', $product->product_code)
            ->where('batch_no', 'MANUAL-RETURN')
            ->lockForUpdate()
            ->first();

        if (!$batch) {
            $batch = new StockBatch([
                'product_id' => $product->product_code,
                'batch_no' => 'MANUAL-RETURN',
                'quantity' => 0,
                'available_quantity' => 0,
                'purchase_rate' => 0,
                'mrp' => (float) ($product->mrp ?? 0),
            ]);
            if (Schema::hasColumn('stock_batches', 'sale_price')) {
                $batch->sale_price = (float) ($product->price ?? 0);
            }
        }

        $batch->available_quantity = (float) $batch->available_quantity + $rawQuantity;
        $batch->save();

        $this->labelService()->ensureForBatch($batch);

        $this->movement($batch, 'sale_return', 'sale_return', $returnId, $returnDetailId, $date, $rawQuantity, 0);
    }

    public function purchaseReturn(
        StockBatch $batch,
        float $rawQuantity,
        int $returnId,
        int $returnDetailId,
        string $date
    ): void {
        $batch = StockBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
        if ((float) $batch->available_quantity + 0.00001 < $rawQuantity) {
            throw ValidationException::withMessages([
                'return_items' => "Return exceeds available quantity in batch {$batch->batch_no}.",
            ]);
        }
        $batch->available_quantity = (float) $batch->available_quantity - $rawQuantity;
        $batch->quantity = max(0, (float) $batch->quantity - $rawQuantity);
        $batch->save();
        $this->movement($batch, 'purchase_return', 'purchase_return', $returnId, $returnDetailId, $date, 0, $rawQuantity);
    }

    public function availableBatches(string $productCode): Collection
    {
        return StockBatch::where('product_id', $productCode)
            ->where('available_quantity', '>', 0)
            ->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->orderBy('batch_no')
            ->get();
    }

    private function movement(
        StockBatch $batch,
        string $type,
        string $referenceType,
        int $referenceId,
        ?int $detailId,
        string $date,
        float $in,
        float $out,
        float $saleRate = 0,
        ?int $reversalOf = null
    ): BatchMovement {
        return BatchMovement::create([
            'stock_batch_id' => $batch->id,
            'product_id' => $batch->product_id,
            'movement_type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_detail_id' => $detailId,
            'movement_date' => $date,
            'quantity_in' => $in,
            'quantity_out' => $out,
            'balance_after' => $batch->available_quantity,
            'purchase_rate' => $batch->purchase_rate,
            'sale_rate' => $saleRate,
            'reversal_of_id' => $reversalOf,
        ]);
    }

    public function normaliseExpiry(?string $value): ?string
    {
        if (!$value) return null;
        foreach (['Y-m-d', 'd/m/Y', 'm/Y', 'm/y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                return in_array($format, ['m/Y', 'm/y'], true)
                    ? $date->endOfMonth()->toDateString()
                    : $date->toDateString();
            } catch (\Throwable) {
            }
        }
        throw ValidationException::withMessages(['expiry_date' => 'Expiry date must be DD/MM/YYYY or MM/YYYY.']);
    }

    private function labelService(): InventoryLabelService
    {
        return $this->inventoryLabels ??= app(InventoryLabelService::class);
    }
}