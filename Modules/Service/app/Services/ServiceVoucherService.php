<?php

namespace Modules\Service\app\Services;

use Illuminate\Support\Facades\DB;
use Modules\Service\app\Models\Service;

class ServiceVoucherService
{
    public function preview(string $date): string
    {
        $financialYear = Service::getFinancialYear($date);
        $sequence = DB::table('service_voucher_sequences')
            ->where('financial_year', $financialYear)
            ->value('last_number') ?? 0;

        return $this->format($financialYear, ((int) $sequence) + 1);
    }

    public function next(string $date): string
    {
        $financialYear = Service::getFinancialYear($date);

        DB::table('service_voucher_sequences')->insertOrIgnore([
            'financial_year' => $financialYear,
            'last_number' => 0,
        ]);

        $sequence = DB::table('service_voucher_sequences')
            ->where('financial_year', $financialYear)
            ->lockForUpdate()
            ->first();

        $nextNumber = ((int) $sequence->last_number) + 1;

        DB::table('service_voucher_sequences')
            ->where('financial_year', $financialYear)
            ->update(['last_number' => $nextNumber]);

        return $this->format($financialYear, $nextNumber);
    }

    private function format(string $financialYear, int $number): string
    {
        return 'R' . substr($financialYear, 2, 2) . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
