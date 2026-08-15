<?php

namespace Modules\Service\app\Services;

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
use Modules\Sale\app\Models\SaleSetting;
use Modules\Settings\app\Models\Company;
use Modules\Service\app\Models\Service;
use Modules\Service\app\Models\ServiceDetail;

class ServiceBillingService
{
    public function __construct(
        private readonly ServiceCalculator $calculator,
        private readonly ServiceVoucherService $voucherService,
        private readonly BatchInventoryService $batchInventory,
        private readonly MrpInventoryService $mrpInventory
    ) {
    }

    public function create(array $data): Service
    {
        return DB::transaction(function () use ($data) {
            $data['sale_items'] = $this->mrpInventory->applySalePricing(
                $data['sale_items'] ?? [],
                (bool) (SaleSetting::values()['mrp_pricing_mode'] ?? false)
            );
            $serviceDate = Carbon::createFromFormat('d/m/Y', $data['sv_date'])->toDateString();
            $calculation = $this->calculator->calculate($data['sale_items'] ?? [], $data['service_items'] ?? [], (string) $data['sv_type'], $this->shouldCollectTax($data));
            $this->lockOperationalRows($calculation['sale_items'], [], (int) $data['sv_paymode']);
            $allowOutOfStock = $this->allowsOutOfStockSale();

            if (!$allowOutOfStock) {
                $this->assertStockAvailable($calculation['sale_items']);
            }

            $amountPaid = $this->validatedAmountPaid($data, $calculation['amount_payable']);
            $due = $this->dueData($data, $calculation['amount_payable'], $amountPaid, $serviceDate);
            $service = Service::create($this->serviceData(
                $data,
                $calculation,
                $serviceDate,
                $amountPaid,
                $due,
                $this->voucherService->next($serviceDate)
            ));

            $this->createDetailsAndApplyStock($service, $calculation, $serviceDate, $allowOutOfStock);
            $this->applyFinancialEntries($service);
            $this->refreshClosings();

            return $service->fresh(['serviceDetails.product', 'customer', 'user', 'banking']);
        }, 5);
    }

