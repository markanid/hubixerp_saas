<?php

namespace Modules\Returns\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Finance\app\Models\Balance;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\DailyBank;
use Modules\Finance\app\Models\LedgerBook;
use Modules\Finance\app\Models\StockValue;
use Modules\Product\app\Models\BatchMovement;
use Modules\Product\app\Models\MrpStockMovement;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\Stock;
use Modules\Product\app\Models\StockLedger;
use Modules\Product\app\Services\BatchInventoryService;
use Modules\Product\app\Services\MrpInventoryService;
use Modules\Returns\app\Models\ReturnDetail;
use Modules\Returns\app\Models\Returns;
use Modules\Sale\app\Models\Sale;
use Modules\Sale\app\Models\SaleDetail;

class SaleReturnService
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
            $sale = $this->originalSale($data['pr_pvno'] ?? null);

            if ($sale) {
                $this->assertOriginalSaleMatches($sale, (int) $data['pr_vendor']);
                $this->assertReturnableQuantities($sale, $calculation['items'], $data['pr_id'] ?? null);
            }

            $return = !empty($data['pr_id'])
                ? Returns::whereKey($data['pr_id'])->lockForUpdate()->firstOrFail()
                : null;

            if ($return) {
                $this->assertActiveSaleReturn($return);
                $oldDetails = ReturnDetail::where('prd_prid', $return->pr_id)->lockForUpdate()->get();
                $oldCustomer = (int) $return->pr_vendor;
                $oldDate = $return->pr_date;
                $oldPaymode = (int) $return->pr_paymode;
                $oldPaid = (float) $return->pr_amount_paid;

                $this->lockOperationalRows($calculation['items'], $oldDetails->pluck('prd_itemid')->all(), (int) $data['pr_paymode'], $oldPaymode);
                $this->reverseStock($return, $oldDetails, $oldDate);
                $this->batchInventory->reverseReference('sale_return', (int) $return->pr_id, $oldDate);
                $this->mrpInventory->reverseReference('sale_return', (int) $return->pr_id, $oldDate);
                $return->returnDetails()->delete();
                $this->removeFinancialEntries($return, $oldCustomer, $oldDate);
                // Balance::updateCreditBalance($oldDate, $oldPaymode, $oldPaid);
                Banking::updateCreditBanking($oldPaymode, $oldPaid, $oldDate);

                $return->update($this->returnData($data, $calculation, $returnDate, $amountPaid));
            } else {
                $this->lockOperationalRows($calculation['items'], [], (int) $data['pr_paymode']);
                $return = Returns::create($this->returnData($data, $calculation, $returnDate, $amountPaid, $data['pr_vno']));
            }

            $this->createDetailsAndApplyStock($return, $calculation['items'], $returnDate, $sale);
            $this->applyFinancialEntries($return);
            $this->refreshClosings();

            return $return->fresh(['returnDetails.product', 'customer', 'user', 'banking']);
        }, 5);
    }

    public function cancel(Returns $return): void
    {
        DB::transaction(function () use ($return) {
            $return = Returns::whereKey($return->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActiveSaleReturn($return);

            $details = ReturnDetail::where('prd_prid', $return->pr_id)->lockForUpdate()->get();
            $this->lockOperationalRows([], $details->pluck('prd_itemid')->all(), (int) $return->pr_paymode);

            LedgerBook::releaseCustomerPaymentAllocationsForSource($return->pr_vendor, $return->pr_id, ['sr']);
            LedgerBook::where('lb_vid', $return->pr_id)
                ->whereIn('lb_type', ['sr', 'srp'])
                ->update([
                    'lb_amount' => 0,
                    'lb_tramount' => 0,
                    'status' => '0',
                ]);
            LedgerBook::recalculateCustomerLedger($return->pr_vendor);

            // Balance::updateCreditBalance($return->pr_date, $return->pr_paymode, $return->pr_amount_paid);
            Banking::updateCreditBanking($return->pr_paymode, $return->pr_amount_paid, $return->pr_date);
            $this->reverseStock($return, $details, $return->pr_date);
            $this->batchInventory->reverseReference('sale_return', (int) $return->pr_id, $return->pr_date);
            $this->mrpInventory->reverseReference('sale_return', (int) $return->pr_id, $return->pr_date);

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
                'mrp_stock_lot_id' => !empty($item['mrp_stock_lot_id']) ? (int) $item['mrp_stock_lot_id'] : null,
                'stock_mrp' => isset($item['stock_mrp']) ? (float) $item['stock_mrp'] : null,
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
            'pr_type' => 2,
            'pr_paid' => $balance <= 0 ? 'FP' : 'NP',
            'pr_user' => Auth::id() ?: ($data['pr_user'] ?? null),
        ];

        if (Schema::hasColumn('preturn', 'pr_state_code')) {
            $result['pr_state_code'] = $data['pr_state_code'] ?? null;
        }

        if (Schema::hasColumn('preturn', 'pr_is_igst')) {
            $result['pr_is_igst'] = (bool) ($data['pr_is_igst'] ?? false);
        }

        if ($voucher !== null) {
            $result['pr_vno'] = $voucher;
        }

        return $result;
    }

    private function createDetailsAndApplyStock(Returns $return, array $items, string $returnDate, ?Sale $sale): void
    {
        foreach ($items as $item) {
            $detail = ReturnDetail::create([
                'prd_prid' => $return->pr_id,
                'prd_itemid' => $item['product_id'],
                'mrp_stock_lot_id' => $item['mrp_stock_lot_id'],
                'stock_mrp' => $item['stock_mrp'],
                'prd_hsn' => $item['hsn_code'],
                'prd_itemqty' => $item['quantity'],
                'prd_unit' => $item['unit'],
                'prd_uprice' => $item['unit_price'],
                'prd_uqty' => $item['unit_qty'],
                'prd_gst' => $item['gst_value'],
                'prd_total' => $item['total'],
            ]);

            Stock::updateStock($returnDate, $item['product_id'], $item['raw_quantity']);
            StockLedger::updateStockLedger($returnDate, $item['product_id'], $item['raw_quantity'], $return->pr_id, 'sr');

            if ($this->batchInventory->enabled($item['product'])) {
                if ($sale) {
                    $this->applyOriginalSaleBatchReturn($sale, $item, $return, $detail, $returnDate);
                } else {
                    $this->batchInventory->manualSaleReturn(
                        $item['product'],
                        (float) $item['raw_quantity'],
                        (int) $return->pr_id,
                        (int) $detail->prd_id,
                        $returnDate
                    );
                }
            }

            if ($this->mrpInventory->enabled()) {
                if ($sale) {
                    $this->applyOriginalSaleMrpReturn($sale, $item, $return, $detail, $returnDate);
                } else {
                    $lot = $this->mrpInventory->manualSaleReturn(
                        $item['product'],
                        (float) $item['raw_quantity'],
                        (int) $return->pr_id,
                        (int) $detail->prd_id,
                        $returnDate,
                        $item['stock_mrp']
                    );
                    $detail->update(['mrp_stock_lot_id' => $lot->id, 'stock_mrp' => $lot->mrp]);
                }
            }
        }
    }

    private function reverseStock(Returns $return, Collection $details, string $date): void
    {
        foreach ($details as $item) {
            $quantity = $this->rawQuantity((float) $item->prd_itemqty, (string) $item->prd_unit, (float) $item->prd_uqty);
            Stock::updateStock($date, $item->prd_itemid, -$quantity);
            StockLedger::updateStockLedger($date, $item->prd_itemid, -$quantity, $return->pr_id, 'srd');
        }
    }

    private function applyFinancialEntries(Returns $return): void
    {
        LedgerBook::setSaleReturnLedgerBook(
            $return->pr_amount_payable,
            $return->pr_amount_paid,
            $return->pr_date,
            $return->pr_id,
            $return->pr_paymode,
            $return->pr_vendor,
            $return->pr_vno
        );
        LedgerBook::recalculateCustomerLedger($return->pr_vendor);
        // Balance::updateDebitBalance($return->pr_date, $return->pr_paymode, $return->pr_amount_paid);
        Banking::updateDebitBanking($return->pr_paymode, $return->pr_amount_paid, $return->pr_date);
    }

    private function removeFinancialEntries(Returns $return, int $customerId, string $date): void
    {
        LedgerBook::releaseCustomerPaymentAllocationsForSource($customerId, $return->pr_id, ['sr']);
        LedgerBook::where('lb_vid', $return->pr_id)->whereIn('lb_type', ['sr', 'srp'])->delete();
        LedgerBook::recalculateCustomerLedger($customerId, $date);
    }

    private function originalSale(?string $voucher): ?Sale
    {
        $voucher = trim((string) $voucher);
        if ($voucher === '') {
            return null;
        }

        return Sale::with('saleDetails.product')
            ->where('sa_vno', $voucher)
            ->where('status', '1')
            ->first();
    }

    private function assertOriginalSaleMatches(Sale $sale, int $customerId): void
    {
        if ((int) $sale->sa_customer !== $customerId) {
            throw ValidationException::withMessages([
                'pr_vendor' => "Customer does not match original sale {$sale->sa_vno}.",
            ]);
        }
    }

    private function assertReturnableQuantities(Sale $sale, array $items, ?int $currentReturnId): void
    {
        $requested = collect($items)->groupBy('product_id')->map(fn (Collection $rows) => round((float) $rows->sum('raw_quantity'), 2));
        $sold = $sale->saleDetails
            ->groupBy('sad_itemid')
            ->map(fn (Collection $rows) => round((float) $rows->sum(fn (SaleDetail $detail) => $this->rawQuantity((float) $detail->sad_itemqty, (string) $detail->sad_unit, (float) $detail->sad_uqty)), 2));

        $alreadyReturned = ReturnDetail::query()
            ->whereHas('return', function ($query) use ($sale, $currentReturnId) {
                $query->where('pr_type', 2)
                    ->where('status', '1')
                    ->where('pr_pvno', $sale->sa_vno);

                if ($currentReturnId) {
                    $query->where('pr_id', '!=', $currentReturnId);
                }
            })
            ->get()
            ->groupBy('prd_itemid')
            ->map(fn (Collection $rows) => round((float) $rows->sum(fn (ReturnDetail $detail) => $this->rawQuantity((float) $detail->prd_itemqty, (string) $detail->prd_unit, (float) $detail->prd_uqty)), 2));

        foreach ($requested as $productCode => $quantity) {
            $available = (float) ($sold->get($productCode, 0) - $alreadyReturned->get($productCode, 0));
            if ($quantity > $available + 0.00001) {
                throw ValidationException::withMessages([
                    'return_items' => "Return quantity for {$productCode} exceeds original sale balance. Available: {$available}.",
                ]);
            }
        }
    }

    private function applyOriginalSaleBatchReturn(Sale $sale, array $item, Returns $return, ReturnDetail $detail, string $returnDate): void
    {
        $remaining = (float) $item['raw_quantity'];
        $saleDetails = $sale->saleDetails->where('sad_itemid', $item['product_id'])->values();
        $batchReturnable = $saleDetails->sum(fn (SaleDetail $saleDetail) => $this->batchReturnableQuantity($saleDetail));

        if ($batchReturnable <= 0) {
            $this->batchInventory->manualSaleReturn(
                $item['product'],
                (float) $item['raw_quantity'],
                (int) $return->pr_id,
                (int) $detail->prd_id,
                $returnDate
            );

            return;
        }

        foreach ($saleDetails as $saleDetail) {
            if ($remaining <= 0) {
                break;
            }

            $available = $this->batchReturnableQuantity($saleDetail);
            if ($available <= 0) {
                continue;
            }

            $quantity = min($remaining, $available);
            $this->batchInventory->returnSaleDetail(
                (int) $saleDetail->sad_id,
                (float) $quantity,
                (int) $return->pr_id,
                (int) $detail->prd_id,
                $returnDate
            );
            $remaining -= $quantity;
        }

        if ($remaining > 0.00001) {
            throw ValidationException::withMessages([
                'return_items' => "No returnable batch quantity was found on {$sale->sa_vno} for {$item['product']->product}.",
            ]);
        }
    }

    private function batchReturnableQuantity(SaleDetail $saleDetail): float
    {
        $sales = BatchMovement::where('reference_type', 'sale')
            ->where('reference_detail_id', $saleDetail->sad_id)
            ->where('movement_type', 'sale')
            ->get();

        if ($sales->isEmpty()) {
            return 0;
        }

        $returned = (float) BatchMovement::where('movement_type', 'sale_return')
            ->whereIn('reversal_of_id', $sales->pluck('id'))
            ->whereNotIn('id', BatchMovement::query()
                ->select('reversal_of_id')
                ->whereNotNull('reversal_of_id'))
            ->sum('quantity_in');

        return max(0, (float) $sales->sum('quantity_out') - $returned);
    }

    private function applyOriginalSaleMrpReturn(Sale $sale, array $item, Returns $return, ReturnDetail $detail, string $returnDate): void
    {
        $remaining = (float) $item['raw_quantity'];
        $saleDetails = $sale->saleDetails->where('sad_itemid', $item['product_id']);
        if (!empty($item['mrp_stock_lot_id'])) {
            $saleDetails = $saleDetails->where('mrp_stock_lot_id', (int) $item['mrp_stock_lot_id']);
        }
        $saleDetails = $saleDetails->values();
        $returnable = $saleDetails->sum(fn (SaleDetail $saleDetail) => $this->mrpReturnableQuantity($saleDetail));

        if ($returnable <= 0) {
            $lot = $this->mrpInventory->manualSaleReturn(
                $item['product'],
                (float) $item['raw_quantity'],
                (int) $return->pr_id,
                (int) $detail->prd_id,
                $returnDate,
                $item['stock_mrp']
            );
            $detail->update(['mrp_stock_lot_id' => $lot->id, 'stock_mrp' => $lot->mrp]);

            return;
        }

        foreach ($saleDetails as $saleDetail) {
            if ($remaining <= 0) {
                break;
            }

            $available = $this->mrpReturnableQuantity($saleDetail);
            if ($available <= 0) {
                continue;
            }

            $quantity = min($remaining, $available);
            $this->mrpInventory->returnSaleDetail(
                (int) $saleDetail->sad_id,
                (float) $quantity,
                (int) $return->pr_id,
                (int) $detail->prd_id,
                $returnDate
            );
            if (!$detail->mrp_stock_lot_id && $saleDetail->mrp_stock_lot_id) {
                $detail->update([
                    'mrp_stock_lot_id' => $saleDetail->mrp_stock_lot_id,
                    'stock_mrp' => $saleDetail->stock_mrp,
                ]);
            }
            $remaining -= $quantity;
        }

        if ($remaining > 0.00001) {
            throw ValidationException::withMessages([
                'return_items' => "No returnable MRP-lot quantity was found on {$sale->sa_vno} for {$item['product']->product}.",
            ]);
        }
    }

    private function mrpReturnableQuantity(SaleDetail $saleDetail): float
    {
        $sales = MrpStockMovement::where('reference_type', 'sale')
            ->where('reference_detail_id', $saleDetail->sad_id)
            ->where('movement_type', 'sale')
            ->get();

        if ($sales->isEmpty()) {
            return 0;
        }

        $returned = (float) MrpStockMovement::where('movement_type', 'sale_return')
            ->whereIn('reversal_of_id', $sales->pluck('id'))
            ->whereNotIn('id', MrpStockMovement::query()->select('reversal_of_id')->whereNotNull('reversal_of_id'))
            ->sum('quantity_in');

        return max(0, (float) $sales->sum('quantity_out') - $returned);
    }

    private function validatedAmountPaid(array $data, float $amountPayable): float
    {
        $amountPaid = round((float) ($data['pr_amount_paid'] ?? 0), 2);

        if ($amountPaid > $amountPayable) {
            throw ValidationException::withMessages([
                'pr_amount_paid' => 'Amount paid cannot exceed the sale return amount.',
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

    private function assertActiveSaleReturn(Returns $return): void
    {
        if ((int) $return->pr_type !== 2 || (string) $return->status !== '1') {
            throw ValidationException::withMessages([
                'sale_return' => 'This sale return is already cancelled or invalid.',
            ]);
        }
    }

    private function refreshClosings(): void
    {
        StockValue::stockClosing();
    }
}