<?php

namespace Modules\Sale\app\Services;

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
use Modules\Product\app\Models\Stock;
use Modules\Product\app\Models\StockLedger;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Services\BatchInventoryService;
use Modules\Product\app\Services\MrpInventoryService;
use Modules\Sale\app\Models\Sale;
use Modules\Sale\app\Models\SaleDetail;
use Modules\Sale\app\Models\SaleSetting;
use Modules\Settings\app\Models\Company;

class SaleService
{
    public function __construct(
        private readonly SaleCalculator $calculator,
        private readonly SaleVoucherService $voucherService,
        private readonly BatchInventoryService $batchInventory,
        private readonly MrpInventoryService $mrpInventory
    ) {
    }

    public function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $data['sale_items'] = $this->mrpInventory->applySalePricing(
                $data['sale_items'],
                (bool) (SaleSetting::values()['mrp_pricing_mode'] ?? false)
            );
            $saleDate = Carbon::createFromFormat('d/m/Y', $data['sa_date'])->toDateString();
            $calculation = $this->calculator->calculate($data['sale_items'], (string) $data['sa_type'], $this->shouldCollectTax($data));
            $this->lockOperationalRows($calculation['items'], [], (int) $data['sa_paymode']);
            $allowOutOfStock = $this->allowsOutOfStockSale();

            if (!$allowOutOfStock) {
                $this->assertStockAvailable($calculation['items']);
            }

            $amountPaid = $this->validatedAmountPaid($data, $calculation['amount_payable']);
            $due = $this->dueData($data, $calculation['amount_payable'], $amountPaid, $saleDate);
            $sale = Sale::create($this->saleData(
                $data,
                $calculation,
                $saleDate,
                $amountPaid,
                $due,
                $this->voucherService->next((string) $data['sa_type'], $saleDate)
            ));

            $this->createDetailsAndApplyStock($sale, $calculation['items'], $saleDate, $allowOutOfStock);
            $this->applyFinancialEntries($sale);
            $this->refreshClosings();

