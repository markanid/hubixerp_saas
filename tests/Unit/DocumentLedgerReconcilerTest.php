<?php

namespace Tests\Unit;

use App\Support\DocumentLedgerReconciler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentLedgerReconcilerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        session(['financial_year' => '2026-2027']);

        Schema::create('ledgerbook', function (Blueprint $table): void {
            $table->id('lb_id');
            $table->string('lb_vno');
            $table->unsignedBigInteger('lb_vid');
            $table->date('lb_date');
            $table->string('lb_type');
            $table->decimal('lb_amount', 12, 2)->default(0);
            $table->decimal('lb_opbalance', 12, 2)->default(0);
            $table->decimal('lb_tramount', 12, 2)->default(0);
            $table->decimal('lb_clbalance', 12, 2)->default(0);
            $table->unsignedBigInteger('lb_paymode')->nullable();
            $table->unsignedBigInteger('lb_payee');
            $table->string('status')->nullable();
            $table->string('financial_year')->nullable();
        });
        Schema::create('vendor_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payment_ledgerbook_id');
            $table->unsignedBigInteger('source_ledgerbook_id');
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->decimal('amount', 12, 2);
        });
    }

    public function test_purchase_edit_updates_the_original_entry_and_keeps_external_payment_allocations(): void
    {
        DB::table('ledgerbook')->insert([
            'lb_id' => 1, 'lb_vno' => 'P0001', 'lb_vid' => 10, 'lb_date' => '2026-05-01',
            'lb_type' => 'p', 'lb_amount' => 0, 'lb_opbalance' => 0, 'lb_tramount' => 12700,
            'lb_clbalance' => 12700, 'lb_paymode' => 1, 'lb_payee' => 7, 'status' => '1', 'financial_year' => '2026-2027',
        ]);
        DB::table('vendor_payment_allocations')->insert([
            ['payment_ledgerbook_id' => 20, 'source_ledgerbook_id' => 1, 'source_type' => 'p', 'source_id' => 10, 'amount' => 12000],
            ['payment_ledgerbook_id' => 21, 'source_ledgerbook_id' => 1, 'source_type' => 'p', 'source_id' => 10, 'amount' => 500],
        ]);

        $summary = DocumentLedgerReconciler::reconcile(10, 'p', 'pp', 7, true, '2026-05-02', 12500, 2, 'P0001');

        $this->assertSame(12500.0, $summary['total_paid']);
        $this->assertDatabaseHas('ledgerbook', ['lb_id' => 1, 'lb_tramount' => 12500, 'lb_date' => '2026-05-02', 'lb_paymode' => 2]);
        $this->assertSame(2, DB::table('vendor_payment_allocations')->count());
    }

    public function test_purchase_edit_rejects_a_total_lower_than_already_allocated_payments(): void
    {
        DB::table('ledgerbook')->insert([
            'lb_id' => 1, 'lb_vno' => 'P0001', 'lb_vid' => 10, 'lb_date' => '2026-05-01',
            'lb_type' => 'p', 'lb_amount' => 0, 'lb_opbalance' => 0, 'lb_tramount' => 12700,
            'lb_clbalance' => 12700, 'lb_paymode' => 1, 'lb_payee' => 7, 'status' => '1', 'financial_year' => '2026-2027',
        ]);
        DB::table('vendor_payment_allocations')->insert([
            'payment_ledgerbook_id' => 20, 'source_ledgerbook_id' => 1, 'source_type' => 'p', 'source_id' => 10, 'amount' => 12500,
        ]);

        $this->expectException(ValidationException::class);
        DocumentLedgerReconciler::reconcile(10, 'p', 'pp', 7, true, '2026-05-01', 12499, 1, 'P0001');
    }
}
