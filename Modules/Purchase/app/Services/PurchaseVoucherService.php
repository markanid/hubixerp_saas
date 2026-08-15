<?php

namespace Modules\Purchase\app\Services;

use Illuminate\Support\Facades\DB;
use Modules\Purchase\app\Models\Purchase;

class PurchaseVoucherService
{
    public function preview(string $purchaseType, string $date): string
    {
        [$financialYear, $prefix] = $this->voucherParts($purchaseType, $date);
        $sequence = DB::table('purchase_voucher_sequences')
            ->where('financial_year', $financialYear)
            ->where('purchase_type', $purchaseType)
            ->value('last_number') ?? 0;

        return $this->format($prefix, ((int) $sequence) + 1);
    }

    public function next(string $purchaseType, string $date): string
    {
        [$financialYear, $prefix] = $this->voucherParts($purchaseType, $date);

        DB::table('purchase_voucher_sequences')->insertOrIgnore([
            'financial_year' => $financialYear,
            'purchase_type' => $purchaseType,
            'last_number' => 0,
        ]);

        $sequence = DB::table('purchase_voucher_sequences')
            ->where('financial_year', $financialYear)
            ->where('purchase_type', $purchaseType)
            ->lockForUpdate()
            ->first();

        $nextNumber = ((int) $sequence->last_number) + 1;

        DB::table('purchase_voucher_sequences')
            ->where('financial_year', $financialYear)
            ->where('purchase_type', $purchaseType)
            ->update(['last_number' => $nextNumber]);

        return $this->format($prefix, $nextNumber);
    }

    private function voucherParts(string $purchaseType, string $date): array
    {
        $financialYear = Purchase::getFinancialYear($date);
        $year = substr($financialYear, 2, 2);
        $prefix = $purchaseType === '2' ? "P{$year}/6B-" : "P{$year}-";

        return [$financialYear, $prefix];
    }

    private function format(string $prefix, int $number): string
    {
        return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
