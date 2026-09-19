<?php

namespace Modules\Product\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Product\app\Models\MrpStockLot;
use Modules\Product\app\Models\MrpStockMovement;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\StockBatch;
use Modules\Settings\app\Models\Company;

class MrpInventoryService
{
    public function __construct(private ?InventoryLabelService $inventoryLabels = null) {}

    public function enabled(): bool
    {
        return (Company::query()->value('inventory_mode') ?? 'standard') === 'mrp';
    }

    public function bootstrapExistingStock(string $previousMode): void
    {
        if ($previousMode === 'batch') {
            StockBatch::where('available_quantity', '>', 0)
                ->orderBy('id')
                ->each(function (StockBatch $batch) {
                    $voucher = 'BATCH-'.$batch->id;
                    if (MrpStockLot::where('purchase_voucher', $voucher)->where('product_id', $batch->product_id)->exists()) {
                        return;
                    }
                    if ((float) $batch->mrp <= 0) {
                        throw ValidationException::withMessages([
                            'inventory_mode' => "Batch {$batch->batch_no} has stock but no MRP. Enter its MRP before enabling MRP-wise inventory.",
                        ]);
                    }

                    $date = $batch->created_at?->toDateString() ?? now()->toDateString();
                    $product = Product::where('product_code', $batch->product_id)->firstOrFail();
                    $pricing = $this->pricingFromProduct($product, (float) $batch->mrp);
                    $lot = MrpStockLot::create([
                        'product_id' => $batch->product_id,
                        'purchase_voucher' => $voucher,
                        'purchase_date' => $date,
                        'purchase_rate' => (float) $batch->purchase_rate,
                        'mrp' => (float) $batch->mrp,
                        ...$pricing,
                        'quantity' => (float) $batch->available_quantity,
                        'available_quantity' => (float) $batch->available_quantity,
                    ]);
                    $this->labelService()->ensureForMrpLot($lot);
                    $this->movement($lot, 'opening', 'opening', (int) $batch->id, null, $date, (float) $batch->available_quantity, 0);
                });
        }

        Product::with('stock')->orderBy('id')->each(function (Product $product) {
            $rawStock = (float) ($product->stock?->stock_qty ?? 0);
            $tracked = (float) MrpStockLot::where('product_id', $product->product_code)->sum('available_quantity');
            $difference = round($rawStock - $tracked, 2);
            if (abs($difference) <= 0.00001) {
                return;
            }

            if ($difference < 0) {
                $remaining = abs($difference);
                $lots = MrpStockLot::where('product_id', $product->product_code)
                    ->where('available_quantity', '>', 0)
                    ->orderBy('purchase_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    if ($remaining <= 0.00001) {
                        break;
                    }

                    $quantity = min($remaining, (float) $lot->available_quantity);
                    $lot->available_quantity = (float) $lot->available_quantity - $quantity;
                    $lot->save();
                    $this->movement($lot, 'mode_reconciliation', 'opening', 0, null, now()->toDateString(), 0, $quantity);
                    $remaining -= $quantity;
                }

                if ($remaining > 0.00001) {
                    $mrp = round((float) ($product->mrp ?? 0), 2);
                    if ($mrp <= 0) {
                        throw ValidationException::withMessages([
                            'inventory_mode' => "{$product->product} has negative stock and no MRP. Enter its MRP before enabling MRP-wise inventory.",
                        ]);
                    }

                    $date = now()->toDateString();
                    $deficit = MrpStockLot::where('product_id', $product->product_code)
                        ->where('purchase_voucher', 'OPENING-DEFICIT')
                        ->lockForUpdate()
                        ->first();
                    if (! $deficit) {
                        $pricing = $this->pricingFromProduct($product, $mrp);
                        $deficit = MrpStockLot::create([
                            'product_id' => $product->product_code,
                            'purchase_voucher' => 'OPENING-DEFICIT',
                            'purchase_date' => $date,
                            'purchase_rate' => (float) ($product->pprice ?? 0) / max((float) ($product->uqty ?: 1), 1),
                            'mrp' => $mrp,
                            ...$pricing,
                            'quantity' => 0,
                            'available_quantity' => 0,
                        ]);
                    }

                    $deficit->quantity = (float) $deficit->quantity - $remaining;
                    $deficit->available_quantity = (float) $deficit->available_quantity - $remaining;
                    $deficit->save();
                    $this->movement($deficit, 'opening_deficit', 'opening', 0, null, $date, 0, $remaining);
                }

                return;
            }
            if ((float) ($product->mrp ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'inventory_mode' => "{$product->product} has stock but no MRP. Enter its MRP before enabling MRP-wise inventory.",
                ]);
            }

            $date = now()->toDateString();
            $pricing = $this->pricingFromProduct($product, (float) $product->mrp);
            $lot = MrpStockLot::create([
                'product_id' => $product->product_code,
                'purchase_voucher' => 'OPENING-'.now()->format('Ymd'),
                'purchase_date' => $date,
                'purchase_rate' => (float) ($product->pprice ?? 0) / max((float) ($product->uqty ?: 1), 1),
                'mrp' => (float) $product->mrp,
                ...$pricing,
                'quantity' => $difference,
                'available_quantity' => $difference,
            ]);
            $this->labelService()->ensureForMrpLot($lot);
            $this->movement($lot, 'opening', 'opening', 0, null, $date, $difference, 0);
            $this->settleDeficits($lot, $difference, 'opening', 0, null, $date);
        });
    }

    public function receivePurchase(
        Product $product,
        array $item,
        int $purchaseId,
        int $detailId,
        string $voucher,
        string $date,
        float $rawQuantity
    ): ?MrpStockLot {
        if (! $this->enabled()) {
            return null;
        }

        $mrp = round((float) ($item['mrp'] ?? 0), 2);
        if ($mrp <= 0) {
            throw ValidationException::withMessages([
                'purchase_items' => "MRP is required for {$product->product} in MRP-wise inventory mode.",
            ]);
        }

        $unitQty = max((float) ($item['unit_qty'] ?? 1), 1);
        $purchaseRate = in_array(($item['unit'] ?? ''), ['No.s', 'Nos.'], true)
            ? (float) ($item['unit_price'] ?? 0)
            : (float) ($item['unit_price'] ?? 0) / $unitQty;

        $lot = MrpStockLot::create([
            'product_id' => $product->product_code,
            'purchase_id' => $purchaseId,
            'purchase_detail_id' => $detailId,
            'purchase_voucher' => $voucher,
            'purchase_date' => $date,
            'purchase_rate' => $purchaseRate,
            'mrp' => $mrp,
            'sale_price' => round((float) ($item['sale_price'] ?? $product->price ?? $mrp), 2),
            'margin_percentage' => round((float) ($item['margin_percentage'] ?? 0), 2),
            'margin_amount' => round((float) ($item['margin_amount'] ?? 0), 2),
            'quantity' => $rawQuantity,
            'available_quantity' => $rawQuantity,
        ]);

        $this->labelService()->ensureForMrpLot($lot);

        $this->movement($lot, 'purchase', 'purchase', $purchaseId, $detailId, $date, $rawQuantity, 0);
        $this->settleDeficits($lot, $rawQuantity, 'purchase', $purchaseId, $detailId, $date);

        return $lot;
    }

    public function receiveOpening(
        Product $product,
        array $item,
        int $referenceId,
        string $date,
        float $rawQuantity
    ): ?MrpStockLot {
        if (! $this->enabled()) {
            return null;
        }

        $mrp = round((float) ($item['mrp'] ?? 0), 2);
        if ($mrp <= 0) {
            throw ValidationException::withMessages([
                'opening_stock' => "MRP is required for the opening stock of {$product->product}.",
            ]);
        }

        $salePrice = round((float) ($item['sale_price'] ?? $product->price ?? $mrp), 2);
        if ($salePrice < 0 || $salePrice > $mrp) {
            throw ValidationException::withMessages([
                'opening_stock' => "Sale price must be between zero and MRP {$mrp} for {$product->product}.",
            ]);
        }

        $marginAmount = round($mrp - $salePrice, 2);
        $lot = MrpStockLot::create([
            'product_id' => $product->product_code,
            'purchase_voucher' => 'OPENING-'.$product->product_code.'-'.now()->format('YmdHisv'),
            'purchase_date' => $date,
            'purchase_rate' => round((float) ($item['purchase_rate'] ?? 0), 2),
            'mrp' => $mrp,
            'sale_price' => $salePrice,
            'margin_percentage' => $mrp > 0 ? round(($marginAmount / $mrp) * 100, 2) : 0,
            'margin_amount' => $marginAmount,
            'quantity' => $rawQuantity,
            'available_quantity' => $rawQuantity,
        ]);

        $this->labelService()->ensureForMrpLot($lot);
        $this->movement($lot, 'opening', 'opening', $referenceId, null, $date, $rawQuantity, 0);

        return $lot;
    }

    public function applySalePricing(array $items, bool $useMrpAsUnitPrice): array
    {
        if (! $this->enabled()) {
            return $items;
        }

        $products = Product::whereIn('product_code', collect($items)->pluck('product_id')->filter()->unique())
            ->get()
            ->keyBy('product_code');

        return collect($items)->map(function (array $item, int $index) use ($products, $useMrpAsUnitPrice) {
            $product = $products->get((string) ($item['product_id'] ?? ''));
            $lotId = ! empty($item['mrp_stock_lot_id']) ? (int) $item['mrp_stock_lot_id'] : null;

            if (! $product || ! $lotId) {
                return $item;
            }

            $lot = MrpStockLot::whereKey($lotId)
                ->where('product_id', $product->product_code)
                ->first();

            if (! $lot) {
                throw ValidationException::withMessages([
                    "sale_items.$index.mrp_stock_lot_id" => "The selected MRP stock lot does not belong to {$product->product}.",
                ]);
            }

            $packagePrice = $useMrpAsUnitPrice ? (float) $lot->mrp : (float) $lot->sale_price;
            $unitQty = max((float) ($product->uqty ?: 1), 1);
            $isLooseUnit = in_array((string) ($item['unit'] ?? ''), ['No.s', 'Nos.'], true);
            $item['unit_price'] = round($isLooseUnit && $unitQty > 1 ? $packagePrice / $unitQty : $packagePrice, 2);

            return $item;
        })->all();
    }

    public function allocateSale(
        Product $product,
        float $rawQuantity,
        int $referenceId,
        int $detailId,
        string $date,
        ?int $selectedLotId,
        float $saleRate = 0,
        string $referenceType = 'sale',
        string $movementType = 'sale',
        bool $allowOutOfStock = false
    ): Collection {
        if (! $this->enabled()) {
            return collect();
        }

        if (! $selectedLotId) {
            throw ValidationException::withMessages([
                'sale_items' => "Select an MRP stock lot for {$product->product}.",
            ]);
        }

        $selectedLot = MrpStockLot::whereKey($selectedLotId)
            ->where('product_id', $product->product_code)
            ->first();

        if (! $selectedLot) {
            throw ValidationException::withMessages([
                'sale_items' => "The selected MRP stock lot does not belong to {$product->product}.",
            ]);
        }

        $label = $this->labelService()->ensureForMrpLot($selectedLot);
        $slotLotIds = $label
            ? $this->labelService()->mrpLotsForLabel($label)->pluck('id')
            : collect([$selectedLot->id]);
        $lots = MrpStockLot::whereIn('id', $slotLotIds)
            ->where('product_id', $product->product_code)
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $available = (float) $lots->sum('available_quantity');

        if ($available + 0.00001 < $rawQuantity) {
            throw ValidationException::withMessages([
                'sale_items' => "Insufficient stock in the selected pricing slot at MRP {$selectedLot->mrp}. Available: {$available}.",
            ]);
        }

        $maxRawRate = (float) $selectedLot->mrp / max((float) ($product->uqty ?: 1), 1);
        if ($saleRate > $maxRawRate + 0.00001) {
            throw ValidationException::withMessages([
                'sale_items' => "Sale rate cannot exceed MRP {$selectedLot->mrp} for {$product->product}.",
            ]);
        }

        $remaining = $rawQuantity;
        $movements = collect();
        foreach ($lots as $lot) {
            if ($remaining <= 0.00001) {
                break;
            }

            $quantity = min($remaining, max((float) $lot->available_quantity, 0));
            if ($quantity <= 0) {
                continue;
            }

            $lot->available_quantity = (float) $lot->available_quantity - $quantity;
            $lot->save();
            $movements->push($this->movement(
                $lot, $movementType, $referenceType, $referenceId, $detailId, $date, 0, $quantity, $saleRate
            ));
            $remaining -= $quantity;
        }

        return $movements;
    }

    public function reverseReference(string $referenceType, int $referenceId, string $date): void
    {
        if (! $this->enabled()) {
            return;
        }

        $movements = MrpStockMovement::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereNull('reversal_of_id')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($movements as $movement) {
            if (MrpStockMovement::where('reversal_of_id', $movement->id)->exists()) {
                continue;
            }

            $lot = MrpStockLot::whereKey($movement->mrp_stock_lot_id)->lockForUpdate()->firstOrFail();
            $net = (float) $movement->quantity_in - (float) $movement->quantity_out;
            $newAvailable = (float) $lot->available_quantity - $net;
            $isDeficitLot = $lot->purchase_voucher === 'OPENING-DEFICIT';
            if ($newAvailable < -0.00001 && ! $isDeficitLot) {
                throw ValidationException::withMessages([
                    'mrp_stock' => "MRP lot {$lot->purchase_voucher} has already been used and cannot be reversed.",
                ]);
            }

            $lot->available_quantity = $isDeficitLot ? $newAvailable : max(0, $newAvailable);
            if ($movement->movement_type === 'purchase') {
                $lot->quantity = max(0, (float) $lot->quantity - (float) $movement->quantity_in);
            } elseif ($movement->movement_type === 'purchase_return') {
                $lot->quantity = (float) $lot->quantity + (float) $movement->quantity_out;
            } elseif ($movement->movement_type === 'deficit_settlement' && $isDeficitLot) {
                $lot->quantity = (float) $lot->quantity - $net;
            }
            $lot->save();

            $this->movement(
                $lot,
                $movement->movement_type.'_reversal',
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
        if (! $this->enabled()) {
            return;
        }

        $sales = MrpStockMovement::where('reference_type', 'sale')
            ->where('reference_detail_id', $saleDetailId)
            ->where('movement_type', 'sale')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $alreadyReturned = (float) MrpStockMovement::where('movement_type', 'sale_return')
            ->whereIn('reversal_of_id', $sales->pluck('id'))
            ->whereNotIn('id', MrpStockMovement::query()->select('reversal_of_id')->whereNotNull('reversal_of_id'))
            ->sum('quantity_in');
        $sold = (float) $sales->sum('quantity_out');
        if ($rawQuantity > ($sold - $alreadyReturned) + 0.00001) {
            throw ValidationException::withMessages(['return_items' => 'Sale return exceeds the MRP-lot quantity sold.']);
        }

        $remaining = $rawQuantity;
        foreach ($sales as $saleMovement) {
            if ($remaining <= 0) {
                break;
            }

            $returned = (float) MrpStockMovement::where('reversal_of_id', $saleMovement->id)
                ->where('movement_type', 'sale_return')
                ->whereNotIn('id', MrpStockMovement::query()->select('reversal_of_id')->whereNotNull('reversal_of_id'))
                ->sum('quantity_in');
            $quantity = min($remaining, (float) $saleMovement->quantity_out - $returned);
            if ($quantity <= 0) {
                continue;
            }

            $lot = MrpStockLot::whereKey($saleMovement->mrp_stock_lot_id)->lockForUpdate()->firstOrFail();
            $lot->increment('available_quantity', $quantity);
            $lot->refresh();
            $this->movement(
                $lot, 'sale_return', 'sale_return', $returnId, $returnDetailId,
                $date, $quantity, 0, (float) $saleMovement->sale_rate, $saleMovement->id
            );
            $remaining -= $quantity;
        }
    }

    public function manualSaleReturn(
        Product $product,
        float $rawQuantity,
        int $returnId,
        int $returnDetailId,
        string $date,
        ?float $selectedMrp = null
    ): ?MrpStockLot {
        if (! $this->enabled()) {
            return null;
        }

        $mrp = round((float) ($selectedMrp ?? $product->mrp ?? 0), 2);
        if ($mrp <= 0) {
            throw ValidationException::withMessages([
                'return_items' => "MRP is required for the returned {$product->product}.",
            ]);
        }
        $lot = MrpStockLot::where('product_id', $product->product_code)
            ->where('purchase_voucher', 'MANUAL-RETURN')
            ->where('mrp', $mrp)
            ->lockForUpdate()
            ->first();

        if (! $lot) {
            $pricing = $this->pricingFromProduct($product, $mrp);
            $lot = MrpStockLot::create([
                'product_id' => $product->product_code,
                'purchase_voucher' => 'MANUAL-RETURN',
                'purchase_date' => $date,
                'purchase_rate' => (float) ($product->pprice ?? 0) / max((float) ($product->uqty ?: 1), 1),
                'mrp' => $mrp,
                ...$pricing,
                'quantity' => 0,
                'available_quantity' => 0,
            ]);
        }

        $lot->available_quantity = (float) $lot->available_quantity + $rawQuantity;
        $lot->quantity = (float) $lot->quantity + $rawQuantity;
        $lot->save();
        $this->labelService()->ensureForMrpLot($lot);
        $this->movement($lot, 'sale_return', 'sale_return', $returnId, $returnDetailId, $date, $rawQuantity, 0);

        return $lot;
    }

    public function purchaseReturn(MrpStockLot $lot, float $rawQuantity, int $returnId, int $returnDetailId, string $date): void
    {
        $lot = MrpStockLot::whereKey($lot->id)->lockForUpdate()->firstOrFail();
        if ((float) $lot->available_quantity + 0.00001 < $rawQuantity) {
            throw ValidationException::withMessages([
                'return_items' => "Return exceeds available quantity in MRP lot {$lot->purchase_voucher}.",
            ]);
        }

        $lot->available_quantity = (float) $lot->available_quantity - $rawQuantity;
        $lot->quantity = max(0, (float) $lot->quantity - $rawQuantity);
        $lot->save();
        $this->movement($lot, 'purchase_return', 'purchase_return', $returnId, $returnDetailId, $date, 0, $rawQuantity);
    }

    public function saleSelectableLots(string $productCode): Collection
    {
        return MrpStockLot::where('product_id', $productCode)
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get();
    }

    public function availableLots(string $productCode): Collection
    {
        return MrpStockLot::where('product_id', $productCode)
            ->where('available_quantity', '>', 0)
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get();
    }

    private function settleDeficits(
        MrpStockLot $incomingLot,
        float $incomingQuantity,
        string $referenceType,
        int $referenceId,
        ?int $detailId,
        string $date
    ): void {
        $remaining = $incomingQuantity;
        $deficits = MrpStockLot::where('product_id', $incomingLot->product_id)
            ->where('purchase_voucher', 'OPENING-DEFICIT')
            ->where('available_quantity', '<', 0)
            ->where('id', '!=', $incomingLot->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($deficits as $deficit) {
            if ($remaining <= 0.00001) {
                break;
            }

            $quantity = min($remaining, abs((float) $deficit->available_quantity));

            $incomingLot->available_quantity = (float) $incomingLot->available_quantity - $quantity;
            $incomingLot->save();
            $this->movement(
                $incomingLot, 'deficit_settlement', $referenceType, $referenceId, $detailId, $date, 0, $quantity
            );

            $deficit->available_quantity = min(0, (float) $deficit->available_quantity + $quantity);
            $deficit->quantity = min(0, (float) $deficit->quantity + $quantity);
            $deficit->save();
            $this->movement(
                $deficit, 'deficit_settlement', $referenceType, $referenceId, $detailId, $date, $quantity, 0
            );

            $remaining -= $quantity;
        }
    }

    private function movement(
        MrpStockLot $lot,
        string $type,
        string $referenceType,
        int $referenceId,
        ?int $detailId,
        string $date,
        float $in,
        float $out,
        float $saleRate = 0,
        ?int $reversalOf = null
    ): MrpStockMovement {
        return MrpStockMovement::create([
            'mrp_stock_lot_id' => $lot->id,
            'product_id' => $lot->product_id,
            'movement_type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_detail_id' => $detailId,
            'movement_date' => $date,
            'quantity_in' => $in,
            'quantity_out' => $out,
            'balance_after' => $lot->available_quantity,
            'purchase_rate' => $lot->purchase_rate,
            'mrp' => $lot->mrp,
            'sale_rate' => $saleRate,
            'reversal_of_id' => $reversalOf,
        ]);
    }

    private function pricingFromProduct(Product $product, float $mrp): array
    {
        $salePrice = round(max(0, min($mrp, (float) ($product->price ?? $mrp))), 2);
        $marginAmount = round(max(0, $mrp - $salePrice), 2);

        return [
            'sale_price' => $salePrice,
            'margin_percentage' => $mrp > 0 ? round(($marginAmount / $mrp) * 100, 2) : 0,
            'margin_amount' => $marginAmount,
        ];
    }

    private function labelService(): InventoryLabelService
    {
        return $this->inventoryLabels ??= app(InventoryLabelService::class);
    }
}
