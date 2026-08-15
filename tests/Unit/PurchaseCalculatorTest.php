<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Product\app\Models\Product;
use Modules\Purchase\app\Services\PurchaseCalculator;
use Tests\TestCase;

class PurchaseCalculatorTest extends TestCase
{
    public function test_it_canonicalizes_mrp_lot_margin_from_sale_price(): void
    {
        $product = new Product([
            'product_code' => 'PRD_001',
            'product' => 'MRP Product',
            'hsn_code' => '1001',
            'pprice' => 80,
            'price' => 105,
            'unit' => 'STRIP',
            'uqty' => 10,
            'gst' => 0,
        ]);

        $result = (new PurchaseCalculator())->calculateWithProducts([[
            'product_id' => 'PRD_001',
            'quantity' => 2,
            'unit' => 'STRIP',
            'unit_price' => 90,
            'mrp' => 120,
            'sale_price' => 105,
            'margin_percentage' => 99,
            'margin_amount' => 99,
        ]], new Collection(['PRD_001' => $product]));

        $item = $result['items'][0];
        $this->assertSame(105.0, $item['sale_price']);
        $this->assertSame(15.0, $item['margin_amount']);
        $this->assertSame(12.5, $item['margin_percentage']);
    }

    public function test_it_rejects_a_lot_sale_price_above_mrp(): void
    {
        $product = new Product([
            'product_code' => 'PRD_002',
            'product' => 'Invalid MRP Product',
            'pprice' => 80,
            'price' => 130,
            'unit' => 'STRIP',
            'uqty' => 10,
            'gst' => 0,
        ]);

        $this->expectException(ValidationException::class);

        (new PurchaseCalculator())->calculateWithProducts([[
            'product_id' => 'PRD_002',
            'quantity' => 1,
            'unit' => 'STRIP',
            'unit_price' => 90,
            'mrp' => 120,
            'sale_price' => 130,
        ]], new Collection(['PRD_002' => $product]));
    }

    public function test_it_uses_margin_amount_as_the_purchase_lot_pricing_basis(): void
    {
        $product = new Product([
            'product_code' => 'PRD_003',
            'product' => 'Amount Margin Product',
            'unit' => 'STRIP',
            'uqty' => 10,
            'gst' => 0,
        ]);

        $result = (new PurchaseCalculator())->calculateWithProducts([[
            'product_id' => 'PRD_003',
            'quantity' => 1,
            'unit' => 'STRIP',
            'unit_price' => 90,
            'mrp' => 120,
            'sale_price' => 1,
            'margin_percentage' => 99,
            'margin_amount' => 10,
            'margin_basis' => 'amount',
        ]], new Collection(['PRD_003' => $product]));

        $item = $result['items'][0];
        $this->assertSame(110.0, $item['sale_price']);
        $this->assertSame(10.0, $item['margin_amount']);
        $this->assertSame(8.33, $item['margin_percentage']);
    }

    public function test_it_uses_margin_percentage_as_the_purchase_lot_pricing_basis(): void
    {
        $product = new Product([
            'product_code' => 'PRD_004',
            'product' => 'Percentage Margin Product',
            'unit' => 'STRIP',
            'uqty' => 10,
            'gst' => 0,
        ]);

        $result = (new PurchaseCalculator())->calculateWithProducts([[
            'product_id' => 'PRD_004',
            'quantity' => 1,
            'unit' => 'STRIP',
            'unit_price' => 90,
            'mrp' => 120,
            'sale_price' => 1,
            'margin_percentage' => 10,
            'margin_amount' => 99,
            'margin_basis' => 'percentage',
        ]], new Collection(['PRD_004' => $product]));

        $item = $result['items'][0];
        $this->assertSame(108.0, $item['sale_price']);
        $this->assertSame(12.0, $item['margin_amount']);
        $this->assertSame(10.0, $item['margin_percentage']);
    }
}
