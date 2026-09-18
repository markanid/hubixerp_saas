<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Product\app\Models\MrpStockLot;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\StockBatch;
use Modules\Product\app\Services\InventoryLabelService;
use Tests\TestCase;

class ProductBarcodeSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        $this->withoutMiddleware();

        Schema::create('company', function (Blueprint $table) {
            $table->id();
            $table->string('inventory_mode')->default('standard');
        });
        Schema::create('product', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('product');
            $table->string('bar_code')->unique();
            $table->boolean('is_batch_managed')->default(false);
            $table->unsignedInteger('typeid')->default(2);
            $table->string('status')->default('1');
            $table->string('hsn_code')->nullable();
            $table->decimal('pprice')->default(15);
            $table->decimal('price')->default(25);
            $table->decimal('mrp')->default(30);
            $table->decimal('margin')->default(0);
            $table->decimal('amt_margin')->default(0);
            $table->string('unit')->default('No.s');
            $table->decimal('uqty')->default(1);
            $table->decimal('gst')->default(0);
        });
        Schema::create('stock', function (Blueprint $table) {
            $table->id('stock_id');
            $table->string('stock_product_id');
            $table->decimal('stock_qty')->default(10);
        });
        foreach (['mrp_stock_lots', 'stock_batches'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('product_id');
                $table->string('purchase_voucher')->nullable();
                $table->date('purchase_date')->nullable();
                $table->string('batch_no')->nullable();
                $table->date('expiry_date')->nullable();
                foreach (['purchase_rate', 'mrp', 'sale_price', 'margin_percentage', 'margin_amount', 'quantity', 'available_quantity'] as $column) {
                    $table->decimal($column, 15, 2)->default(0);
                }
                $table->timestamps();
            });
        }
        Schema::create('inventory_labels', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->string('label_type');
            $table->unsignedBigInteger('mrp_stock_lot_id')->nullable();
            $table->unsignedBigInteger('stock_batch_id')->nullable();
            $table->string('barcode')->unique();
            $table->string('signature')->nullable();
            $table->timestamps();
        });
        DB::table('company')->insert(['inventory_mode' => 'standard']);
    }

    public function test_both_searches_preserve_leading_zero_barcodes_and_trim_scanner_whitespace(): void
    {
        $product = $this->product('PRD_001', '001234567890');

        foreach ($this->searchAll(" \t001234567890\r\n") as $results) {
            $this->assertCount(1, $results);
            $this->assertSame($product->id, $results[0]['id']);
            $this->assertSame('001234567890', $results[0]['bar_code'] ?? $results[0]['barcode']);
            if (array_key_exists('current_stock', $results[0])) {
                $this->assertEquals(10, $results[0]['current_stock']);
            }
        }
    }

    public function test_exact_barcode_wins_over_partial_matches_even_beyond_the_pos_result_limit(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->product('OTHER_'.$i, 'X1234567890'.$i, 'Item 1234567890 '.$i);
        }
        $product = $this->product('TARGET', '1234567890');

        foreach ($this->searchAll('1234567890') as $results) {
            $this->assertCount(1, $results);
            $this->assertSame($product->id, $results[0]['id']);
        }
    }

    public function test_product_codes_partial_names_and_unknown_codes_still_work(): void
    {
        $product = $this->product('PRD_001', 'ABC-001', 'Test medicine');
        $this->product('PRD_0010', 'ABC-002', 'Test medicine large');

        foreach ($this->searchAll('PRD_001') as $results) {
            $this->assertCount(1, $results);
            $this->assertSame($product->id, $results[0]['id']);
        }
        foreach ($this->searchAll('medicine') as $results) {
            $this->assertCount(2, $results);
        }
        foreach ($this->searchAll('UNKNOWN-BARCODE') as $results) {
            $this->assertSame([], $results);
        }
    }

    public function test_inventory_barcodes_keep_the_selected_mrp_lot_or_batch_and_its_price(): void
    {
        $product = $this->product('PRD_001', 'ABC-001');
        $product->update(['is_batch_managed' => true]);
        $labels = new InventoryLabelService();

        foreach (['mrp', 'batch'] as $mode) {
            DB::table('company')->update(['inventory_mode' => $mode]);
            $attributes = [
                'product_id' => $product->product_code,
                'purchase_voucher' => 'PUR-001',
                'purchase_rate' => 12,
                'mrp' => 24,
                'sale_price' => 20,
                'quantity' => 8,
                'available_quantity' => 8,
            ];
            $source = $mode === 'mrp'
                ? MrpStockLot::create($attributes)
                : StockBatch::create($attributes + ['batch_no' => 'B-001']);
            $label = $mode === 'mrp' ? $labels->ensureForMrpLot($source) : $labels->ensureForBatch($source);
            $sourceKey = $mode === 'mrp' ? 'mrp_stock_lot_id' : 'stock_batch_id';

            foreach ($this->searchSaleAndPos($label->barcode) as $results) {
                $this->assertCount(1, $results);
                $this->assertSame($product->id, $results[0]['id']);
                $this->assertSame($source->id, $results[0][$sourceKey]);
                $this->assertEquals(20, $results[0]['price']);
                $this->assertEquals(8, $results[0]['current_stock']);
            }
        }
    }

    private function searchAll(string $query): array
    {
        $sale = $this->getJson(route('search', ['query' => $query, 'searchBy' => 'product']))->assertOk();
        $pos = $this->getJson(route('pos.products', ['q' => $query]))->assertOk();
        $purchase = $this->getJson(route('purchase.search', ['query' => $query, 'searchBy' => 'product']))->assertOk();
        $estimation = $this->getJson(route('estimations.search', ['query' => $query, 'searchBy' => 'product']))->assertOk();

        return [$sale->json(), $pos->json('products'), $purchase->json(), $estimation->json()];
    }

    private function searchSaleAndPos(string $query): array
    {
        $sale = $this->getJson(route('search', ['query' => $query, 'searchBy' => 'product']))->assertOk();
        $pos = $this->getJson(route('pos.products', ['q' => $query]))->assertOk();

        return [$sale->json(), $pos->json('products')];
    }

    private function product(string $code, string $barcode, string $name = 'Test product'): Product
    {
        $product = Product::create(['product_code' => $code, 'product' => $name, 'bar_code' => $barcode]);
        DB::table('stock')->insert(['stock_product_id' => $code, 'stock_qty' => 10]);

        return $product;
    }
}
