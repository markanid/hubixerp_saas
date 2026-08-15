<?php

namespace Modules\Product\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Product\app\Models\InventoryLabel;
use Modules\Product\app\Models\MrpStockLot;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\StockBatch;
use Modules\Settings\app\Models\Company;
use RuntimeException;

class InventoryLabelService
{
    public function ensureForMrpLot(MrpStockLot $lot): ?InventoryLabel
    {
        if (!$this->available() || !$this->isPhysicalMrpLot($lot)) {
            return null;
        }

        $signature = $this->signatureForMrpLot($lot);
        $identity = $this->supportsSlots()
            ? [
                'product_id' => $lot->product_id,
                'label_type' => InventoryLabel::TYPE_MRP,
                'signature' => $signature,
            ]
            : ['mrp_stock_lot_id' => $lot->id];

        return InventoryLabel::firstOrCreate(
            $identity,
            [
                'product_id' => $lot->product_id,
                'label_type' => InventoryLabel::TYPE_MRP,
                'signature' => $signature,
                'mrp_stock_lot_id' => $lot->id,
                'stock_batch_id' => null,
                'barcode' => $this->barcodeFor(InventoryLabel::TYPE_MRP, (int) $lot->id),
            ]
        );
    }

    public function ensureForBatch(StockBatch $batch): ?InventoryLabel
    {
        if (!$this->available() || !$this->isPhysicalBatch($batch)) {
            return null;
        }

        $signature = $this->signatureForBatch($batch);
        $identity = $this->supportsSlots()
            ? [
                'product_id' => $batch->product_id,
                'label_type' => InventoryLabel::TYPE_BATCH,
                'signature' => $signature,
            ]
            : ['stock_batch_id' => $batch->id];

        return InventoryLabel::firstOrCreate(
            $identity,
            [
                'product_id' => $batch->product_id,
                'label_type' => InventoryLabel::TYPE_BATCH,
                'signature' => $signature,
                'mrp_stock_lot_id' => null,
                'stock_batch_id' => $batch->id,
                'barcode' => $this->barcodeFor(InventoryLabel::TYPE_BATCH, (int) $batch->id),
            ]
        );
    }

    public function labelsForProduct(Product $product, ?string $inventoryMode = null): Collection
    {
        if (!$this->available()) {
            return collect();
        }

        $mode = $inventoryMode ?? (Company::query()->value('inventory_mode') ?? 'standard');

        if ($mode === InventoryLabel::TYPE_MRP) {
            MrpStockLot::where('product_id', $product->product_code)
                ->where(function ($query) {
                    $query->whereNull('purchase_voucher')
                        ->orWhere('purchase_voucher', '!=', 'OPENING-DEFICIT');
                })
                ->where(function ($query) {
                    $query->where('quantity', '>', 0)->orWhere('available_quantity', '>', 0);
                })
                ->orderBy('id')
                ->each(fn (MrpStockLot $lot) => $this->ensureForMrpLot($lot));
        }

        if ($mode === InventoryLabel::TYPE_BATCH && $product->is_batch_managed) {
            StockBatch::where('product_id', $product->product_code)
                ->where(function ($query) {
                    $query->where('quantity', '>', 0)->orWhere('available_quantity', '>', 0);
                })
                ->orderBy('id')
                ->each(fn (StockBatch $batch) => $this->ensureForBatch($batch));
        }

        return InventoryLabel::with(['mrpStockLot', 'stockBatch'])
            ->where('product_id', $product->product_code)
            ->where('label_type', $mode)
            ->orderBy('id')
            ->get()
            ->filter(function (InventoryLabel $label) {
                return $label->label_type === InventoryLabel::TYPE_MRP
                    ? $this->mrpLotsForLabel($label)->isNotEmpty()
                    : $this->batchesForLabel($label)->isNotEmpty();
            })
            ->values();
    }

    public function resolveActive(string $barcode): ?InventoryLabel
    {
        if (!$this->available()) {
            return null;
        }

        $label = InventoryLabel::with(['product', 'mrpStockLot', 'stockBatch'])
            ->where('barcode', trim($barcode))
            ->first();

        if (!$label || !$label->product || (string) $label->product->status !== '1' || (int) $label->product->typeid === 3) {
            return null;
        }

        $mode = Company::query()->value('inventory_mode') ?? 'standard';
        if ($label->label_type === InventoryLabel::TYPE_MRP) {
            if ($mode !== 'mrp') {
                return null;
            }

            $activeLot = $this->mrpLotsForLabel($label, true)->first();
            if (!$activeLot) {
                return null;
            }

            $label->setRelation('mrpStockLot', $activeLot);

            return $label;
        }

        if ($mode !== 'batch' || !$label->product->is_batch_managed) {
            return null;
        }

        $activeBatch = $this->batchesForLabel($label, true)->first();
        if (!$activeBatch) {
            return null;
        }

        $label->setRelation('stockBatch', $activeBatch);

        return $label;
    }

