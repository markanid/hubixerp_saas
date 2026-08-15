<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Product\app\Models\BatchMovement;
use Modules\Product\app\Models\StockBatch;
use Modules\Purchase\app\Models\PurchaseDetail;
use Tests\TestCase;

class PurchaseDetailBatchMovementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('purchase_detail', function (Blueprint $table) {
            $table->id('pud_id');
            $table->string('pud_itemid');
        });
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->string('batch_no');
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('available_quantity', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('batch_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_batch_id');
            $table->string('product_id');
            $table->string('movement_type');
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->unsignedBigInteger('reference_detail_id')->nullable();
            $table->date('movement_date');
            $table->decimal('quantity_in', 15, 2)->default(0);
            $table->decimal('quantity_out', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->decimal('purchase_rate', 15, 2)->default(0);
            $table->decimal('sale_rate', 15, 2)->default(0);
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_purchase_detail_resolves_only_its_purchase_batch_movement(): void
    {
        $detail = PurchaseDetail::create([
            'pud_itemid' => 'PRD_001',
        ]);
        $batch = StockBatch::create([
            'product_id' => 'PRD_001',
            'batch_no' => 'BATCH-01',
            'quantity' => 5,
            'available_quantity' => 5,
        ]);

        BatchMovement::create([
            'stock_batch_id' => $batch->id,
            'product_id' => 'PRD_001',
            'movement_type' => 'sale',
            'reference_type' => 'sale',
            'reference_id' => 99,
            'reference_detail_id' => $detail->pud_id,
            'movement_date' => '2026-08-14',
        ]);
        $purchaseMovement = BatchMovement::create([
            'stock_batch_id' => $batch->id,
            'product_id' => 'PRD_001',
            'movement_type' => 'purchase',
            'reference_type' => 'purchase',
            'reference_id' => 10,
            'reference_detail_id' => $detail->pud_id,
            'movement_date' => '2026-08-14',
            'quantity_in' => 5,
            'balance_after' => 5,
        ]);

        $detail->load('batchMovement.batch');

        $this->assertSame($purchaseMovement->id, $detail->batchMovement?->id);
        $this->assertSame($batch->id, $detail->batchMovement?->batch?->id);
    }
}
