<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Services\BatchInventoryService;
use Modules\Product\app\Services\MrpInventoryService;
use Modules\Product\app\Services\OpeningStockService;
use Tests\TestCase;

class OpeningStockServiceTest extends TestCase
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
            $table->string('product');
            $table->unsignedInteger('typeid')->default(2);
            $table->boolean('is_batch_managed')->default(false);
            $table->decimal('uqty', 15, 2)->default(1);
            $table->decimal('pprice', 15, 2)->default(0);
            $table->decimal('mrp', 15, 2)->default(0);
            $table->decimal('price', 15, 2)->default(0);
        });
        Schema::create('stock', function (Blueprint $table): void {
            $table->id('stock_id');
            $table->date('stock_date')->nullable();
            $table->string('stock_product_id');
            $table->decimal('stock_qty', 15, 2)->default(0);
        });
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
        Schema::create('mrp_stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->string('product_id');
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->unsignedBigInteger('purchase_detail_id')->nullable();
            $table->string('purchase_voucher')->nullable();
            $table->date('purchase_date');
            $table->decimal('purchase_rate', 15, 2)->default(0);
            $table->decimal('mrp', 15, 2);
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->decimal('margin_percentage', 15, 2)->default(0);
            $table->decimal('margin_amount', 15, 2)->default(0);
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('available_quantity', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('mrp_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mrp_stock_lot_id');
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
            $table->decimal('mrp', 15, 2)->default(0);
            $table->decimal('sale_rate', 15, 2)->default(0);
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('product_id');
            $table->string('batch_no');
            $table->date('expiry_date')->nullable();
            $table->decimal('purchase_rate', 15, 2)->default(0);
            $table->decimal('mrp', 15, 2)->default(0);
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('available_quantity', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'batch_no']);
        });
        Schema::create('batch_movements', function (Blueprint $table): void {
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

    public function test_mrp_opening_stock_keeps_legacy_and_tracked_balances_in_sync(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product(false);

        $this->service()->record($product, 'mrp', [[
            'quantity' => 3,
            'purchase_price' => 80,
            'mrp' => 120,
            'sale_price' => 110,
        ]], '2026-09-18');

        $this->assertDatabaseHas('mrp_stock_lots', [
            'product_id' => $product->product_code,
            'purchase_rate' => 8,
            'available_quantity' => 30,
        ]);
        $this->assertDatabaseHas('stock', ['stock_product_id' => $product->product_code, 'stock_qty' => 30]);
        $this->assertDatabaseHas('stock_ledgers', ['stock_type' => 'OPENING', 'stock_in' => 30, 'stock_balance' => 30]);
    }

    public function test_batch_opening_stock_keeps_legacy_and_tracked_balances_in_sync(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'batch']);
        $product = $this->product(true);

        $this->service()->record($product, 'batch', [[
            'batch_no' => 'B-OPEN',
            'expiry_date' => '2027-09-30',
            'quantity' => 2,
            'purchase_price' => 80,
            'mrp' => 120,
            'sale_price' => 110,
        ]], '2026-09-18');

        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $product->product_code,
            'batch_no' => 'B-OPEN',
            'purchase_rate' => 8,
            'available_quantity' => 20,
        ]);
        $this->assertDatabaseHas('stock', ['stock_product_id' => $product->product_code, 'stock_qty' => 20]);
        $this->assertDatabaseHas('stock_ledgers', ['stock_type' => 'OPENING', 'stock_in' => 20, 'stock_balance' => 20]);
    }

    public function test_existing_legacy_balance_can_be_allocated_without_doubling_stock(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product(false);
        DB::table('stock')->insert([
            'stock_date' => '2026-09-17',
            'stock_product_id' => $product->product_code,
            'stock_qty' => 30,
        ]);

        $this->service()->record($product, 'mrp', [[
            'quantity' => 3,
            'purchase_price' => 80,
            'mrp' => 120,
            'sale_price' => 110,
        ]], '2026-09-18', true);

        $this->assertSame(30.0, (float) DB::table('stock')->value('stock_qty'));
        $this->assertSame(30.0, (float) DB::table('mrp_stock_lots')->value('available_quantity'));
        $this->assertDatabaseHas('stock_ledgers', ['stock_type' => 'OPENING', 'stock_in' => 30, 'stock_balance' => 30]);
    }

    private function service(): OpeningStockService
    {
        return new OpeningStockService(new BatchInventoryService, new MrpInventoryService);
    }

    private function product(bool $batchManaged): Product
    {
        $id = DB::table('product')->insertGetId([
            'product_code' => $batchManaged ? 'PRD-BATCH' : 'PRD-MRP',
            'product' => 'Opening Product',
            'typeid' => 2,
            'is_batch_managed' => $batchManaged,
            'uqty' => 10,
            'pprice' => 80,
            'mrp' => 120,
            'price' => 110,
        ]);

        return Product::findOrFail($id);
    }
}
