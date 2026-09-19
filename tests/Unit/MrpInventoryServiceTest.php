<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Product\app\Models\MrpStockLot;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Services\MrpInventoryService;
use Tests\TestCase;

class MrpInventoryServiceTest extends TestCase
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
            $table->string('unit')->default('STRIP');
            $table->decimal('uqty', 15, 2)->default(10);
            $table->decimal('pprice', 15, 2)->default(80);
            $table->decimal('mrp', 15, 2)->nullable();
            $table->decimal('price', 15, 2)->nullable();
        });
        Schema::create('stock', function (Blueprint $table) {
            $table->id('stock_id');
            $table->date('stock_date')->nullable();
            $table->string('stock_product_id');
            $table->decimal('stock_qty', 15, 2)->default(0);
        });
        Schema::create('mrp_stock_lots', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->unsignedBigInteger('purchase_detail_id')->nullable();
            $table->string('purchase_voucher')->nullable();
            $table->date('purchase_date');
            $table->decimal('purchase_rate', 15, 2)->default(0);
            $table->decimal('mrp', 15, 2);
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->decimal('margin_percentage', 10, 2)->nullable();
            $table->decimal('margin_amount', 15, 2)->nullable();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('available_quantity', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('mrp_stock_movements', function (Blueprint $table) {
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

    public function test_standard_mode_keeps_mrp_lot_tracking_disabled(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'standard']);

        $lot = $this->service()->receivePurchase(
            $this->product(), $this->purchaseItem(100), 1, 1, 'PUR-001', '2026-08-06', 100
        );

        $this->assertNull($lot);
        $this->assertDatabaseCount('mrp_stock_lots', 0);
    }

    public function test_each_purchase_line_remains_a_separate_mrp_lot_and_sale_uses_the_selected_one(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product();
        $service = $this->service();

        $first = $service->receivePurchase($product, $this->purchaseItem(100, 80), 1, 11, 'PUR-001', '2026-08-01', 100);
        $second = $service->receivePurchase($product, $this->purchaseItem(120, 90), 2, 22, 'PUR-002', '2026-08-06', 50);

        $service->allocateSale($product, 10, 7, 70, '2026-08-06', $second->id, 11);

        $this->assertSame(100.0, (float) $first->fresh()->available_quantity);
        $this->assertSame(40.0, (float) $second->fresh()->available_quantity);
        $this->assertDatabaseHas('mrp_stock_movements', [
            'reference_type' => 'sale',
            'reference_detail_id' => 70,
            'mrp_stock_lot_id' => $second->id,
            'quantity_out' => 10,
        ]);
    }

    public function test_opening_stock_creates_a_sellable_mrp_slot_and_movement(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product();

        $lot = $this->service()->receiveOpening($product, [
            'purchase_rate' => 8,
            'mrp' => 120,
            'sale_price' => 110,
        ], $product->id, '2026-08-01', 25);

        $this->assertNotNull($lot);
        $this->assertSame(25.0, (float) $lot->available_quantity);
        $this->assertSame(110.0, (float) $lot->sale_price);
        $this->assertDatabaseHas('mrp_stock_movements', [
            'mrp_stock_lot_id' => $lot->id,
            'movement_type' => 'opening',
            'reference_type' => 'opening',
            'reference_id' => $product->id,
            'quantity_in' => 25,
        ]);
    }

    public function test_sale_cannot_exceed_the_selected_lot_mrp_or_available_quantity(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product();
        $lot = $this->service()->receivePurchase(
            $product, $this->purchaseItem(100), 1, 1, 'PUR-001', '2026-08-06', 5
        );

        try {
            $this->service()->allocateSale($product, 1, 2, 2, '2026-08-06', $lot->id, 10.01);
            $this->fail('Expected an MRP validation failure.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('cannot exceed MRP', $exception->getMessage());
        }

        $this->expectException(ValidationException::class);
        $this->service()->allocateSale($product, 6, 3, 3, '2026-08-06', $lot->id, 10);
    }

    public function test_out_of_stock_setting_does_not_bypass_mrp_slot_availability(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product();
        $lot = $this->service()->receivePurchase(
            $product, $this->purchaseItem(100), 1, 1, 'PUR-001', '2026-08-06', 5
        );

        $this->expectException(ValidationException::class);
        $this->service()->allocateSale($product, 6, 3, 3, '2026-08-06', $lot->id, 10, allowOutOfStock: true);
    }

    public function test_sale_return_restores_the_exact_original_mrp_lot(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product();
        $service = $this->service();
        $first = $service->receivePurchase($product, $this->purchaseItem(100), 1, 1, 'PUR-001', '2026-08-01', 20);
        $second = $service->receivePurchase($product, $this->purchaseItem(120), 2, 2, 'PUR-002', '2026-08-06', 20);

        $service->allocateSale($product, 8, 9, 90, '2026-08-06', $second->id, 11);
        $service->returnSaleDetail(90, 3, 10, 100, '2026-08-07');

        $this->assertSame(20.0, (float) $first->fresh()->available_quantity);
        $this->assertSame(15.0, (float) $second->fresh()->available_quantity);
    }

    public function test_identical_pricing_uses_one_barcode_slot_and_allocates_fifo_across_lots(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product();
        $service = $this->service();
        $first = $service->receivePurchase($product, $this->purchaseItem(100, 80), 1, 1, 'PUR-001', '2026-08-01', 5);
        $second = $service->receivePurchase($product, $this->purchaseItem(100, 80), 2, 2, 'PUR-002', '2026-08-02', 8);

        $service->allocateSale($product, 10, 9, 90, '2026-08-03', $first->id, 9);

        $this->assertDatabaseCount('inventory_labels', 1);
        $this->assertSame(0.0, (float) $first->fresh()->available_quantity);
        $this->assertSame(3.0, (float) $second->fresh()->available_quantity);
        $this->assertSame(2, DB::table('mrp_stock_movements')->where('reference_detail_id', 90)->where('movement_type', 'sale')->count());

        $service->returnSaleDetail(90, 6, 10, 100, '2026-08-04');

        $this->assertSame(5.0, (float) $first->fresh()->available_quantity);
        $this->assertSame(4.0, (float) $second->fresh()->available_quantity);
    }

    public function test_selected_lot_controls_sale_price_and_mrp_pricing_mode(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product();
        $service = $this->service();
        $lot = $service->receivePurchase(
            $product,
            $this->purchaseItem(120, 90),
            2,
            22,
            'PUR-002',
            '2026-08-07',
            50
        );
        $line = [[
            'product_id' => $product->product_code,
            'mrp_stock_lot_id' => $lot->id,
            'unit' => 'STRIP',
            'unit_price' => 1,
        ]];

        $this->assertSame(110.0, (float) $service->applySalePricing($line, false)[0]['unit_price']);
        $this->assertSame(120.0, (float) $service->applySalePricing($line, true)[0]['unit_price']);

        $line[0]['unit'] = 'No.s';
        $this->assertSame(11.0, (float) $service->applySalePricing($line, false)[0]['unit_price']);
        $this->assertSame(12.0, (float) $service->applySalePricing($line, true)[0]['unit_price']);
    }

    public function test_manual_sale_return_requires_and_preserves_its_mrp(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product();

        $this->service()->manualSaleReturn($product, 10, 15, 150, '2026-08-07', 135);

        $lot = MrpStockLot::where('purchase_voucher', 'MANUAL-RETURN')->firstOrFail();
        $this->assertSame(135.0, (float) $lot->mrp);
        $this->assertSame(10.0, (float) $lot->available_quantity);
    }

    public function test_negative_opening_stock_becomes_a_deficit_that_future_purchases_settle_first(): void
    {
        DB::table('company')->insert(['inventory_mode' => 'mrp']);
        $product = $this->product();
        DB::table('stock')->insert([
            'stock_date' => '2026-08-06',
            'stock_product_id' => $product->product_code,
            'stock_qty' => -20,
        ]);
        $service = $this->service();

        $service->bootstrapExistingStock('standard');
        $deficit = MrpStockLot::where('purchase_voucher', 'OPENING-DEFICIT')->firstOrFail();
        $this->assertSame(-20.0, (float) $deficit->available_quantity);

        $purchase = $service->receivePurchase(
            $product, $this->purchaseItem(100), 25, 250, 'PUR-025', '2026-08-07', 30
        );

        $this->assertSame(0.0, (float) $deficit->fresh()->available_quantity);
        $this->assertSame(10.0, (float) $purchase->fresh()->available_quantity);
        $this->assertSame(10.0, (float) MrpStockLot::sum('available_quantity'));

        $service->reverseReference('purchase', 25, '2026-08-07');
        $this->assertSame(-20.0, (float) $deficit->fresh()->available_quantity);
        $this->assertSame(0.0, (float) $purchase->fresh()->available_quantity);
    }

    private function service(): MrpInventoryService
    {
        return new MrpInventoryService;
    }

    private function product(): Product
    {
        DB::table('product')->insert([
            'product_code' => 'PRD-001',
            'product' => 'Paracetamol',
            'unit' => 'STRIP',
            'uqty' => 10,
            'pprice' => 80,
            'mrp' => 100,
            'price' => 90,
        ]);

        return Product::where('product_code', 'PRD-001')->firstOrFail();
    }

    private function purchaseItem(float $mrp, float $unitPrice = 80): array
    {
        return [
            'unit' => 'STRIP',
            'unit_qty' => 10,
            'unit_price' => $unitPrice,
            'mrp' => $mrp,
            'sale_price' => $mrp - 10,
            'margin_percentage' => round((10 / $mrp) * 100, 2),
            'margin_amount' => 10,
        ];
    }
}
