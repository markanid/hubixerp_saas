<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\app\Models\StockValue;
use Modules\Finance\app\Services\InventoryValuationService;
use Tests\TestCase;

class InventoryValuationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('company', function (Blueprint $table): void {
            $table->id();
            $table->string('inventory_mode')->default('standard');
        });
        Schema::create('product', function (Blueprint $table): void {
            $table->id();
            $table->string('product_code')->unique();
            $table->decimal('uqty', 15, 2)->nullable();
            $table->decimal('pprice', 15, 2)->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('mrp', 15, 2)->nullable();
            $table->boolean('is_batch_managed')->default(false);
        });
        Schema::create('stock', function (Blueprint $table): void {
            $table->id('stock_id');
            $table->string('stock_product_id');
            $table->decimal('stock_qty', 15, 2)->default(0);
        });
        Schema::create('mrp_stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->string('product_id');
            $table->decimal('purchase_rate', 15, 2)->default(0);
            $table->decimal('mrp', 15, 2)->default(0);
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->decimal('available_quantity', 15, 2)->default(0);
        });
        Schema::create('stock_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('product_id');
            $table->string('batch_no');
            $table->decimal('purchase_rate', 15, 2)->default(0);
            $table->decimal('mrp', 15, 2)->default(0);
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->decimal('available_quantity', 15, 2)->default(0);
        });
        Schema::create('stock_value', function (Blueprint $table): void {
            $table->id('sv_id');
            $table->date('sv_date')->unique();
            $table->decimal('sv_pvalue', 15, 2)->default(0);
            $table->decimal('sv_svalue', 15, 2)->default(0);
            $table->boolean('status')->nullable();
        });
    }

    public function test_standard_mode_values_positive_stock_at_product_prices(): void
    {
        $this->setMode('standard');
        $this->product('PRD-1', 10, 80, 90, 100);
        $this->product('PRD-2', 0, 5, 8, 10);
        $this->stock('PRD-1', 25);
        $this->stock('PRD-2', 2);
        $this->stock('PRD-IGNORED', 0);

        $this->assertSame([
            'purchase_value' => 210.0,
            'sale_value' => 241.0,
            'mrp_value' => 270.0,
        ], $this->service()->totals());
    }

    public function test_mrp_mode_values_each_available_lot_at_its_own_mrp(): void
    {
        $this->setMode('mrp');
        $this->product('PRD-1', 10, 1, 1, 999);
        $this->stock('PRD-1', 999);
        $this->mrpLot('PRD-1', 8, 100, 90, 15);
        $this->mrpLot('PRD-1', 9, 120, 110, 20);
        $this->mrpLot('PRD-1', 50, 500, 450, 0);

        $this->assertSame([
            'purchase_value' => 300.0,
            'sale_value' => 355.0,
            'mrp_value' => 390.0,
        ], $this->service()->totals());
    }

    public function test_batch_mode_combines_batch_values_with_non_batch_legacy_stock(): void
    {
        $this->setMode('batch');
        $this->product('BATCHED', 5, 1, 1, 70, true);
        $this->product('STANDARD', 2, 20, 25, 30, false);
        $this->stock('BATCHED', 1000);
        $this->stock('STANDARD', 4);
        $this->batch('BATCHED', 'A', 6, 50, 45, 10);
        $this->batch('BATCHED', 'B', 7, 60, 55, 5);
        $this->batch('BATCHED', 'EMPTY', 99, 999, 999, 0);
        $this->batch('STANDARD', 'STALE', 99, 999, 999, 4);

        $this->assertSame([
            'purchase_value' => 135.0,
            'sale_value' => 195.0,
            'mrp_value' => 220.0,
        ], $this->service()->totals());
    }

    public function test_daily_snapshot_is_updated_to_zero_when_no_stock_remains(): void
    {
        $this->setMode('standard');
        DB::table('stock_value')->insert([
            'sv_date' => now()->toDateString(),
            'sv_pvalue' => 500,
            'sv_svalue' => 600,
        ]);

        StockValue::stockClosing();

        $this->assertDatabaseHas('stock_value', [
            'sv_date' => now()->toDateString(),
            'sv_pvalue' => 0,
            'sv_svalue' => 0,
        ]);
    }

    private function service(): InventoryValuationService
    {
        return app(InventoryValuationService::class);
    }

    private function setMode(string $mode): void
    {
        DB::table('company')->insert(['inventory_mode' => $mode]);
    }

    private function product(
        string $code,
        float $unitQuantity,
        float $purchasePrice,
        float $salePrice,
        float $mrp,
        bool $batchManaged = false
    ): void {
        DB::table('product')->insert([
            'product_code' => $code,
            'uqty' => $unitQuantity,
            'pprice' => $purchasePrice,
            'price' => $salePrice,
            'mrp' => $mrp,
            'is_batch_managed' => $batchManaged,
        ]);
    }

    private function stock(string $product, float $quantity): void
    {
        if (!DB::table('product')->where('product_code', $product)->exists()) {
            $this->product($product, 1, 1, 1, 1);
        }

        DB::table('stock')->insert([
            'stock_product_id' => $product,
            'stock_qty' => $quantity,
        ]);
    }

    private function mrpLot(
        string $product,
        float $purchaseRate,
        float $mrp,
        float $salePrice,
        float $available
    ): void {
        DB::table('mrp_stock_lots')->insert([
            'product_id' => $product,
            'purchase_rate' => $purchaseRate,
            'mrp' => $mrp,
            'sale_price' => $salePrice,
            'available_quantity' => $available,
        ]);
    }

    private function batch(
        string $product,
        string $batch,
        float $purchaseRate,
        float $mrp,
        float $salePrice,
        float $available
    ): void {
        DB::table('stock_batches')->insert([
            'product_id' => $product,
            'batch_no' => $batch,
            'purchase_rate' => $purchaseRate,
            'mrp' => $mrp,
            'sale_price' => $salePrice,
            'available_quantity' => $available,
        ]);
    }
}