            return $sale->fresh(['saleDetails.product', 'customer', 'user', 'banking']);
        }, 5);
    }

    public function update(Sale $sale, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $data) {
            $data['sale_items'] = $this->mrpInventory->applySalePricing(
                $data['sale_items'],
                (bool) (SaleSetting::values()['mrp_pricing_mode'] ?? false)
            );
            $sale = Sale::whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActive($sale);

            $oldDetails = SaleDetail::where('sad_sid', $sale->sa_id)->lockForUpdate()->get();
            $oldDate = $sale->sa_date;
            $oldCustomer = (int) $sale->sa_customer;
            $oldPaymode = (int) $sale->sa_paymode;
            $oldPaid = (float) $sale->sa_amount_paid;
            $saleDate = Carbon::createFromFormat('d/m/Y', $data['sa_date'])->toDateString();
            $calculation = $this->calculator->calculate($data['sale_items'], (string) $data['sa_type'], $this->shouldCollectTax($data));
            $allowOutOfStock = $this->allowsOutOfStockSale();

            $this->lockOperationalRows(
                $calculation['items'],
                $oldDetails->pluck('sad_itemid')->all(),
                (int) $data['sa_paymode'],
                $oldPaymode
            );

            if (!$allowOutOfStock) {
                $this->assertStockAvailable($calculation['items'], $this->rawQuantities($oldDetails));
            }

            $this->reverseStock($sale, $oldDetails, $oldDate);
            $this->batchInventory->reverseReference('sale', $sale->sa_id, $oldDate);
            $this->mrpInventory->reverseReference('sale', $sale->sa_id, $oldDate);
            $sale->saleDetails()->delete();
            $this->removeFinancialEntries($sale, $oldCustomer, $oldDate);
            // Balance::updateDebitBalance($oldDate, $oldPaymode, $oldPaid);
            Banking::updateDebitBanking($oldPaymode, $oldPaid, $oldDate);

            $amountPaid = $this->validatedAmountPaid($data, $calculation['amount_payable']);
            $due = $this->dueData($data, $calculation['amount_payable'], $amountPaid, $saleDate);
            $sale->update($this->saleData($data, $calculation, $saleDate, $amountPaid, $due));
            $this->createDetailsAndApplyStock($sale, $calculation['items'], $saleDate, $allowOutOfStock);
            $this->applyFinancialEntries($sale);

            LedgerBook::recalculateCustomerLedger($oldCustomer);
            if ($oldCustomer !== (int) $sale->sa_customer) {
                LedgerBook::recalculateCustomerLedger((int) $sale->sa_customer);
            }

            $this->refreshClosings();

            return $sale->fresh(['saleDetails.product', 'customer', 'user', 'banking']);
        }, 5);
    }

    public function cancel(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $sale = Sale::whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActive($sale);

            $details = SaleDetail::where('sad_sid', $sale->sa_id)->lockForUpdate()->get();
            $this->lockOperationalRows([], $details->pluck('sad_itemid')->all(), (int) $sale->sa_paymode);

            LedgerBook::releaseCustomerPaymentAllocationsForSource(
                $sale->sa_customer,
                $sale->sa_id,
                ['s']
            );
            LedgerBook::where('lb_vid', $sale->sa_id)
                ->whereIn('lb_type', ['s', 'sp'])
                ->update([
                    'lb_amount' => 0,
                    'lb_tramount' => 0,
                    'status' => '0',
                ]);
            LedgerBook::recalculateCustomerLedger($sale->sa_customer);

            // Balance::updateDebitBalance($sale->sa_date, $sale->sa_paymode, $sale->sa_amount_paid);
            Banking::updateDebitBanking($sale->sa_paymode, $sale->sa_amount_paid, $sale->sa_date);
            $this->reverseStock($sale, $details, $sale->sa_date);
            $this->batchInventory->reverseReference('sale', $sale->sa_id, $sale->sa_date);
            $this->mrpInventory->reverseReference('sale', $sale->sa_id, $sale->sa_date);

            $sale->status = '0';
            $sale->save();
            $this->refreshClosings();
        }, 5);
    }

    private function saleData(
        array $data,
        array $calculation,
        string $saleDate,
        float $amountPaid,
        array $due,
        ?string $voucher = null
    ): array {
        $balance = round($calculation['amount_payable'] - $amountPaid, 2);
        $result = [
            'sa_date' => $saleDate,
            'sa_eway_bill_no' => $data['sa_eway_bill_no'] ?? null,
            'sa_loc' => $data['sa_loc'] ?? null,
            'sa_vehicle' => $data['sa_vehicle'] ?? null,
            'sa_customer' => $data['sa_customer'],
            'sa_state_code' => $data['sa_state_code'] ?? null,
            'sa_amount' => $calculation['amount'],
            'sa_gst' => $calculation['gst'],
            'sa_discount' => $calculation['discount'],
            'sa_grandtotal' => $calculation['grand_total'],
            'sa_amount_payable' => $calculation['amount_payable'],
            'sa_round' => $calculation['round'],
            'sa_amount_paid' => $amountPaid,
            'sa_balance' => $balance,
            'sa_due_days' => $due['days'],
            'sa_due_date' => $due['date'],
            'sa_paymode' => $data['sa_paymode'],
            'sa_type' => (string) $data['sa_type'],
            'sa_is_igst' => (bool) ($data['sa_is_igst'] ?? false),
            'sa_remark' => $data['sa_remark'] ?? null,
            'sa_user' => Auth::id(),
            'sa_paid' => $balance <= 0 ? 'FP' : ($amountPaid > 0 ? 'HP' : 'NP'),
        ];

        if ($voucher !== null) {
            $result['sa_vno'] = $voucher;
        }

        return $result;
    }

    private function createDetailsAndApplyStock(
        Sale $sale,
        array $items,
        string $saleDate,
        bool $allowOutOfStock
    ): void
    {
        foreach ($items as $item) {
            $detail = SaleDetail::create([
                'sad_sid' => $sale->sa_id,
                'sad_itemid' => $item['product_id'],
                'sad_hsn' => $item['hsn_code'],
                'manufacturing_date' => $item['manufacturing_date'],
                'sad_itemqty' => $item['quantity'],
                'sad_returnqty' => $item['quantity'],
                'sad_unit' => $item['unit'],
                'sad_uprice' => $item['unit_price'],
                'sad_uqty' => $item['unit_qty'],
                'sad_price' => $item['total_before_discount'],
                'sad_adisc' => $item['discount_amount'],
                'sad_pdisc' => $item['discount_percentage'],
                'sad_gst' => $item['gst_value'],
                'sad_total' => $item['total_after_discount'],
            ]);

            $product = Product::where('product_code', $item['product_id'])->firstOrFail();
            $allocations = $this->batchInventory->allocateSale(
                $product,
                (float) $item['raw_quantity'],
                (int) $sale->sa_id,
                (int) $detail->sad_id,
                $saleDate,
                $item['stock_batch_id'] ?? null,
                in_array($item['unit'], ['No.s', 'Nos.'], true)
                    ? (float) $item['unit_price']
                    : (float) $item['unit_price'] / max((float) $item['unit_qty'], 1),
                allowOutOfStock: $allowOutOfStock
            );
            if ($allocations->count() === 1) {
                $batch = $allocations->first()->batch;
                $detail->update([
                    'stock_batch_id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'expiry_date' => $batch->expiry_date,
                ]);
            }

            $mrpAllocations = $this->mrpInventory->allocateSale(
                $product,
                (float) $item['raw_quantity'],
                (int) $sale->sa_id,
                (int) $detail->sad_id,
                $saleDate,
                $item['mrp_stock_lot_id'] ?? null,
                in_array($item['unit'], ['No.s', 'Nos.'], true)
                    ? (float) $item['unit_price']
                    : (float) $item['unit_price'] / max((float) $item['unit_qty'], 1),
                allowOutOfStock: $allowOutOfStock
            );
            if ($mrpAllocations->count() === 1) {
                $lot = $mrpAllocations->first()->lot;
                $detail->update([
                    'mrp_stock_lot_id' => $lot->id,
                    'stock_mrp' => $lot->mrp,
                ]);
            }

            Stock::updateStock($saleDate, $item['product_id'], -$item['raw_quantity']);
            StockLedger::updateStockLedger(
                $saleDate,
                $item['product_id'],
                -$item['raw_quantity'],
                $sale->sa_id,
                's'
            );
        }
    }

    private function reverseStock(Sale $sale, Collection $details, string $originalDate): void
    {
        foreach ($details as $item) {
            $quantity = $item->sad_unit !== 'No.s'
                ? (float) $item->sad_returnqty * (float) $item->sad_uqty
                : (float) $item->sad_returnqty;

            Stock::updateStock($originalDate, $item->sad_itemid, $quantity);
            StockLedger::updateStockLedger(
                $originalDate,
                $item->sad_itemid,
                $quantity,
                $sale->sa_id,
                'sd'
            );
        }
    }

    private function applyFinancialEntries(Sale $sale): void
    {
        LedgerBook::setSaleLedgerBook(
            $sale->sa_amount_payable,
            $sale->sa_amount_paid,
            $sale->sa_date,
            $sale->sa_id,
            $sale->sa_paymode,
            $sale->sa_customer,
            $sale->sa_vno
        );
        LedgerBook::recalculateCustomerLedger($sale->sa_customer);
        // Balance::updateCreditBalance($sale->sa_date, $sale->sa_paymode, $sale->sa_amount_paid);
        Banking::updateCreditBanking($sale->sa_paymode, $sale->sa_amount_paid, $sale->sa_date);
    }

    private function removeFinancialEntries(Sale $sale, int $customerId, string $date): void
    {
        LedgerBook::releaseCustomerPaymentAllocationsForSource($customerId, $sale->sa_id, ['s']);
        LedgerBook::where('lb_vid', $sale->sa_id)
            ->whereIn('lb_type', ['s', 'sp'])
            ->delete();
        LedgerBook::recalculateCustomerLedger($customerId, $date);
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

    private function assertStockAvailable(array $items, array $restoredQuantities = []): void
    {
        $required = collect($items)
            ->groupBy('product_id')
            ->map(fn (Collection $rows) => round((float) $rows->sum('raw_quantity'), 2));

        $stocks = Stock::whereIn('stock_product_id', $required->keys())
            ->orderByDesc('stock_id')
            ->get()
            ->unique('stock_product_id')
            ->keyBy('stock_product_id');

        foreach ($required as $productCode => $quantity) {
            $available = (float) ($stocks->get($productCode)?->stock_qty ?? 0)
                + (float) ($restoredQuantities[$productCode] ?? 0);

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'sale_items' => "Insufficient stock for product {$productCode}. Available: {$available}.",
                ]);
            }
        }
    }

    private function rawQuantities(Collection $details): array
    {
        return $details->groupBy('sad_itemid')->map(function (Collection $rows) {
            return round((float) $rows->sum(function (SaleDetail $item) {
                return $item->sad_unit !== 'No.s'
                    ? (float) $item->sad_returnqty * (float) $item->sad_uqty
                    : (float) $item->sad_returnqty;
            }), 2);
        })->all();
    }

    private function validatedAmountPaid(array $data, float $amountPayable): float
    {
        $amountPaid = round((float) ($data['sa_amount_paid'] ?? 0), 2);

        if ($amountPaid > $amountPayable) {
            throw ValidationException::withMessages([
                'sa_amount_paid' => 'Amount paid cannot exceed the amount payable.',
            ]);
        }

        return $amountPaid;
    }

    private function dueData(array $data, float $amountPayable, float $amountPaid, string $saleDate): array
    {
        $hasBalance = $amountPaid < $amountPayable;
        $isPosSale = !empty($data['pos_session_id']) || (($data['sale_origin'] ?? null) === 'pos');

        $dueDays = $hasBalance
            && !$isPosSale
            && trim((string) ($data['sa_due_days'] ?? '')) !== ''
                ? (int) $data['sa_due_days']
                : null;

        return [
            'days' => $dueDays,
            'date' => $dueDays !== null
                ? Carbon::parse($saleDate)->addDays($dueDays)->toDateString()
                : null,
        ];
    }

    private function assertActive(Sale $sale): void
    {
        if ((string) $sale->status !== '1') {
            throw ValidationException::withMessages([
                'sale' => 'This sale is already cancelled.',
            ]);
        }
    }

    private function allowsOutOfStockSale(): bool
    {
        return (bool) (SaleSetting::values()['allow_out_of_stock_sale'] ?? false);
    }

    private function refreshClosings(): void
    {
        StockValue::stockClosing();
    }

    private function shouldCollectTax(array $data): bool
    {
        return Company::taxProfile()['collect_tax'] && (string) ($data['sa_type'] ?? '1') !== '0';
    }
}