<?php

namespace Tests\Feature;

use Modules\Product\app\Models\Product;
use Modules\Settings\app\Models\PrintSetting;
use Tests\TestCase;

class ProductBarcodePrintViewTest extends TestCase
{
    public function test_legacy_print_views_only_render_selected_barcode_fields(): void
    {
        $product = new Product();
        $product->forceFill([
            'id' => 15,
            'product_code' => 'PRD_015',
            'product' => 'Name Must Stay Hidden',
            'pprice' => 90,
            'mrp' => 125,
            'price' => 110,
            'bar_code' => '1234567890',
        ]);

        $viewData = [
            'codeType' => 'barcode',
            'barcodeFieldLabels' => [
                'product_code' => 'Product Code',
                'mrp' => 'MRP',
                'sale_price' => 'Sale Price',
            ],
            'thermalSettings' => [
                'label_width_mm' => 60,
                'label_height_mm' => 40,
                'label_margin_mm' => 2,
                'auto_print' => false,
            ],
            'currencySymbol' => 'Rs.',
            'maskPurchasePrice' => false,
            'page_title' => 'Print Barcode',
        ];

        $singleHtml = view('product::products.barcode-print', $viewData + [
            'product' => $product,
        ])->render();
        $sheetHtml = view('product::products.barcode-sheet', $viewData + [
            'products' => collect([$product]),
        ])->render();

        foreach ([$singleHtml, $sheetHtml] as $html) {
            $this->assertStringContainsString('Product Code:', $html);
            $this->assertStringContainsString('PRD_015', $html);
            $this->assertStringContainsString('MRP:', $html);
            $this->assertStringContainsString('Sale Price:', $html);
            $this->assertStringNotContainsString('Name Must Stay Hidden', $html);
            $this->assertStringNotContainsString('Purchase Price:', $html);
        }
    }

    public function test_legacy_and_inventory_print_views_mask_only_purchase_price(): void
    {
        $product = new Product();
        $product->forceFill([
            'id' => 20,
            'product_code' => 'PRD_020',
            'product' => 'Masked Product',
            'pprice' => 1234.50,
            'mrp' => 1500,
            'price' => 1400,
            'bar_code' => '1234567890',
        ]);
        $thermalSettings = [
            'label_width_mm' => 60,
            'label_height_mm' => 40,
            'label_margin_mm' => 2,
            'auto_print' => false,
        ];

        $legacyHtml = view('product::products.barcode-print', [
            'product' => $product,
            'codeType' => 'barcode',
            'barcodeFieldLabels' => [
                'purchase_price' => 'Purchase Price',
                'mrp' => 'MRP',
                'sale_price' => 'Sale Price',
            ],
            'thermalSettings' => $thermalSettings,
            'currencySymbol' => 'Rs.',
            'maskPurchasePrice' => true,
            'page_title' => 'Print Barcode',
        ])->render();

        $label = new \Modules\Product\app\Models\InventoryLabel();
        $label->forceFill(['id' => 5, 'barcode' => '810000000010']);
        $inventoryHtml = view('product::products.inventory-label-print', [
            'rows' => collect([[
                'label' => $label,
                'purchase_price' => 1234.50,
                'mrp' => 1500,
                'sale_price' => 1400,
            ]]),
            'codeType' => 'barcode',
            'barcodeFieldLabels' => [
                'purchase_price' => 'Purchase Price',
                'mrp' => 'MRP',
                'sale_price' => 'Sale Price',
            ],
            'thermalSettings' => $thermalSettings,
            'currencySymbol' => 'Rs.',
            'maskPurchasePrice' => true,
            'backUrl' => '/products',
            'page_title' => 'Print Inventory Barcode',
        ])->render();

        foreach ([$legacyHtml, $inventoryHtml] as $html) {
            $this->assertStringContainsString('KVMO.UX', $html);
            $this->assertStringNotContainsString('1234.50', $html);
            $this->assertStringContainsString('1,500.00', $html);
            $this->assertStringContainsString('1,400.00', $html);
        }
    }

    public function test_barcode_view_queues_payload_through_hubix_without_browser_auto_print(): void
    {
        $product = new Product();
        $product->forceFill([
            'id' => 25,
            'product_code' => 'PRD_025',
            'product' => 'Hubix Label',
            'bar_code' => '1234567890',
        ]);
        $printSetting = new PrintSetting(array_merge(
            PrintSetting::defaultsFor('barcode'),
            ['print_method' => 'local_agent', 'auto_print' => true]
        ));

        $html = view('product::products.barcode-print', [
            'product' => $product,
            'codeType' => 'barcode',
            'barcodeFieldLabels' => ['product_code' => 'Product Code'],
            'thermalSettings' => [
                'label_width_mm' => 60,
                'label_height_mm' => 40,
                'label_margin_mm' => 2,
                'auto_print' => true,
            ],
            'currencySymbol' => 'Rs.',
            'maskPurchasePrice' => false,
            'page_title' => 'Print Barcode',
            'printSetting' => $printSetting,
            'printPayload' => [
                'source' => 'products',
                'ids' => [25],
                'code_type' => 'barcode',
                'layout' => 'single',
            ],
        ])->render();

        $this->assertStringContainsString('Print with Hubix', $html);
        $this->assertStringContainsString('documentType: "barcode"', $html);
        $this->assertStringContainsString('"ids":[25]', $html);
        $this->assertStringNotContainsString('onload="window.print();"', $html);
    }
}
