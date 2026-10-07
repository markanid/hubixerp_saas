<?php

namespace Tests\Unit;

use App\Support\FinancialYear;
use Carbon\Carbon;
use Tests\TestCase;

class FinancialYearTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_default_transaction_date_keeps_today_when_it_is_in_the_logged_in_year(): void
    {
        Carbon::setTestNow('2026-10-06');
        session(['financial_year' => '2026-2027']);

        $this->assertSame('2026-10-06', FinancialYear::defaultTransactionDate()->toDateString());
    }

    public function test_default_transaction_date_uses_the_end_of_a_closed_logged_in_year(): void
    {
        Carbon::setTestNow('2026-10-06');
        session(['financial_year' => '2025-2026']);

        $this->assertSame('2026-03-31', FinancialYear::defaultTransactionDate()->toDateString());
    }
}
