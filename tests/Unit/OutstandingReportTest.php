<?php

namespace Tests\Unit;

use App\Support\OutstandingReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OutstandingReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach (['vendor' => 'cp_name', 'customer' => 'customer'] as $table => $name) {
            Schema::create($table, function (Blueprint $t) use ($name) {
                $t->id(); $t->string($name); $t->decimal('op_balance')->default(0);
            });
        }
        Schema::create('ledgerbook', function (Blueprint $t) {
            $t->id('lb_id'); $t->integer('lb_payee'); $t->string('lb_type');
            $t->date('lb_date'); $t->string('status')->nullable()->default('1');
            $t->decimal('lb_tramount')->default(0); $t->decimal('lb_clbalance');
        });
        DB::table('vendor')->insert(['id' => 1, 'cp_name' => 'Supplier A', 'op_balance' => 9999]);
        DB::table('customer')->insert(['id' => 1, 'customer' => 'Customer A', 'op_balance' => 9999]);
    }

    public function test_supplier_return_settles_account_and_future_entries_do_not_change_past_balance(): void
    {
        $this->entry(1, 'p', '2026-05-01', 1000, 1000);
        $this->entry(2, 'pp', '2026-05-02', 0, 500);
        $this->entry(3, 'pr', '2026-05-03', 500, 0);
        $this->assertEquals(500, OutstandingReport::rows(true, '2026-04-01', '2026-05-02')[0]['balance']);
        $this->assertSame([], OutstandingReport::rows(true, '2026-04-01', '2026-05-03'));
        $this->entry(4, 'loan', '2026-06-01', 200, 200);
        $this->assertSame([], OutstandingReport::rows(true, '2026-04-01', '2026-05-03'));
        $rows = OutstandingReport::rows(true, '2026-04-01', '2026-06-01');
        $this->assertCount(1, $rows);
        $this->assertSame('Purchase / Loan', $rows[0]['particular']);
        $this->assertEquals(200, $rows[0]['balance']);
        $this->assertStringContainsString('vendors/1', $rows[0]['_actions'][0]['url']);
    }

    public function test_latest_date_then_id_is_authoritative_and_customer_vendor_ids_are_separate(): void
    {
        $this->entry(1, 's', '2026-05-02', 1000, 123);
        $this->entry(2, 'svp', '2026-05-02', 0, 80);
        $this->entry(3, 's', '2026-05-01', 10000, 10000);
        $this->entry(4, 'loan_asset', '2026-05-03', 0, 999, '0');
        $this->entry(5, 'p', '2026-05-02', 5000, 5000);
        $rows = OutstandingReport::rows(false, '2026-04-01', '2026-05-03');
        $this->assertCount(1, $rows);
        $this->assertEquals(80, $rows[0]['balance']);
        $this->assertStringContainsString('customers/1', $rows[0]['_actions'][0]['url']);
    }

    public function test_brought_forward_and_opening_only_debt_are_retained(): void
    {
        $this->entry(1, 'p', '2026-03-31', 500, 450);
        DB::table('vendor')->insert(['id' => 2, 'cp_name' => 'Supplier B', 'op_balance' => 250]);
        $rows = OutstandingReport::rows(true, '2026-04-01', '2026-06-01');
        $this->assertEquals([450, 250], array_column($rows, 'balance'));
        $this->assertSame(['Opening Balance', 'Opening Balance'], array_column($rows, 'particular'));
    }

    private function entry(int $id, string $type, string $date, float $charge, float $closing, string $status = '1'): void
    {
        DB::table('ledgerbook')->insert(['lb_id' => $id, 'lb_payee' => 1, 'lb_type' => $type,
            'lb_date' => $date, 'lb_tramount' => $charge, 'lb_clbalance' => $closing, 'status' => $status]);
    }
}
