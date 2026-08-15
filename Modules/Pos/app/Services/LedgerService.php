<?php

namespace Modules\Pos\app\Services;

use Modules\Finance\app\Models\LedgerBook;
use Modules\Sale\app\Models\Sale;

class LedgerService
{
    public function refreshCustomer(Sale $sale): void
    {
        LedgerBook::recalculateCustomerLedger((int) $sale->sa_customer);
    }
}