    public function mrpLotsForLabel(InventoryLabel $label, bool $availableOnly = false): Collection
    {
        $source = $label->mrpStockLot;
        if (!$source) {
            return collect();
        }

        $query = MrpStockLot::where('product_id', $label->product_id)
            ->where('purchase_rate', $source->purchase_rate)
            ->where('mrp', $source->mrp)
            ->where('sale_price', $source->sale_price)
            ->where(function ($builder) {
                $builder->whereNull('purchase_voucher')->orWhere('purchase_voucher', '!=', 'OPENING-DEFICIT');
            });

        if ($availableOnly) {
            $query->where('available_quantity', '>', 0);
        } else {
            $query->where(function ($builder) {
                $builder->where('quantity', '>', 0)->orWhere('available_quantity', '>', 0);
            });
        }

        return $query->orderBy('purchase_date')->orderBy('id')->get();
    }

    public function batchesForLabel(InventoryLabel $label, bool $availableOnly = false): Collection
    {
        $source = $label->stockBatch;
        if (!$source) {
            return collect();
        }

        $query = StockBatch::where('product_id', $label->product_id)
            ->where('batch_no', $source->batch_no)
            ->where('purchase_rate', $source->purchase_rate)
            ->where('mrp', $source->mrp);

        if (Schema::hasColumn('stock_batches', 'sale_price')) {
            $query->where('sale_price', $source->sale_price);
        }

        $source->expiry_date
            ? $query->whereDate('expiry_date', $source->expiry_date->toDateString())
            : $query->whereNull('expiry_date');

        if ($availableOnly) {
            $query->where('available_quantity', '>', 0);
        } else {
            $query->where(function ($builder) {
                $builder->where('quantity', '>', 0)->orWhere('available_quantity', '>', 0);
            });
        }

        return $query->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->orderBy('id')->get();
    }

    public function slotDetails(InventoryLabel $label, Product $product): array
    {
        $sources = $label->label_type === InventoryLabel::TYPE_MRP
            ? $this->mrpLotsForLabel($label)
            : $this->batchesForLabel($label);
        $source = $sources->first()
            ?? ($label->label_type === InventoryLabel::TYPE_MRP ? $label->mrpStockLot : $label->stockBatch);
        $unitQty = max((float) ($product->uqty ?: 1), 1);

        return [
            'label' => $label,
            'product_code' => $product->product_code,
            'product_name' => $product->product,
            'purchase_price' => round((float) ($source?->purchase_rate ?? 0) * $unitQty, 2),
            'mrp' => round((float) ($source?->mrp ?? 0), 2),
            'sale_price' => round((float) ($source?->sale_price ?? $product->price ?? 0), 2),
            'batch_number' => $source instanceof StockBatch ? $source->batch_no : null,
            'expiry_date' => $source instanceof StockBatch ? $source->expiry_date : null,
            'raw_quantity' => (float) $sources->sum('quantity'),
            'raw_available_quantity' => (float) $sources->sum('available_quantity'),
            'source_count' => $sources->count(),
        ];
    }

    public function availableQuantity(InventoryLabel $label): float
    {
        return (float) ($label->label_type === InventoryLabel::TYPE_MRP
            ? $this->mrpLotsForLabel($label, true)->sum('available_quantity')
            : $this->batchesForLabel($label, true)->sum('available_quantity'));
    }

    public function barcodeFor(string $type, int $sourceId): string
    {
        if ($sourceId > 999999999) {
            throw new RuntimeException('Inventory label source ID is too large for the barcode format.');
        }

        $prefix = $type === InventoryLabel::TYPE_MRP ? '81' : '82';
        $payload = $prefix . str_pad((string) $sourceId, 9, '0', STR_PAD_LEFT);

        return $payload . $this->luhnCheckDigit($payload);
    }

    private function luhnCheckDigit(string $payload): int
    {
        $sum = 0;
        $digits = str_split($payload . '0');
        $parity = count($digits) % 2;

        foreach ($digits as $index => $digit) {
            $value = (int) $digit;
            if ($index % 2 === $parity) {
                $value *= 2;
                if ($value > 9) {
                    $value -= 9;
                }
            }
            $sum += $value;
        }

        return (10 - ($sum % 10)) % 10;
    }

    private function available(): bool
    {
        return Schema::hasTable('inventory_labels');
    }

    private function supportsSlots(): bool
    {
        return $this->available() && Schema::hasColumn('inventory_labels', 'signature');
    }

    private function signatureForMrpLot(MrpStockLot $lot): string
    {
        return $this->signature([
            InventoryLabel::TYPE_MRP,
            $lot->product_id,
            $lot->purchase_rate,
            $lot->mrp,
            $lot->sale_price,
        ]);
    }

    private function signatureForBatch(StockBatch $batch): string
    {
        return $this->signature([
            InventoryLabel::TYPE_BATCH,
            $batch->product_id,
            $batch->purchase_rate,
            $batch->mrp,
            $batch->sale_price ?? 0,
            trim((string) $batch->batch_no),
            $batch->expiry_date?->toDateString() ?? '',
        ]);
    }

    private function signature(array $parts): string
    {
        $normalised = collect($parts)->map(function ($part, $index) {
            return in_array($index, [2, 3, 4], true)
                ? number_format(round((float) $part, 2), 2, '.', '')
                : (string) $part;
        });

        return hash('sha256', $normalised->implode('|'));
    }

    private function isPhysicalMrpLot(MrpStockLot $lot): bool
    {
        return $lot->purchase_voucher !== 'OPENING-DEFICIT'
            && ((float) $lot->quantity > 0 || (float) $lot->available_quantity > 0);
    }

    private function isPhysicalBatch(StockBatch $batch): bool
    {
        return (float) $batch->quantity > 0 || (float) $batch->available_quantity > 0;
    }
}
