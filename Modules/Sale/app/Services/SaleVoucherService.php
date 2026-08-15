<?php

namespace Modules\Sale\app\Services;

use Illuminate\Support\Facades\DB;

class SaleVoucherService
{
    public function preview(string $saleType, string $date): string
    {
        [$financialYear, $prefix] = $this->voucherParts($saleType, $date);
        $sequence = DB::table('sale_voucher_sequences')
            ->where('financial_year', $financialYear)
            ->where('sale_type', $saleType)
            ->value('last_number') ?? 0;

        return $this->format($prefix, $financialYear, ((int) $sequence) + 1);
    }

    public function next(string $saleType, string $date): string
    {
        [$financialYear, $prefix] = $this->voucherParts($saleType, $date);

        DB::table('sale_voucher_sequences')->insertOrIgnore([
            'financial_year' => $financialYear,
            'sale_type' => $saleType,
            'last_number' => 0,
        ]);

        $sequence = DB::table('sale_voucher_sequences')
            ->where('financial_year', $financialYear)
            ->where('sale_type', $saleType)
            ->lockForUpdate()
            ->first();

        $nextNumber = ((int) $sequence->last_number) + 1;

        DB::table('sale_voucher_sequences')
            ->where('financial_year', $financialYear)
            ->where('sale_type', $saleType)
            ->update(['last_number' => $nextNumber]);

        return $this->format($prefix, $financialYear, $nextNumber);
    }

    private function voucherParts(string $saleType, string $date): array
    {
        $financialYear = \Modules\Sale\app\Models\Sale::getFinancialYear($date);
        $prefix = match ($saleType) {
            '1' => 'S',
            '2' => 'SB',
            '0' => 'SG',
        };

        return [$financialYear, $prefix];
    }

    private function format(string $prefix, string $financialYear, int $number): string
    {
        return $prefix . substr($financialYear, 2, 2) . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