    public function update(Service $service, array $data): Service
    {
        return DB::transaction(function () use ($service, $data) {
            $data['sale_items'] = $this->mrpInventory->applySalePricing(
                $data['sale_items'] ?? [],
                (bool) (SaleSetting::values()['mrp_pricing_mode'] ?? false)
            );
            $service = Service::whereKey($service->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActive($service);

            $oldDetails = ServiceDetail::where('svd_sid', $service->sv_id)->lockForUpdate()->get();
            $oldSaleDetails = $oldDetails->where('svd_type', 'sale')->values();
            $oldDate = $service->sv_date;
            $oldCustomer = (int) $service->sv_customer;
            $oldPaymode = (int) $service->sv_paymode;
            $oldPaid = (float) $service->sv_amount_paid;
            $serviceDate = Carbon::createFromFormat('d/m/Y', $data['sv_date'])->toDateString();
            $calculation = $this->calculator->calculate($data['sale_items'] ?? [], $data['service_items'] ?? [], (string) $data['sv_type'], $this->shouldCollectTax($data));
            $allowOutOfStock = $this->allowsOutOfStockSale();

            $this->lockOperationalRows(
                $calculation['sale_items'],
                $oldSaleDetails->pluck('svd_itemid')->all(),
                (int) $data['sv_paymode'],
                $oldPaymode
            );

            if (!$allowOutOfStock) {
                $this->assertStockAvailable($calculation['sale_items'], $this->rawQuantities($oldSaleDetails));
            }

            $this->reverseStock($service, $oldSaleDetails, $oldDate);
            $this->batchInventory->reverseReference('service', $service->sv_id, $oldDate);
            $this->mrpInventory->reverseReference('service', $service->sv_id, $oldDate);
            $service->serviceDetails()->delete();
            $this->removeFinancialEntries($service, $oldCustomer);
            // Balance::updateDebitBalance($oldDate, $oldPaymode, $oldPaid);
            Banking::updateDebitBanking($oldPaymode, $oldPaid, $oldDate);

            $amountPaid = $this->validatedAmountPaid($data, $calculation['amount_payable']);
            $due = $this->dueData($data, $calculation['amount_payable'], $amountPaid, $serviceDate);
            $service->update($this->serviceData($data, $calculation, $serviceDate, $amountPaid, $due));
            $this->createDetailsAndApplyStock($service, $calculation, $serviceDate, $allowOutOfStock);
            $this->applyFinancialEntries($service);

            LedgerBook::recalculateCustomerLedger($oldCustomer);
            if ($oldCustomer !== (int) $service->sv_customer) {
                LedgerBook::recalculateCustomerLedger((int) $service->sv_customer);
            }

            $this->refreshClosings();

            return $service->fresh(['serviceDetails.product', 'customer', 'user', 'banking']);
        }, 5);
    }

    public function cancel(Service $service): void
    {
        DB::transaction(function () use ($service) {
            $service = Service::whereKey($service->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActive($service);

            $saleDetails = ServiceDetail::where('svd_sid', $service->sv_id)
                ->where('svd_type', 'sale')
                ->lockForUpdate()
                ->get();
            $this->lockOperationalRows([], $saleDetails->pluck('svd_itemid')->all(), (int) $service->sv_paymode);

            LedgerBook::releaseCustomerPaymentAllocationsForSource(
                $service->sv_customer,
                $service->sv_id,
                ['sv']
            );
            LedgerBook::where('lb_vid', $service->sv_id)
                ->whereIn('lb_type', ['sv', 'svp'])
                ->update([
                    'lb_amount' => 0,
                    'lb_tramount' => 0,
                    'status' => '0',
                ]);
            LedgerBook::recalculateCustomerLedger($service->sv_customer);

            // Balance::updateDebitBalance($service->sv_date, $service->sv_paymode, $service->sv_amount_paid);
            Banking::updateDebitBanking($service->sv_paymode, $service->sv_amount_paid, $service->sv_date);
            $this->reverseStock($service, $saleDetails, $service->sv_date);
            $this->batchInventory->reverseReference('service', $service->sv_id, $service->sv_date);
            $this->mrpInventory->reverseReference('service', $service->sv_id, $service->sv_date);

            $service->status = '0';
            $service->save();
            $this->refreshClosings();
        }, 5);
    }

    private function serviceData(
        array $data,
        array $calculation,
        string $serviceDate,
        float $amountPaid,
        array $due,
        ?string $voucher = null
    ): array {
        $balance = round($calculation['amount_payable'] - $amountPaid, 2);
        $result = [
            'sv_date' => $serviceDate,
            'sv_type' => (string) $data['sv_type'],
            'sv_eway_bill_no' => $data['sv_eway_bill_no'] ?? null,
            'sv_customer' => $data['sv_customer'],
            'sv_state_code' => $data['sv_state_code'] ?? null,
            'sv_is_igst' => (bool) ($data['sv_is_igst'] ?? false),
            'sv_loc' => $data['sv_loc'] ?? null,
            'sv_vehicle' => $data['sv_vehicle'] ?? null,
            'sv_remark' => $data['sv_remark'] ?? null,
            'sv_amount' => $calculation['amount'],
            'sv_gst' => $calculation['gst'],
            'sv_discount' => $calculation['discount'],
            'sv_grandtotal' => $calculation['grand_total'],
            'sv_amount_payable' => $calculation['amount_payable'],
            'sv_round' => $calculation['round'],
            'sv_amount_paid' => $amountPaid,
            'sv_balance' => $balance,
            'sv_due_days' => $due['days'],
            'sv_due_date' => $due['date'],
            'sv_paymode' => $data['sv_paymode'],
            'sv_user' => Auth::id() ?: ($data['sv_user'] ?? null),
            'sv_paid' => $balance <= 0 ? 'FP' : ($amountPaid > 0 ? 'HP' : 'NP'),
        ];

        if ($voucher !== null) {
            $result['sv_vno'] = $voucher;
        }

        return $result;
    }

    private function createDetailsAndApplyStock(
        Service $service,
        array $calculation,
        string $serviceDate,
        bool $allowOutOfStock
    ): void {
        foreach ($calculation['service_items'] as $item) {
            ServiceDetail::create($this->detailData($service, $item));
        }

        foreach ($calculation['sale_items'] as $item) {
            $detail = ServiceDetail::create($this->detailData($service, $item));
            $product = Product::where('product_code', $item['product_id'])->firstOrFail();
            $allocations = $this->batchInventory->allocateSale(
                $product,
                (float) $item['raw_quantity'],
                (int) $service->sv_id,
                (int) $detail->svd_id,
                $serviceDate,
                $item['stock_batch_id'] ?? null,
                in_array($item['unit'], ['No.s', 'Nos.'], true)
                    ? (float) $item['unit_price']
                    : (float) $item['unit_price'] / max((float) $item['unit_qty'], 1),
                referenceType: 'service',
                movementType: 'service',
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
                (int) $service->sv_id,
                (int) $detail->svd_id,
                $serviceDate,
                $item['mrp_stock_lot_id'] ?? null,
                in_array($item['unit'], ['No.s', 'Nos.'], true)
                    ? (float) $item['unit_price']
                    : (float) $item['unit_price'] / max((float) $item['unit_qty'], 1),
                referenceType: 'service',
                movementType: 'service'
            );
            if ($mrpAllocations->count() === 1) {
                $lot = $mrpAllocations->first()->lot;
                $detail->update(['mrp_stock_lot_id' => $lot->id, 'stock_mrp' => $lot->mrp]);
            }

            Stock::updateStock($serviceDate, $item['product_id'], -$item['raw_quantity']);
            StockLedger::updateStockLedger($serviceDate, $item['product_id'], -$item['raw_quantity'], $service->sv_id, 'sv');
        }
    }

    private function detailData(Service $service, array $item): array
    {
        return [
            'svd_sid' => $service->sv_id,
            'svd_itemid' => $item['product_id'],
            'stock_batch_id' => $item['stock_batch_id'] ?? null,
            'mrp_stock_lot_id' => $item['mrp_stock_lot_id'] ?? null,
            'svd_hsn' => $item['hsn_code'],
            'manufacturing_date' => $item['manufacturing_date'] ?? null,
            'svd_itemqty' => $item['quantity'],
            'svd_unit' => $item['unit'],
            'svd_uprice' => $item['unit_price'],
            'svd_uqty' => $item['unit_qty'],
            'svd_price' => $item['total_before_discount'],
            'svd_adisc' => $item['discount_amount'],
            'svd_pdisc' => $item['discount_percentage'],
            'svd_gst' => $item['gst_value'],
            'svd_total' => $item['total_after_discount'],
            'svd_type' => $item['type'],
            'svd_remark' => $item['remarks'] ?? '',
        ];
    }

    private function reverseStock(Service $service, Collection $details, string $originalDate): void
    {
        foreach ($details as $item) {
            $quantity = in_array($item->svd_unit, ['No.s', 'Nos.'], true)
                ? (float) $item->svd_itemqty
                : (float) $item->svd_itemqty * (float) $item->svd_uqty;

            Stock::updateStock($originalDate, $item->svd_itemid, $quantity);
            StockLedger::updateStockLedger($originalDate, $item->svd_itemid, $quantity, $service->sv_id, 'svd');
        }
    }

    private function applyFinancialEntries(Service $service): void
    {
        LedgerBook::setServiceLedgerBook(
            $service->sv_amount_payable,
            $service->sv_amount_paid,
            $service->sv_date,
            $service->sv_id,
            $service->sv_paymode,
            $service->sv_customer,
            $service->sv_vno
        );
        LedgerBook::recalculateCustomerLedger($service->sv_customer);
        // Balance::updateCreditBalance($service->sv_date, $service->sv_paymode, $service->sv_amount_paid);
        Banking::updateCreditBanking($service->sv_paymode, $service->sv_amount_paid, $service->sv_date);
    }

    private function removeFinancialEntries(Service $service, int $customerId): void
    {
        LedgerBook::releaseCustomerPaymentAllocationsForSource($customerId, $service->sv_id, ['sv']);
        LedgerBook::where('lb_vid', $service->sv_id)
            ->whereIn('lb_type', ['sv', 'svp'])
            ->delete();
        LedgerBook::recalculateCustomerLedger($customerId);
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

        if ($required->isEmpty()) {
            return;
        }

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
        return $details->groupBy('svd_itemid')->map(function (Collection $rows) {
            return round((float) $rows->sum(function (ServiceDetail $item) {
                return in_array($item->svd_unit, ['No.s', 'Nos.'], true)
                    ? (float) $item->svd_itemqty
                    : (float) $item->svd_itemqty * (float) $item->svd_uqty;
            }), 2);
        })->all();
    }

    private function validatedAmountPaid(array $data, float $amountPayable): float
    {
        $amountPaid = round((float) ($data['sv_amount_paid'] ?? 0), 2);

        if ($amountPaid > $amountPayable) {
            throw ValidationException::withMessages([
                'sv_amount_paid' => 'Amount paid cannot exceed the amount payable.',
            ]);
        }

        return $amountPaid;
    }

    private function dueData(array $data, float $amountPayable, float $amountPaid, string $serviceDate): array
    {
        $hasBalance = $amountPaid < $amountPayable;

        $dueDays = $hasBalance && trim((string) ($data['sv_due_days'] ?? '')) !== ''
            ? (int) $data['sv_due_days']
            : null;

        return [
            'days' => $dueDays,
            'date' => $dueDays !== null
                ? Carbon::parse($serviceDate)->addDays($dueDays)->toDateString()
                : null,
        ];
    }

    private function assertActive(Service $service): void
    {
        if ((string) $service->status !== '1') {
            throw ValidationException::withMessages([
                'service' => 'This service bill is already cancelled.',
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
        return Company::taxProfile()['collect_tax'] && (string) ($data['sv_type'] ?? '1') !== '0';
    }
}