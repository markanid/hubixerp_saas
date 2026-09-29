<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Product\app\Models\StockLedger;
use Tests\TestCase;

class StockLedgerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('stock_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->string('stock_item_id');
            $table->date('stock_date');
            $table->string('stock_type');
            $table->unsignedBigInteger('stock_ref_id')->nullable();
            $table->decimal('stock_in', 15, 2)->default(0);
            $table->decimal('stock_out', 15, 2)->default(0);
            $table->decimal('stock_balance', 15, 2)->default(0);
            $table->string('financial_year')->nullable();
            $table->timestamps();
        });
    }

    public function test_product_edits_reuse_only_the_product_adjustment_ledger_entry(): void
    {
        StockLedger::updateStockLedger('2026-09-01', 'PRD_001', 10, 1, 'INIT');
        StockLedger::updateStockLedger('2026-09-02', 'PRD_001', -3, 2, 's');

        StockLedger::updateProductAdjustmentLedger('2026-09-03', 'PRD_001', 2, 1);
        StockLedger::updateStockLedger('2026-09-04', 'PRD_001', 1, 3, 'p');
        StockLedger::updateProductAdjustmentLedger('2026-09-05', 'PRD_001', -4, 1);

        $adjustments = StockLedger::where('stock_item_id', 'PRD_001')
            ->where('stock_type', 'ADJUST')
            ->where('stock_ref_id', 1)
            ->get();

        $this->assertCount(1, $adjustments);
        $this->assertSame(2.0, (float) $adjustments->first()->stock_out);
        $this->assertSame(0.0, (float) $adjustments->first()->stock_in);
        $this->assertSame(3.0, (float) StockLedger::where('stock_type', 's')->value('stock_out'));
        $this->assertSame(6.0, (float) StockLedger::orderByDesc('id')->value('stock_balance'));
    }
}
