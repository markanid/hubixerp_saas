<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Modules\Product\app\Models\MrpStockLot;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\Stock;
use Modules\Product\app\Models\StockBatch;
use Modules\Product\app\Models\StockLedger;
use Modules\Product\app\Services\ProductStockService;
use Tests\TestCase;

class ProductStockServiceTest extends TestCase
{
    private ProductStockService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProductStockService();
    }

    public function test_standard_stock_uses_legacy_stock_and_unit_conversion(): void
    {
        $product = $this->product(5);
        $product->setRelation('stock', new Stock(['stock_qty' => 25]));

        $this->assertSame(25.0, $this->service->rawQuantity($product, 'standard'));
        $this->assertSame(5.0, $this->service->displayQuantity($product, 'standard'));
        $this->assertFalse($this->service->usesTrackedInventory($product, 'standard'));
    }

    public function test_mrp_stock_uses_available_lots_and_is_not_directly_editable(): void
    {
        $product = $this->product(2);
        $product->setRelation('stock', new Stock(['stock_qty' => 999]));
        $product->setRelation('mrpStockLots', new Collection([
            new MrpStockLot(['available_quantity' => 8]),
            new MrpStockLot(['available_quantity' => 4]),
        ]));

        $this->assertSame(12.0, $this->service->rawQuantity($product, 'mrp'));
        $this->assertSame(6.0, $this->service->displayQuantity($product, 'mrp'));
        $this->assertTrue($this->service->usesTrackedInventory($product, 'mrp'));
    }

    public function test_batch_stock_uses_available_batches_only_for_batch_managed_products(): void
    {
        $product = $this->product(4, true);
        $product->setRelation('stock', new Stock(['stock_qty' => 100]));
        $product->setRelation('stockBatches', new Collection([
            new StockBatch(['available_quantity' => 20]),
            new StockBatch(['available_quantity' => 8]),
        ]));

        $this->assertSame(28.0, $this->service->rawQuantity($product, 'batch'));
        $this->assertSame(7.0, $this->service->displayQuantity($product, 'batch'));
        $this->assertTrue($this->service->usesTrackedInventory($product, 'batch'));

        $product->is_batch_managed = false;

        $this->assertSame(100.0, $this->service->rawQuantity($product, 'batch'));
        $this->assertFalse($this->service->usesTrackedInventory($product, 'batch'));
    }

    public function test_zero_unit_quantity_does_not_cause_division_by_zero(): void
    {
        $product = $this->product(0);
        $product->setRelation('stock', new Stock(['stock_qty' => 10]));

        $this->assertSame(10.0, $this->service->displayQuantity($product, 'standard'));
    }

    public function test_stock_ledger_relationships_use_the_inventory_item_key(): void
    {
        $product = $this->product(1);
        $product->product_code = 'PRD_001';
        $ledger = new StockLedger(['stock_item_id' => 'PRD_001']);

        $this->assertSame('stock_item_id', $product->stockLedgers()->getForeignKeyName());
        $this->assertSame('stock_item_id', $ledger->product()->getForeignKeyName());
        $this->assertSame('product_code', $ledger->product()->getOwnerKeyName());
    }

    public function test_direct_adjustments_require_an_explicit_standard_stock_submission(): void
    {
        $product = $this->product(1);
        $product->typeid = 2;

        $this->assertTrue($this->service->canApplyDirectAdjustment($product, 'standard', false, false, true));
        $this->assertFalse($this->service->canApplyDirectAdjustment($product, 'standard', false, false, false));
        $this->assertFalse($this->service->canApplyDirectAdjustment($product, 'standard', false, true, true));
        $this->assertFalse($this->service->canApplyDirectAdjustment($product, 'mrp', false, false, true));
        $this->assertFalse($this->service->canApplyDirectAdjustment($product, 'batch', true, false, true));

        $product->typeid = 3;

        $this->assertFalse($this->service->canApplyDirectAdjustment($product, 'standard', false, false, true));
    }

    public function test_list_aggregate_is_used_without_loading_tracked_inventory_rows(): void
    {
        $product = $this->product(2, true);
        $product->setAttribute('tracked_stock_qty', 18);

        $this->assertSame(9.0, $this->service->displayQuantity($product, 'mrp'));
        $this->assertSame(9.0, $this->service->displayQuantity($product, 'batch'));
    }

    private function product(float $unitQuantity, bool $batchManaged = false): Product
    {
        $product = new Product();
        $product->forceFill([
            'uqty' => $unitQuantity,
            'is_batch_managed' => $batchManaged,
        ]);

        return $product;
    }
}
