<?php

namespace Modules\Returns\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\app\Models\Balance;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\DailyBank;
use Modules\Finance\app\Models\LedgerBook;
use Modules\Finance\app\Models\StockValue;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\Stock;
use Modules\Product\app\Models\StockBatch;
use Modules\Product\app\Models\MrpStockLot;
use Modules\Product\app\Models\StockLedger;
use Modules\Product\app\Services\BatchInventoryService;
use Modules\Product\app\Services\MrpInventoryService;
use Modules\Purchase\app\Models\Purchase;
use Modules\Purchase\app\Models\PurchaseDetail;
use Modules\Returns\app\Models\ReturnDetail;
use Modules\Returns\app\Models\Returns;

class PurchaseReturnService
{
    public function __construct(
        private readonly BatchInventoryService $batchInventory,
        private readonly MrpInventoryService $mrpInventory
    )
    {
    }

    public function save(array $data): Returns
    {
        return DB::transaction(function () use ($data) {
            $returnDate = Carbon::createFromFormat('d/m/Y', $data['pr_date'])->toDateString();
            $calculation = $this->calculate($data['return_items']);
            $amountPaid = $this->validatedAmountPaid($data, $calculation['amount_payable']);
            $purchase = $this->originalPurchase($data['pr_pvno'] ?? null);

            if ($purchase) {
                $this->assertOriginalPurchaseMatches($purchase, (int) $data['pr_vendor']);
                $this->assertReturnableQuantities($purchase, $calculation['items'], $data['pr_id'] ?? null);
            }

            $return = !empty($data['pr_id'])
                ? Returns::whereKey($data['pr_id'])->lockForUpdate()->firstOrFail()
                : null;

            if ($return) {
                $this->assertActivePurchaseReturn($return);
                $oldDetails = ReturnDetail::where('prd_prid', $return->pr_id)->lockForUpdate()->get();
                $oldVendor = (int) $return->pr_vendor;
                $oldDate = $return->pr_date;
                $oldPaymode = (int) $return->pr_paymode;
                $oldPaid = (float) $return->pr_amount_paid;

                $this->lockOperationalRows($calculation['items'], $oldDetails->pluck('prd_itemid')->all(), (int) $data['pr_paymode'], $oldPaymode);
                $this->reverseStock($return, $oldDetails, $oldDate);
                $this->batchInventory->reverseReference('purchase_return', (int) $return->pr_id, $oldDate);
                $this->mrpInventory->reverseReference('purchase_return', (int) $return->pr_id, $oldDate);
                $return->returnDetails()->delete();
                $this->removeFinancialEntries($return, $oldVendor, $oldDate);
                // Balance::updateDebitBalance($oldDate, $oldPaymode, $oldPaid);
                Banking::updateDebitBanking($oldPaymode, $oldPaid, $oldDate);

                $return->update($this->returnData($data, $calculation, $returnDate, $amountPaid));
            } else {
                $this->lockOperationalRows($calculation['items'], [], (int) $data['pr_paymode']);
                $return = Returns::create($this->returnData($data, $calculation, $returnDate, $amountPaid, $data['pr_vno']));
            }

            $this->createDetailsAndApplyStock($return, $calculation['items'], $returnDate, $purchase);
            $this->applyFinancialEntries($return);
            $this->refreshClosings();

            return $return->fresh(['returnDetails.product', 'vendor', 'user', 'banking']);
        }, 5);
    }

