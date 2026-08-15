<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Product\app\Services\InlineProductService;
use Tests\TestCase;

class InlineProductServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('product', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('product');
            $table->string('hsn_code')->nullable();
            $table->decimal('gst', 10, 2)->nullable();
            $table->decimal('pprice', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('mrp', 10, 2)->nullable();
            $table->decimal('margin', 10, 2)->nullable();
            $table->decimal('amt_margin', 10, 2)->nullable();
            $table->string('unit')->nullable();
            $table->decimal('uqty', 10, 2)->nullable();
            $table->unsignedBigInteger('bar_code')->unique();
            $table->unsignedBigInteger('typeid')->default(2);
        });
    }

    public function test_create_uses_numeric_barcode_and_returns_transaction_fields(): void
    {
        $payload = (new InlineProductService())->create([
            'product' => 'Inline Product',
            'product_code' => 'PRD_013',
            'hsn_code' => '1234',
            'gst' => 5,
            'pprice' => 150,
            'price' => 190,
            'mrp' => 200,
            'margin' => 5,
        ]);

        $this->assertSame('PRD_013', $payload['product_code']);
        $this->assertEquals(150, $payload['purchase_price']);
        $this->assertEquals(190, $payload['sale_price']);
        $this->assertEquals(5, $payload['margin']);
        $this->assertEquals(10, $payload['amt_margin']);
        $this->assertMatchesRegularExpression('/^\d{10}$/', $payload['bar_code']);
        $this->assertSame(0, $payload['current_stock']);
        $this->assertFalse($payload['is_batch_managed']);
        $this->assertSame([], $payload['batches']);
        $this->assertSame([], $payload['mrp_lots']);
        $this->assertDatabaseHas('product', [
            'product_code' => 'PRD_013',
            'bar_code' => $payload['bar_code'],
            'typeid' => 2,
        ]);
    }
}
