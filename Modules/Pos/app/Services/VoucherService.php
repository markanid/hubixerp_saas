<?php

namespace Modules\Pos\app\Services;

use Modules\Sale\app\Services\SaleVoucherService;

class VoucherService
{
    public function __construct(private readonly SaleVoucherService $saleVoucherService)
    {
    }

    public function preview(string $saleType, string $date): string
    {
        return $this->saleVoucherService->preview($saleType, $date);
    }
}
