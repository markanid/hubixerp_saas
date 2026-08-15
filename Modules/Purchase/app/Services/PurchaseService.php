<?php

namespace Modules\Purchase\app\Services;

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
use Modules\Product\app\Models\StockLedger;
use Modules\Product\app\Services\BatchInventoryService;
use Modules\Product\app\Services\MrpInventoryService;
use Modules\Purchase\app\Models\Purchase;
use Modules\Purchase\app\Models\PurchaseDetail;
use Modules\Settings\app\Models\Company;

class PurchaseService
{
    public function __construct(
        private readonly PurchaseCalculator $calculator,
        private readonly PurchaseVoucherService $voucherService,
        private readonly BatchInventoryService $batchInventory,
        private readonly MrpInventoryService $mrpInventory
    ) {
    }

    public function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $purchaseDate = Carbon::createFromFormat('d/m/Y', $data['pu_date'])->toDateString();
            $calculation = $this->calculator->calculate($data['purchase_items'], (string) $data['pu_type'], $this->shouldCollectTax());
            $this->lockOperationalRows($calculation['items'], [], (int) $data['pu_paymode']);
            $amountPaid = $this->validatedAmountPaid($data, $calculation['amount_payable']);
            $due = $this->dueData($data, $calculation['amount_payable'], $amountPaid, $purchaseDate);

            $purchase = Purchase::create($this->purchaseData(
                $data,
                $calculation,
                $purchaseDate,
                $amountPaid,
                $due,
                $this->voucherService->next((string) $data['pu_type'], $purchaseDate)
            ));

            $this->createDetailsAndApplyStock($purchase, $calculation['items'], $purchaseDate);
            $this->applyFinancialEntries($purchase);
            $this->refreshClosings();