    public function cancel(Returns $return): void
    {
        DB::transaction(function () use ($return) {
            $return = Returns::whereKey($return->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActivePurchaseReturn($return);

            $details = ReturnDetail::where('prd_prid', $return->pr_id)->lockForUpdate()->get();
            $this->lockOperationalRows([], $details->pluck('prd_itemid')->all(), (int) $return->pr_paymode);

            LedgerBook::releaseVendorPaymentAllocationsForSource($return->pr_vendor, $return->pr_id, ['pr']);
            LedgerBook::where('lb_vid', $return->pr_id)
                ->whereIn('lb_type', ['pr', 'prp'])
                ->update([
                    'lb_amount' => 0,
                    'lb_tramount' => 0,
                    'status' => '0',
                ]);
            LedgerBook::recalculateVendorLedger($return->pr_vendor, $return->pr_date, null);

            // Balance::updateDebitBalance($return->pr_date, $return->pr_paymode, $return->pr_amount_paid);
            Banking::updateDebitBanking($return->pr_paymode, $return->pr_amount_paid, $return->pr_date);
            $this->reverseStock($return, $details, $return->pr_date);
            $this->batchInventory->reverseReference('purchase_return', (int) $return->pr_id, $return->pr_date);
            $this->mrpInventory->reverseReference('purchase_return', (int) $return->pr_id, $return->pr_date);

            $return->status = '0';
            $return->save();
            $this->refreshClosings();
        }, 5);
    }

    private function calculate(array $items): array
    {
        $products = Product::whereIn('product_code', collect($items)->pluck('product_id')->unique())
            ->get()
            ->keyBy('product_code');

        $normalised = collect($items)->map(function (array $item) use ($products) {
            $product = $products->get($item['product_id']);
            if (!$product) {
                throw ValidationException::withMessages(['return_items' => "Invalid product {$item['product_id']}."]);
            }

            $quantity = round((float) $item['quantity'], 2);
            $unitQty = max((float) ($item['unit_qty'] ?? $product->uqty ?: 1), 1);
            $unitPrice = round((float) $item['unit_price'], 2);
            $total = round($unitPrice * $quantity, 2);
            $gstPercent = (float) ($product->gst ?? 0);
            $taxable = $gstPercent > 0 ? round($total / (1 + ($gstPercent / 100)), 2) : $total;
            $gstValue = round($total - $taxable, 2);

            return [
                'product_id' => $product->product_code,
                'product' => $product,
                'hsn_code' => $item['hsn_code'] ?? $product->hsn_code,
                'quantity' => $quantity,
                'unit' => $item['unit'],
                'unit_price' => $unitPrice,
                'unit_qty' => $unitQty,
                'raw_quantity' => $this->rawQuantity($quantity, (string) $item['unit'], $unitQty),
                'gst_value' => $gstValue,
                'total' => $total,
                'stock_batch_id' => $item['stock_batch_id'] ?? null,
                'batch_no' => $item['batch_no'] ?? null,
                'mrp_stock_lot_id' => $item['mrp_stock_lot_id'] ?? null,
                'stock_mrp' => $item['stock_mrp'] ?? null,
            ];
        })->values();

        $amount = round((float) $normalised->sum('total'), 2);
        $amountPayable = round($amount);

        return [
            'items' => $normalised->all(),
            'amount' => $amount,
            'gst' => round((float) $normalised->sum('gst_value'), 2),
            'amount_payable' => $amountPayable,
            'round' => round($amountPayable - $amount, 2),
        ];
    }

    private function returnData(array $data, array $calculation, string $returnDate, float $amountPaid, ?string $voucher = null): array
    {
        $balance = round($calculation['amount_payable'] - $amountPaid, 2);
        $result = [
            'pr_date' => $returnDate,
            'pr_pvno' => $data['pr_pvno'] ?? null,
            'pr_vendor' => $data['pr_vendor'],
            'pr_amount' => $calculation['amount'],
            'pr_gst' => $calculation['gst'],
            'pr_amount_payable' => $calculation['amount_payable'],
            'pr_round' => $calculation['round'],
            'pr_amount_paid' => $amountPaid,
            'pr_balance' => $balance,
            'pr_paymode' => $data['pr_paymode'],
            'pr_type' => 1,
            'pr_paid' => $balance <= 0 ? 'FP' : 'NP',
            'pr_user' => Auth::id() ?: ($data['pr_user'] ?? null),
        ];

        if ($voucher !== null) {
            $result['pr_vno'] = $voucher;
        }

        return $result;
    }

    private function createDetailsAndApplyStock(Returns $return, array $items, string $returnDate, ?Purchase $purchase): void
    {
        foreach ($items as $item) {
            $batch = $this->resolveBatch($item);
            $mrpLot = $this->resolveMrpLot($item);
            $detail = ReturnDetail::create([
                'prd_prid' => $return->pr_id,
                'prd_itemid' => $item['product_id'],
                'stock_batch_id' => $batch?->id,
                'batch_no' => $batch?->batch_no ?? $item['batch_no'],
                'mrp_stock_lot_id' => $mrpLot?->id,
                'stock_mrp' => $mrpLot?->mrp ?? ($item['stock_mrp'] ?? null),
                'prd_hsn' => $item['hsn_code'],
                'prd_itemqty' => $item['quantity'],
                'prd_unit' => $item['unit'],
                'prd_uprice' => $item['unit_price'],
                'prd_uqty' => $item['unit_qty'],
                'prd_gst' => $item['gst_value'],
                'prd_total' => $item['total'],
            ]);

            Stock::updateStock($returnDate, $item['product_id'], -$item['raw_quantity']);
            StockLedger::updateStockLedger($returnDate, $item['product_id'], -$item['raw_quantity'], $return->pr_id, 'pr');

            if ($this->batchInventory->enabled($item['product'])) {
                if (!$batch) {
                    throw ValidationException::withMessages([
                        'return_items' => "Select a batch for {$item['product']->product}.",
                    ]);
                }

                $this->batchInventory->purchaseReturn(
                    $batch,
                    (float) $item['raw_quantity'],
                    (int) $return->pr_id,
                    (int) $detail->prd_id,
                    $returnDate
                );
            }

            if ($this->mrpInventory->enabled()) {
                if (!$mrpLot) {
                    throw ValidationException::withMessages([
                        'return_items' => "Select an MRP stock lot for {$item['product']->product}.",
                    ]);
                }

                $this->mrpInventory->purchaseReturn(
                    $mrpLot,
                    (float) $item['raw_quantity'],
                    (int) $return->pr_id,
                    (int) $detail->prd_id,
                    $returnDate
                );
            }
        }
    }

    private function reverseStock(Returns $return, Collection $details, string $date): void
    {
        foreach ($details as $item) {
            $quantity = $this->rawQuantity((float) $item->prd_itemqty, (string) $item->prd_unit, (float) $item->prd_uqty);
            Stock::updateStock($date, $item->prd_itemid, $quantity);
            StockLedger::updateStockLedger($date, $item->prd_itemid, $quantity, $return->pr_id, 'prd');
        }
    }

    private function applyFinancialEntries(Returns $return): void
    {
        LedgerBook::setPurchaseReturnLedgerBook(
            $return->pr_amount_payable,
            $return->pr_amount_paid,
            $return->pr_date,
            $return->pr_id,
            $return->pr_paymode,
            $return->pr_vendor,
            $return->pr_vno
        );
        LedgerBook::recalculateVendorLedger($return->pr_vendor, $return->pr_date, null);
        // Balance::updateCreditBalance($return->pr_date, $return->pr_paymode, $return->pr_amount_paid);
        Banking::updateCreditBanking($return->pr_paymode, $return->pr_amount_paid, $return->pr_date);
    }

    private function removeFinancialEntries(Returns $return, int $vendorId, string $date): void
    {
        LedgerBook::releaseVendorPaymentAllocationsForSource($vendorId, $return->pr_id, ['pr']);
        LedgerBook::where('lb_vid', $return->pr_id)->whereIn('lb_type', ['pr', 'prp'])->delete();
        LedgerBook::recalculateVendorLedger($vendorId, $date, null);
    }

    private function originalPurchase(?string $voucher): ?Purchase
    {
        $voucher = trim((string) $voucher);
        if ($voucher === '') {
            return null;
        }

        return Purchase::with('purchaseDetails.product')
            ->where('pu_status', '1')
            ->where(function ($query) use ($voucher) {
                $query->where('pu_vno', $voucher)
                    ->orWhere('pu_bill_number', $voucher);
            })
            ->first();
    }

    private function assertOriginalPurchaseMatches(Purchase $purchase, int $vendorId): void
    {
        if ((int) $purchase->pu_vendor !== $vendorId) {
            throw ValidationException::withMessages([
                'pr_vendor' => "Supplier does not match original purchase {$purchase->pu_vno}.",
            ]);
        }
    }

    private function assertReturnableQuantities(Purchase $purchase, array $items, ?int $currentReturnId): void
    {
        $requested = collect($items)->groupBy('product_id')->map(fn (Collection $rows) => round((float) $rows->sum('raw_quantity'), 2));
        $purchased = $purchase->purchaseDetails
            ->groupBy('pud_itemid')
            ->map(fn (Collection $rows) => round((float) $rows->sum(fn (PurchaseDetail $detail) => $this->rawQuantity((float) $detail->pud_itemqty, (string) $detail->pud_unit, (float) $detail->pud_uqty)), 2));

        $alreadyReturned = ReturnDetail::query()
            ->whereHas('return', function ($query) use ($purchase, $currentReturnId) {
                $query->where('pr_type', 1)
                    ->where('status', '1')
                    ->where(function ($q) use ($purchase) {
                        $q->where('pr_pvno', $purchase->pu_vno);

                        if ($purchase->pu_bill_number) {
                            $q->orWhere('pr_pvno', $purchase->pu_bill_number);
                        }
                    });

                if ($currentReturnId) {
                    $query->where('pr_id', '!=', $currentReturnId);
                }
            })
            ->get()
            ->groupBy('prd_itemid')
            ->map(fn (Collection $rows) => round((float) $rows->sum(fn (ReturnDetail $detail) => $this->rawQuantity((float) $detail->prd_itemqty, (string) $detail->prd_unit, (float) $detail->prd_uqty)), 2));

        foreach ($requested as $productCode => $quantity) {
            $available = (float) ($purchased->get($productCode, 0) - $alreadyReturned->get($productCode, 0));
            if ($quantity > $available + 0.00001) {
                throw ValidationException::withMessages([
                    'return_items' => "Return quantity for {$productCode} exceeds original purchase balance. Available: {$available}.",
                ]);
            }
        }
    }

    private function resolveBatch(array $item): ?StockBatch
    {
        if (!$this->batchInventory->enabled($item['product'])) {
            return null;
        }

        if (!empty($item['stock_batch_id'])) {
            return StockBatch::findOrFail($item['stock_batch_id']);
        }

        if (!empty($item['batch_no'])) {
            return StockBatch::where('product_id', $item['product_id'])
                ->where('batch_no', $item['batch_no'])
                ->first();
        }

        return null;
    }

    private function resolveMrpLot(array $item): ?MrpStockLot
    {
        if (!$this->mrpInventory->enabled() || empty($item['mrp_stock_lot_id'])) {
            return null;
        }

        return MrpStockLot::whereKey($item['mrp_stock_lot_id'])
            ->where('product_id', $item['product_id'])
            ->firstOrFail();
    }

    private function validatedAmountPaid(array $data, float $amountPayable): float
    {
        $amountPaid = round((float) ($data['pr_amount_paid'] ?? 0), 2);

        if ($amountPaid > $amountPayable) {
            throw ValidationException::withMessages([
                'pr_amount_paid' => 'Amount paid cannot exceed the purchase return amount.',
            ]);
        }

        return $amountPaid;
    }

    private function rawQuantity(float $quantity, string $unit, float $unitQty): float
    {
        return in_array($unit, ['No.s', 'Nos.'], true) ? $quantity : round($quantity * max($unitQty, 1), 2);
    }

    private function lockOperationalRows(array $newItems, array $oldProductCodes, int $paymode, ?int $oldPaymode = null): void
    {
        $productCodes = collect($newItems)->pluck('product_id')->merge($oldProductCodes)->filter()->unique()->values();

        if ($productCodes->isNotEmpty()) {
            Stock::whereIn('stock_product_id', $productCodes)->orderBy('stock_id')->lockForUpdate()->get();
        }

        $paymodes = collect([$paymode, $oldPaymode])->filter()->unique()->values();
        Banking::whereIn('bk_id', $paymodes)->lockForUpdate()->get();
        // Balance::whereIn('balance_pmode', $paymodes)->lockForUpdate()->get();
    }

    private function assertActivePurchaseReturn(Returns $return): void
    {
        if ((int) $return->pr_type !== 1 || (string) $return->status !== '1') {
            throw ValidationException::withMessages([
                'purchase_return' => 'This purchase return is already cancelled or invalid.',
            ]);
        }
    }

    private function refreshClosings(): void
    {
        StockValue::stockClosing();
    }
}