<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Product\app\Models\Product;
use Modules\Sale\app\Services\SaleCalculator;
use Tests\TestCase;

class SaleCalculatorTest extends TestCase
{
    public function test_it_uses_server_product_values_and_calculates_tax_inclusive_totals(): void
    {
        $product = new Product([
            'product_code' => 'PRD_001',
            'product' => 'Server Product',
            'hsn_code' => '1001',
            'price' => 118,
            'unit' => 'Box',
            'uqty' => 10,
            'gst' => 18,
        ]);

        $result = (new SaleCalculator())->calculateWithProducts([
            [
                'product_id' => 'PRD_001',
                'quantity' => 2,
                'unit' => 'Box',
            'unit_price' => 120,
                'unit_qty' => 999,
                'gst_value' => 0,
                'discount_amount' => 0,
            ],
        ], '1', new Collection(['PRD_001' => $product]));

        $this->assertSame(120.0, $result['items'][0]['unit_price']);
        $this->assertSame(10.0, $result['items'][0]['unit_qty']);
        $this->assertSame(20.0, $result['items'][0]['raw_quantity']);
        $this->assertSame(240.0, $result['amount']);
        $this->assertSame(36.61, $result['gst']);
        $this->assertSame(203.39, $result['grand_total']);
        $this->assertSame(240.0, $result['amount_payable']);
    }

    public function test_it_converts_loose_units_and_disables_gst_for_no_gst_sales(): void
    {
        $product = new Product([
            'product_code' => 'PRD_002',
            'product' => 'Loose Product',
            'price' => 100,
            'unit' => 'Pack',
            'uqty' => 10,
            'gst' => 18,
        ]);

        $result = (new SaleCalculator())->calculateWithProducts([
            [
                'product_id' => 'PRD_002',
                'quantity' => 3,
                'unit' => 'No.s',
                'unit_price' => 12,
                'discount_amount' => 5,
            ],
        ], '0', new Collection(['PRD_002' => $product]));

        $this->assertSame(12.0, $result['items'][0]['unit_price']);
        $this->assertSame(3.0, $result['items'][0]['raw_quantity']);
        $this->assertSame(0.0, $result['gst']);
        $this->assertSame(31.0, $result['grand_total']);
        $this->assertSame(31.0, $result['amount_payable']);
    }

    public function test_it_rejects_a_discount_larger_than_the_line_value(): void
    {
        $product = new Product([
            'product_code' => 'PRD_003',
            'product' => 'Discount Product',
            'price' => 50,
            'unit' => 'No.s',
            'uqty' => 1,
            'gst' => 0,
        ]);

        $this->expectException(ValidationException::class);

        (new SaleCalculator())->calculateWithProducts([
            [
                'product_id' => 'PRD_003',
                'quantity' => 1,
                'unit' => 'No.s',
                'unit_price' => 50,
                'discount_amount' => 51,
            ],
        ], '1', new Collection(['PRD_003' => $product]));
    }

    public function test_it_preserves_an_optional_manual_batch_selection(): void
    {
        $product = new Product([
            'product_code' => 'PRD_004', 'product' => 'Batch Product',
            'price' => 10, 'unit' => 'No.s', 'uqty' => 1, 'gst' => 0,
        ]);

        $result = (new SaleCalculator())->calculateWithProducts([[
            'product_id' => 'PRD_004', 'quantity' => 1, 'unit' => 'No.s',
            'unit_price' => 10, 'stock_batch_id' => 42,
        ]], '1', new Collection(['PRD_004' => $product]));

        $this->assertSame(42, $result['items'][0]['stock_batch_id']);
    }
}
