<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Services\BatchInventoryService;
use Tests\TestCase;

class BatchInventoryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('company', function (Blueprint $table) {
            $table->id();
            $table->string('inventory_mode')->default('standard');
            $table->unsignedInteger('expiry_alert_days')->default(30);
        });
        Schema::create('product', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('product');
            $table->boolean('is_batch_managed')->default(false);
            $table->decimal('uqty', 15, 2)->default(1);
            $table->decimal('pprice', 15, 2)->default(0);
            $table->decimal('mrp', 15, 2)->nullable();
            $table->decimal('price', 15, 2)->nullable();
        });
        Schema::create('stock', function (Blueprint $table) {
            $table->id('stock_id');
            $table->date('stock_date')->nullable();
            $table->string('stock_product_id');
            $table->decimal('stock_qty', 15, 2)->default(0);
        });
        Schema::create('stock_batches', function (Blueprint $table) {
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

    public function test_standard_mode_does_not_create_batch_stock(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'standard']);
        $product = $this->product();

        $result = (new BatchInventoryService)->receivePurchase(
            $product, ['batch_no' => 'B001'], 1, 1, '2026-06-22', 10
        );

        $this->assertNull($result);
        $this->assertDatabaseCount('stock_batches', 0);
    }

    public function test_purchase_merges_same_batch_and_sale_uses_earliest_expiry_first(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'batch']);
        $product = $this->product();
        $service = new BatchInventoryService;

        $service->receivePurchase($product, [
            'batch_no' => 'LATE', 'expiry_date' => '03/2027', 'unit_price' => 8, 'mrp' => 12,
        ], 1, 1, '2026-06-22', 15);
        $service->receivePurchase($product, [
            'batch_no' => 'EARLY', 'expiry_date' => '12/2026', 'unit_price' => 7, 'mrp' => 11,
        ], 2, 2, '2026-06-22', 10);
        $service->receivePurchase($product, [
            'batch_no' => 'EARLY', 'expiry_date' => '12/2026', 'unit_price' => 7, 'mrp' => 11,
        ], 3, 3, '2026-06-22', 5);

        $service->allocateSale($product, 20, 10, 20, '2026-06-22');

        $this->assertDatabaseHas('stock_batches', [
            'batch_no' => 'EARLY', 'quantity' => 15, 'available_quantity' => 0,
        ]);
        $this->assertDatabaseHas('stock_batches', [
            'batch_no' => 'LATE', 'available_quantity' => 10,
        ]);
    }

    public function test_enabling_batch_mode_converts_existing_balance_into_an_opening_batch(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'batch']);
        $product = $this->product();
        $product->update(['uqty' => 10, 'pprice' => 80, 'mrp' => 120, 'price' => 110]);
        DB::table('stock')->insert([
            'stock_date' => '2026-06-22',
            'stock_product_id' => $product->product_code,
            'stock_qty' => 30,
        ]);

        (new BatchInventoryService)->bootstrapExistingStock();

        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $product->product_code,
            'batch_no' => 'OPENING-'.$product->product_code,
            'purchase_rate' => 8,
            'quantity' => 30,
            'available_quantity' => 30,
        ]);
        $this->assertDatabaseHas('batch_movements', [
            'movement_type' => 'opening',
            'reference_type' => 'opening',
            'quantity_in' => 30,
        ]);
    }

    public function test_opening_stock_creates_a_sellable_batch_and_movement(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'batch']);
        $product = $this->product();

        $batch = (new BatchInventoryService)->receiveOpening($product, [
            'batch_no' => 'OPEN-001',
            'expiry_date' => '2027-03-31',
            'purchase_rate' => 8,
            'mrp' => 12,
            'sale_price' => 11,
        ], $product->id, '2026-06-22', 20);

        $this->assertNotNull($batch);
        $this->assertSame(20.0, (float) $batch->available_quantity);
        $this->assertDatabaseHas('batch_movements', [
            'stock_batch_id' => $batch->id,
            'movement_type' => 'opening',
            'reference_type' => 'opening',
            'reference_id' => $product->id,
            'quantity_in' => 20,
        ]);
    }

    public function test_existing_batch_number_rejects_a_different_mrp(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'batch']);
        $product = $this->product();
        $service = new BatchInventoryService;

        $service->receivePurchase($product, [
            'batch_no' => 'B001', 'expiry_date' => '12/2026', 'mrp' => 12,
        ], 1, 1, '2026-06-22', 5);

        $this->expectException(ValidationException::class);
        $service->receivePurchase($product, [
            'batch_no' => 'B001', 'expiry_date' => '12/2026', 'mrp' => 13,
        ], 2, 2, '2026-06-23', 5);
    }

    public function test_existing_batch_number_rejects_different_purchase_or_sale_pricing(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'batch']);
        $product = $this->product();
        $service = new BatchInventoryService;

        $service->receivePurchase($product, [
            'batch_no' => 'B001', 'expiry_date' => '12/2026', 'unit_price' => 8,
            'mrp' => 12, 'sale_price' => 11,
        ], 1, 1, '2026-06-22', 5);

        try {
            $service->receivePurchase($product, [
                'batch_no' => 'B001', 'expiry_date' => '12/2026', 'unit_price' => 9,
                'mrp' => 12, 'sale_price' => 11,
            ], 2, 2, '2026-06-23', 5);
            $this->fail('Expected a purchase-price validation failure.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Purchase price', $exception->getMessage());
        }

        $this->expectException(ValidationException::class);
        $service->receivePurchase($product, [
            'batch_no' => 'B001', 'expiry_date' => '12/2026', 'unit_price' => 8,
            'mrp' => 12, 'sale_price' => 10,
        ], 3, 3, '2026-06-24', 5);
    }

    public function test_manual_batch_cannot_exceed_its_available_quantity(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'batch']);
        $product = $this->product();
        $service = new BatchInventoryService;
        $batch = $service->receivePurchase($product, [
            'batch_no' => 'B001', 'expiry_date' => '12/2026',
        ], 1, 1, '2026-06-22', 5);

        $this->expectException(ValidationException::class);
        $service->allocateSale($product, 6, 10, 20, '2026-06-22', $batch->id);
    }

    public function test_out_of_stock_setting_cannot_leave_a_batch_sale_partially_unallocated(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'batch']);
        $product = $this->product();
        $service = new BatchInventoryService;
        $service->receivePurchase($product, [
            'batch_no' => 'B001', 'expiry_date' => '12/2026', 'mrp' => 12,
        ], 1, 1, '2026-06-22', 5);

        $this->expectException(ValidationException::class);
        $service->allocateSale($product, 6, 10, 20, '2026-06-22', allowOutOfStock: true);
    }

    public function test_sale_return_restores_the_original_allocated_batches(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'batch']);
        $product = $this->product();
        $service = new BatchInventoryService;
        $early = $service->receivePurchase($product, [
            'batch_no' => 'EARLY', 'expiry_date' => '12/2026',
        ], 1, 1, '2026-06-22', 5);
        $late = $service->receivePurchase($product, [
            'batch_no' => 'LATE', 'expiry_date' => '03/2027',
        ], 2, 2, '2026-06-22', 10);

        $service->allocateSale($product, 8, 10, 99, '2026-06-22');
        $service->returnSaleDetail(99, 6, 20, 200, '2026-06-23');

        $this->assertSame(5.0, (float) $early->fresh()->available_quantity);
        $this->assertSame(8.0, (float) $late->fresh()->available_quantity);
    }

    public function test_purchase_return_reduces_quantity_and_available_stock(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'batch']);
        $product = $this->product();
        $service = new BatchInventoryService;
        $batch = $service->receivePurchase($product, [
            'batch_no' => 'B001', 'expiry_date' => '12/2026',
        ], 1, 1, '2026-06-22', 10);

        $service->purchaseReturn($batch, 4, 30, 300, '2026-06-23');

        $this->assertSame(6.0, (float) $batch->fresh()->quantity);
        $this->assertSame(6.0, (float) $batch->fresh()->available_quantity);
    }

    private function product(): Product
    {
        DB::table('product')->insert([
            'product_code' => 'PRD_001',
            'product' => 'Paracetamol',
            'is_batch_managed' => true,
        ]);

        return Product::where('product_code', 'PRD_001')->firstOrFail();
    }
}