            return $purchase->fresh(['purchaseDetails.product', 'vendor', 'user', 'banking']);
        }, 5);
    }

    public function update(Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $purchase = Purchase::whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActive($purchase);

            $oldDetails = PurchaseDetail::where('pud_pid', $purchase->pu_id)->lockForUpdate()->get();
            $oldDate = $purchase->pu_date;
            $oldVendor = (int) $purchase->pu_vendor;
            $oldPaymode = (int) $purchase->pu_paymode;
            $oldPaid = (float) $purchase->pu_amount_paid;
            $purchaseDate = Carbon::createFromFormat('d/m/Y', $data['pu_date'])->toDateString();
            $calculation = $this->calculator->calculate($data['purchase_items'], (string) $data['pu_type'], $this->shouldCollectTax());

            $this->lockOperationalRows(
                $calculation['items'],
                $oldDetails->pluck('pud_itemid')->all(),
                (int) $data['pu_paymode'],
                $oldPaymode
            );

            $this->reverseStock($purchase, $oldDetails, $oldDate);
            $this->batchInventory->reverseReference('purchase', $purchase->pu_id, $oldDate);
            $this->mrpInventory->reverseReference('purchase', $purchase->pu_id, $oldDate);
            $purchase->purchaseDetails()->delete();
            $this->removeFinancialEntries($purchase, $oldVendor, $oldDate);
            // Balance::updateCreditBalance($oldDate, $oldPaymode, $oldPaid);
            Banking::updateCreditBanking($oldPaymode, $oldPaid, $oldDate);

            $amountPaid = $this->validatedAmountPaid($data, $calculation['amount_payable']);
            $due = $this->dueData($data, $calculation['amount_payable'], $amountPaid, $purchaseDate);
            $purchase->update($this->purchaseData($data, $calculation, $purchaseDate, $amountPaid, $due));
            $this->createDetailsAndApplyStock($purchase, $calculation['items'], $purchaseDate);
            $this->applyFinancialEntries($purchase);

            LedgerBook::recalculateVendorLedger($oldVendor, $oldDate, null);
            if ($oldVendor !== (int) $purchase->pu_vendor) {
                LedgerBook::recalculateVendorLedger((int) $purchase->pu_vendor, $purchaseDate, null);
            }

            $this->refreshClosings();

            return $purchase->fresh(['purchaseDetails.product', 'vendor', 'user', 'banking']);
        }, 5);
    }

    public function cancel(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $purchase = Purchase::whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActive($purchase);

            $details = PurchaseDetail::where('pud_pid', $purchase->pu_id)->lockForUpdate()->get();
            $this->lockOperationalRows([], $details->pluck('pud_itemid')->all(), (int) $purchase->pu_paymode);

            LedgerBook::releaseVendorPaymentAllocationsForSource(
                $purchase->pu_vendor,
                $purchase->pu_id,
                ['p']
            );
            LedgerBook::where('lb_vid', $purchase->pu_id)
                ->whereIn('lb_type', ['p', 'pp'])
                ->update([
                    'lb_amount' => 0,
                    'lb_tramount' => 0,
                    'status' => '0',
                ]);
            LedgerBook::recalculateVendorLedger($purchase->pu_vendor, $purchase->pu_date, null);

            // Balance::updateCreditBalance($purchase->pu_date, $purchase->pu_paymode, $purchase->pu_amount_paid);
            Banking::updateCreditBanking($purchase->pu_paymode, $purchase->pu_amount_paid, $purchase->pu_date);
            $this->reverseStock($purchase, $details, $purchase->pu_date);
            $this->batchInventory->reverseReference('purchase', $purchase->pu_id, $purchase->pu_date);
            $this->mrpInventory->reverseReference('purchase', $purchase->pu_id, $purchase->pu_date);

            $purchase->pu_status = '0';
            $purchase->save();
            $this->refreshClosings();
        }, 5);
    }

    private function purchaseData(
        array $data,
        array $calculation,
        string $purchaseDate,
        float $amountPaid,
        array $due,
        ?string $voucher = null
    ): array {
        $balance = round($calculation['amount_payable'] - $amountPaid, 2);
        $result = [
            'pu_date' => $purchaseDate,
            'financial_year' => Purchase::getFinancialYear($purchaseDate),
            'pu_type' => (string) $data['pu_type'],
            'pu_bill_number' => $data['pu_bill_number'] ?? null,
            'pu_vendor' => $data['pu_vendor'],
            'pu_amount' => $calculation['amount'],
            'pu_gst' => $calculation['gst'],
            'pu_discount' => $calculation['discount'],
            'pu_grandtotal' => $calculation['grand_total'],
            'pu_amount_payable' => $calculation['amount_payable'],
            'pu_round' => $calculation['round'],
            'pu_amount_paid' => $amountPaid,
            'pu_balance' => $balance,
            'pu_due_days' => $due['days'],
            'pu_due_date' => $due['date'],
            'pu_paymode' => $data['pu_paymode'],
            'pu_user' => Auth::id() ?: ($data['pu_user'] ?? null),
            'pu_paid' => $balance <= 0 ? 'FP' : ($amountPaid > 0 ? 'HP' : 'NP'),
        ];

        if ($voucher !== null) {
            $result['pu_vno'] = $voucher;
        }

        return $result;
    }

    private function createDetailsAndApplyStock(Purchase $purchase, array $items, string $purchaseDate): void
    {
        foreach ($items as $item) {
            $detail = PurchaseDetail::create([
                'pud_pid' => $purchase->pu_id,
                'pud_itemid' => $item['product_id'],
                'pud_hsn' => $item['hsn_code'],
                'pud_itemqty' => $item['quantity'],
                'pud_returnqty' => $item['quantity'],
                'pud_free' => $item['free_quantity'],
                'pud_unit' => $item['unit'],
                'pud_uprice' => $item['unit_price'],
                'pud_uqty' => $item['unit_qty'],
                'pud_price' => $item['total_before_discount'],
                'pud_adisc' => $item['discount_amount'],
                'pud_pdisc' => $item['discount_percentage'],
                'pud_gst' => $item['gst_value'],
                'pud_total' => $item['total_after_discount'],
                'batch_no' => $item['batch_no'],
                'expiry_date' => $this->batchInventory->normaliseExpiry($item['expiry_date']),
                'batch_mrp' => $item['mrp'],
                'lot_sale_price' => $item['sale_price'],
                'lot_margin_percentage' => $item['margin_percentage'],
                'lot_margin_amount' => $item['margin_amount'],
            ]);

            $totalStock = round((float) $item['raw_quantity'] + (float) $item['raw_free_quantity'], 2);
            Stock::updateStock($purchaseDate, $item['product_id'], $totalStock);
            StockLedger::updateStockLedger($purchaseDate, $item['product_id'], $totalStock, $purchase->pu_id, 'p');

            $product = Product::where('product_code', $item['product_id'])->firstOrFail();
            $this->batchInventory->receivePurchase(
                $product,
                $item,
                (int) $purchase->pu_id,
                (int) $detail->pud_id,
                $purchaseDate,
                $totalStock
            );
            $this->mrpInventory->receivePurchase(
                $product,
                $item,
                (int) $purchase->pu_id,
                (int) $detail->pud_id,
                (string) $purchase->pu_vno,
                $purchaseDate,
                $totalStock
            );
        }
    }

    private function reverseStock(Purchase $purchase, Collection $details, string $originalDate): void
    {
        foreach ($details as $item) {
            $quantity = $item->pud_unit !== 'No.s'
                ? ((float) $item->pud_returnqty + (float) $item->pud_free) * (float) $item->pud_uqty
                : (float) $item->pud_returnqty + (float) $item->pud_free;

            Stock::updateStock($originalDate, $item->pud_itemid, -$quantity);
            StockLedger::updateStockLedger($originalDate, $item->pud_itemid, -$quantity, $purchase->pu_id, 'pd');
        }
    }

    private function applyFinancialEntries(Purchase $purchase): void
    {
        LedgerBook::setPurchaseLedgerBook(
            $purchase->pu_amount_payable,
            $purchase->pu_amount_paid,
            $purchase->pu_date,
            $purchase->pu_id,
            $purchase->pu_paymode,
            $purchase->pu_vendor,
            $purchase->pu_vno
        );
        LedgerBook::recalculateVendorLedger($purchase->pu_vendor, $purchase->pu_date, null);
        // Balance::updateDebitBalance($purchase->pu_date, $purchase->pu_paymode, $purchase->pu_amount_paid);
        Banking::updateDebitBanking($purchase->pu_paymode, $purchase->pu_amount_paid, $purchase->pu_date);
    }

    private function removeFinancialEntries(Purchase $purchase, int $vendorId, string $date): void
    {
        LedgerBook::releaseVendorPaymentAllocationsForSource($vendorId, $purchase->pu_id, ['p']);
        LedgerBook::where('lb_vid', $purchase->pu_id)
            ->whereIn('lb_type', ['p', 'pp'])
            ->delete();
        LedgerBook::recalculateVendorLedger($vendorId, $date, null);
    }

    private function lockOperationalRows(
        array $newItems,
        array $oldProductCodes,
        int $paymode,
        ?int $oldPaymode = null
    ): void {
        $productCodes = collect($newItems)
            ->pluck('product_id')
            ->merge($oldProductCodes)
            ->filter()
            ->unique()
            ->values();

        if ($productCodes->isNotEmpty()) {
            Stock::whereIn('stock_product_id', $productCodes)
                ->orderBy('stock_id')
                ->lockForUpdate()
                ->get();
        }

        $paymodes = collect([$paymode, $oldPaymode])->filter()->unique()->values();
        Banking::whereIn('bk_id', $paymodes)->lockForUpdate()->get();
        // Balance::whereIn('balance_pmode', $paymodes)->lockForUpdate()->get();
    }

    private function dueData(array $data, float $amountPayable, float $amountPaid, string $purchaseDate): array
    {
        $hasBalance = $amountPaid < $amountPayable;

        $dueDays = $hasBalance && trim((string) ($data['pu_due_days'] ?? '')) !== ''
            ? (int) $data['pu_due_days']
            : null;

        return [
            'days' => $dueDays,
            'date' => $dueDays !== null
                ? Carbon::parse($purchaseDate)->addDays($dueDays)->toDateString()
                : null,
        ];
    }

    private function validatedAmountPaid(array $data, float $amountPayable): float
    {
        $amountPaid = round((float) ($data['pu_amount_paid'] ?? 0), 2);

        if ($amountPaid > $amountPayable) {
            throw ValidationException::withMessages([
                'pu_amount_paid' => 'Amount paid cannot exceed the amount payable.',
            ]);
        }

        return $amountPaid;
    }

    private function assertActive(Purchase $purchase): void
    {
        if ((string) $purchase->pu_status !== '1') {
            throw ValidationException::withMessages([
                'purchase' => 'This purchase is already cancelled.',
            ]);
        }
    }

    private function refreshClosings(): void
    {
        StockValue::stockClosing();
    }

    private function shouldCollectTax(): bool
    {
        return Company::taxProfile()['collect_tax'];
    }
}