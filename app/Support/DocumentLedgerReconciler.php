<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Contacts\app\Models\CustomerPaymentAllocation;
use Modules\Contacts\app\Models\VendorPaymentAllocation;
use Modules\Finance\app\Models\LedgerBook;

final class DocumentLedgerReconciler
{
    /**
     * @return array{source: LedgerBook, direct_paid: float, allocated_paid: float, total_paid: float}
     */
    public static function summary(
        int $documentId,
        string $sourceType,
        string $directPaymentType,
        int $partyId,
        bool $isVendor
    ): array {
        $source = LedgerBook::where('lb_vid', $documentId)
            ->where('lb_type', $sourceType)
            ->where('lb_payee', $partyId)
            ->lockForUpdate()
            ->first();

        if (!$source) {
            throw ValidationException::withMessages([
                'ledger' => 'The transaction ledger entry is missing. Please repair the ledger before editing this transaction.',
            ]);
        }

        $activeEntries = static function ($query): void {
            $query->where(function ($status) {
                $status->where('status', '1')->orWhereNull('status');
            });
        };

        $directQuery = LedgerBook::where('lb_vid', $documentId)
            ->where('lb_type', $directPaymentType)
            ->where('lb_payee', $partyId)
            ->lockForUpdate();
        $activeEntries($directQuery);

        $directPaid = (float) $directQuery->get()->sum('lb_amount');
        $allocatedPaid = 0.0;
        $allocationTable = $isVendor ? 'vendor_payment_allocations' : 'customer_payment_allocations';

        if (Schema::hasTable($allocationTable)) {
            $allocationModel = $isVendor ? VendorPaymentAllocation::class : CustomerPaymentAllocation::class;
            $allocatedPaid = (float) $allocationModel::where('source_ledgerbook_id', $source->lb_id)
                ->lockForUpdate()
                ->get()
                ->sum('amount');
        }

        return [
            'source' => $source,
            'direct_paid' => round($directPaid, 2),
            'allocated_paid' => round($allocatedPaid, 2),
            'total_paid' => round($directPaid + $allocatedPaid, 2),
        ];
    }

    /**
     * Keeps the source ledger ID and its payment allocations stable during an edit.
     *
     * @return array{source: LedgerBook, direct_paid: float, allocated_paid: float, total_paid: float}
     */
    public static function reconcile(
        int $documentId,
        string $sourceType,
        string $directPaymentType,
        int $partyId,
        bool $isVendor,
        string $date,
        float $amountPayable,
        ?int $paymode,
        string $voucher
    ): array {
        $summary = self::summary($documentId, $sourceType, $directPaymentType, $partyId, $isVendor);

        if ($summary['total_paid'] > round($amountPayable, 2)) {
            throw ValidationException::withMessages([
                'amount_payable' => 'The revised total cannot be less than payments already recorded for this transaction.',
            ]);
        }

        $financialYear = FinancialYear::active();
        $summary['source']->update([
            'lb_vno' => $voucher,
            'lb_date' => $date,
            'lb_amount' => 0,
            'lb_tramount' => $amountPayable,
            'lb_paymode' => $paymode,
            'financial_year' => $financialYear,
        ]);

        $directPayments = LedgerBook::where('lb_vid', $documentId)
            ->where('lb_type', $directPaymentType)
            ->where('lb_payee', $partyId);
        $activeEntries = static function ($query): void {
            $query->where(function ($status) {
                $status->where('status', '1')->orWhereNull('status');
            });
        };
        $activeEntries($directPayments);
        $directPayments->update([
            'lb_date' => $date,
            'lb_paymode' => $paymode,
            'financial_year' => $financialYear,
        ]);

        return $summary;
    }
}
