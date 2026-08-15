<?php

namespace Modules\Estimation\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Estimation\app\Models\Estimation;
use Modules\Estimation\app\Models\EstimationDetail;
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

class EstimationBillingService
{
    public function __construct(
        private readonly BatchInventoryService $batchInventory,
        private readonly MrpInventoryService $mrpInventory
    )
    {
    }

    public function applyEffects(Estimation $estimation): void
    {
        if (! (bool) $estimation->es_account_effect) {
            return;
        }

        $details = EstimationDetail::where('esd_sid', $estimation->es_id)->lockForUpdate()->get();

        if (! $this->allowsOutOfStockSale()) {
            $this->assertStockAvailable($details);
        }

        foreach ($details as $detail) {
            $rawQuantity = $this->rawQuantity($detail);
            $product = Product::where('product_code', $detail->esd_itemid)->firstOrFail();

            $allocations = $this->batchInventory->allocateSale(
                $product,
                $rawQuantity,
                (int) $estimation->es_id,
                (int) $detail->esd_id,
                $estimation->es_date,
                $detail->stock_batch_id,
                $this->unitRate($detail),
                referenceType: 'estimation',
                movementType: 'estimation',
                allowOutOfStock: $this->allowsOutOfStockSale()
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
                $rawQuantity,
                (int) $estimation->es_id,
                (int) $detail->esd_id,
                $estimation->es_date,
                $detail->mrp_stock_lot_id,
                $this->unitRate($detail),
                referenceType: 'estimation',
                movementType: 'estimation'
            );
            if ($mrpAllocations->count() === 1) {
                $lot = $mrpAllocations->first()->lot;
                $detail->update(['mrp_stock_lot_id' => $lot->id, 'stock_mrp' => $lot->mrp]);
            }

            Stock::updateStock($estimation->es_date, $detail->esd_itemid, -$rawQuantity);
            StockLedger::updateStockLedger(
                $estimation->es_date,
                $detail->esd_itemid,
                -$rawQuantity,
                $estimation->es_id,
                'es'
            );
        }

        LedgerBook::setEstimationLedgerBook(
            $estimation->es_amount_payable,
            $estimation->es_amount_paid,
            $estimation->es_date,
            $estimation->es_id,
            $estimation->es_paymode,
            $estimation->es_customer,
            $estimation->es_vno
        );
        LedgerBook::recalculateCustomerLedger($estimation->es_customer);

        if ((float) $estimation->es_amount_paid > 0 && $estimation->es_paymode) {
            // Balance::updateCreditBalance($estimation->es_date, $estimation->es_paymode, $estimation->es_amount_paid);
            Banking::updateCreditBanking($estimation->es_paymode, $estimation->es_amount_paid, $estimation->es_date);
        }

        $this->refreshClosings();
    }

    public function removeEffects(Estimation $estimation, ?Collection $details = null): void
    {
        if (! (bool) $estimation->es_account_effect) {
            return;
        }

        $details ??= EstimationDetail::where('esd_sid', $estimation->es_id)->lockForUpdate()->get();

        $this->reverseStock($estimation, $details);
        $this->batchInventory->reverseReference('estimation', $estimation->es_id, $estimation->es_date);
        $this->mrpInventory->reverseReference('estimation', $estimation->es_id, $estimation->es_date);

        LedgerBook::releaseCustomerPaymentAllocationsForSource($estimation->es_customer, $estimation->es_id, ['es']);
        LedgerBook::where('lb_vid', $estimation->es_id)
            ->whereIn('lb_type', ['es', 'esp'])
            ->delete();
        LedgerBook::recalculateCustomerLedger($estimation->es_customer, $estimation->es_date);

        if ((float) $estimation->es_amount_paid > 0 && $estimation->es_paymode) {
            // Balance::updateDebitBalance($estimation->es_date, $estimation->es_paymode, $estimation->es_amount_paid);
            Banking::updateDebitBanking($estimation->es_paymode, $estimation->es_amount_paid, $estimation->es_date);
        }

        $this->refreshClosings();
    }

    public function cancelEffects(Estimation $estimation, ?Collection $details = null): void
    {
        if (! (bool) $estimation->es_account_effect) {
            return;
        }

        $details ??= EstimationDetail::where('esd_sid', $estimation->es_id)->lockForUpdate()->get();

        $this->reverseStock($estimation, $details);
        $this->batchInventory->reverseReference('estimation', $estimation->es_id, $estimation->es_date);
        $this->mrpInventory->reverseReference('estimation', $estimation->es_id, $estimation->es_date);

        LedgerBook::releaseCustomerPaymentAllocationsForSource($estimation->es_customer, $estimation->es_id, ['es']);
        LedgerBook::where('lb_vid', $estimation->es_id)
            ->whereIn('lb_type', ['es', 'esp'])
            ->update([
                'lb_amount' => 0,
                'lb_tramount' => 0,
                'status' => '0',
            ]);
        LedgerBook::recalculateCustomerLedger($estimation->es_customer, $estimation->es_date);

        if ((float) $estimation->es_amount_paid > 0 && $estimation->es_paymode) {
            // Balance::updateDebitBalance($estimation->es_date, $estimation->es_paymode, $estimation->es_amount_paid);
            Banking::updateDebitBanking($estimation->es_paymode, $estimation->es_amount_paid, $estimation->es_date);
        }

        $this->refreshClosings();
    }

    private function reverseStock(Estimation $estimation, Collection $details): void
    {
        foreach ($details as $detail) {
            Stock::updateStock($estimation->es_date, $detail->esd_itemid, $this->rawQuantity($detail));
            StockLedger::updateStockLedger(
                $estimation->es_date,
                $detail->esd_itemid,
                $this->rawQuantity($detail),
                $estimation->es_id,
                'esd'
            );
        }
    }

    private function assertStockAvailable(Collection $details): void
    {
        $required = $details
            ->groupBy('esd_itemid')
            ->map(fn (Collection $rows) => round((float) $rows->sum(fn (EstimationDetail $detail) => $this->rawQuantity($detail)), 2));

        $stocks = Stock::whereIn('stock_product_id', $required->keys())
            ->orderByDesc('stock_id')
            ->get()
            ->unique('stock_product_id')
            ->keyBy('stock_product_id');

        foreach ($required as $productCode => $quantity) {
            $available = (float) ($stocks->get($productCode)?->stock_qty ?? 0);

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'estimation_items' => "Insufficient stock for product {$productCode}. Available: {$available}.",
                ]);
            }
        }
    }

    private function rawQuantity(EstimationDetail $detail): float
    {
        return in_array($detail->esd_unit, ['No.s', 'Nos.'], true)
            ? (float) $detail->esd_itemqty
            : (float) $detail->esd_itemqty * max((float) $detail->esd_uqty, 1);
    }

    private function unitRate(EstimationDetail $detail): float
    {
        return in_array($detail->esd_unit, ['No.s', 'Nos.'], true)
            ? (float) $detail->esd_uprice
            : (float) $detail->esd_uprice / max((float) $detail->esd_uqty, 1);
    }

    private function allowsOutOfStockSale(): bool
    {
        return (bool) (SaleSetting::values()['allow_out_of_stock_sale'] ?? false);
    }

    private function refreshClosings(): void
    {
        StockValue::stockClosing();
    }
}