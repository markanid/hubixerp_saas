<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Product\app\Models\MrpStockLot;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\StockBatch;
use Modules\Pos\app\Services\StockService;
use Modules\Product\app\Services\BatchInventoryService;
use Modules\Product\app\Services\InventoryLabelService;
use Modules\Product\app\Services\MrpInventoryService;
use Tests\TestCase;

class InventoryLabelServiceTest extends TestCase
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
        });
        Schema::create('product', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('product');
            $table->boolean('is_batch_managed')->default(false);
            $table->unsignedInteger('typeid')->default(2);
            $table->string('status')->default('1');
        });
        Schema::create('mrp_stock_lots', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->string('purchase_voucher')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_rate', 15, 2)->default(0);
            $table->decimal('mrp', 15, 2)->default(0);
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->decimal('margin_percentage', 10, 2)->default(0);
            $table->decimal('margin_amount', 15, 2)->default(0);
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('available_quantity', 15, 2)->default(0);
            $table->timestamps();
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
        });
        Schema::create('inventory_labels', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->string('label_type');
            $table->unsignedBigInteger('mrp_stock_lot_id')->nullable()->unique();
            $table->unsignedBigInteger('stock_batch_id')->nullable()->unique();
            $table->string('barcode')->unique();
            $table->string('signature')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'label_type', 'signature']);
        });
    }

    public function test_each_inventory_source_receives_a_stable_unique_barcode(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product();
        $lot = MrpStockLot::create([
            'product_id' => $product->product_code,
            'purchase_voucher' => 'PUR-001',
            'quantity' => 10,
            'available_quantity' => 10,
        ]);
        $batch = StockBatch::create([
            'product_id' => $product->product_code,
            'batch_no' => 'B001',
            'quantity' => 10,
            'available_quantity' => 10,
        ]);
        $service = new InventoryLabelService();

        $mrpLabel = $service->ensureForMrpLot($lot);
        $batchLabel = $service->ensureForBatch($batch);

        $this->assertSame($mrpLabel->id, $service->ensureForMrpLot($lot)->id);
        $this->assertSame(12, strlen($mrpLabel->barcode));
        $this->assertSame(12, strlen($batchLabel->barcode));
        $this->assertStringStartsWith('81', $mrpLabel->barcode);
        $this->assertStringStartsWith('82', $batchLabel->barcode);
        $this->assertNotSame($mrpLabel->barcode, $batchLabel->barcode);
    }

    public function test_resolution_respects_inventory_mode_and_available_stock(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product();
        $lot = MrpStockLot::create([
            'product_id' => $product->product_code,
            'purchase_voucher' => 'PUR-001',
            'quantity' => 10,
            'available_quantity' => 10,
        ]);
        $service = new InventoryLabelService();
        $label = $service->ensureForMrpLot($lot);

        $this->assertSame($lot->id, $service->resolveActive($label->barcode)?->mrp_stock_lot_id);

        $lot->update(['available_quantity' => 0]);
        $this->assertNull($service->resolveActive($label->barcode));

        $lot->update(['available_quantity' => 5]);
        DB::table('company')->update(['inventory_mode' => 'batch']);
        $this->assertNull($service->resolveActive($label->barcode));
    }

    public function test_identical_mrp_purchases_share_one_slot_and_aggregate_stock(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product();
        $first = MrpStockLot::create([
            'product_id' => $product->product_code,
            'purchase_voucher' => 'PUR-001',
            'purchase_date' => '2026-08-01',
            'purchase_rate' => 100,
            'mrp' => 150,
            'sale_price' => 140,
            'quantity' => 10,
            'available_quantity' => 4,
        ]);
        $second = MrpStockLot::create([
            'product_id' => $product->product_code,
            'purchase_voucher' => 'PUR-002',
            'purchase_date' => '2026-08-05',
            'purchase_rate' => 100,
            'mrp' => 150,
            'sale_price' => 140,
            'quantity' => 10,
            'available_quantity' => 6,
        ]);
        $labels = new InventoryLabelService();

        $firstLabel = $labels->ensureForMrpLot($first);
        $secondLabel = $labels->ensureForMrpLot($second);

        $this->assertSame($firstLabel->id, $secondLabel->id);
        $this->assertDatabaseCount('inventory_labels', 1);
        $this->assertSame(10.0, $labels->availableQuantity($firstLabel));

        $first->update(['quantity' => 0, 'available_quantity' => 0]);

        $this->assertCount(1, $labels->labelsForProduct($product, 'mrp'));
        $this->assertSame($second->id, $labels->resolveActive($firstLabel->barcode)?->mrpStockLot?->id);
    }

    public function test_pos_search_returns_the_scanned_mrp_lot_selection(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product();
        $lot = MrpStockLot::create([
            'product_id' => $product->product_code,
            'purchase_voucher' => 'PUR-001',
            'quantity' => 10,
            'available_quantity' => 10,
        ]);
        $labels = new InventoryLabelService();
        $label = $labels->ensureForMrpLot($lot);
        $stock = new StockService(
            new BatchInventoryService($labels),
            new MrpInventoryService($labels),
            $labels
        );

        $result = $stock->search($label->barcode)->first();

        $this->assertSame($product->product_code, $result['product_code']);
        $this->assertSame($lot->id, $result['mrp_stock_lot_id']);
        $this->assertSame($label->barcode, $result['barcode']);
        $this->assertSame(10.0, $result['raw_stock']);
    }

    private function product(): Product
    {
        DB::table('product')->insert([
            'product_code' => 'PRD_001',
            'product' => 'Paracetamol',
            'is_batch_managed' => true,
            'typeid' => 2,
            'status' => '1',
        ]);

        return Product::where('product_code', 'PRD_001')->firstOrFail();
    }
}
