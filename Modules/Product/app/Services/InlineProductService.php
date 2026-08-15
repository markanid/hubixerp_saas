<?php

namespace Modules\Product\app\Services;

use Modules\Product\app\Models\Product;

class InlineProductService
{
    public function create(array $attributes): array
    {
        $mrp = isset($attributes['mrp']) && $attributes['mrp'] !== '' ? (float) $attributes['mrp'] : null;
        $price = (float) $attributes['price'];
        $marginAmount = $mrp !== null && $mrp > 0 && $price <= $mrp
            ? round($mrp - $price, 2)
            : null;
        $marginPercentage = $marginAmount !== null
            ? round(($marginAmount * 100) / $mrp, 2)
            : ($attributes['margin'] ?? null);

        $product = Product::create([
            'product' => $attributes['product'],
            'product_code' => $attributes['product_code'],
            'hsn_code' => $attributes['hsn_code'] ?? null,
            'gst' => $attributes['gst'] ?? null,
            'pprice' => $attributes['pprice'] ?? null,
            'mrp' => $mrp,
            'margin' => $marginPercentage,
            'amt_margin' => $marginAmount,
            'price' => $price,
            'unit' => 'No.s',
            'uqty' => 1,
            'bar_code' => $this->generateBarcode(),
            'typeid' => $attributes['typeid'] ?? 2,
        ]);

        return $this->payload($product);
    }

    public function payload(Product $product): array
    {
        return [
            'id' => $product->id,
            'product' => $product->product,
            'product_code' => $product->product_code,
            'hsn_code' => $product->hsn_code,
            'gst' => $product->gst,
            'pprice' => $product->pprice,
            'purchase_price' => $product->pprice,
            'price' => $product->price,
            'sale_price' => $product->price,
            'mrp' => $product->mrp,
            'margin' => $product->margin,
            'amt_margin' => $product->amt_margin,
            'unit' => $product->unit,
            'uqty' => $product->uqty,
            'unit_qty' => $product->uqty,
            'bar_code' => (string) $product->bar_code,
            'current_stock' => 0,
            'is_batch_managed' => false,
            'batches' => [],
            'mrp_lots' => [],
        ];
    }

    private function generateBarcode(): string
    {
        do {
            $barcode = (string) random_int(1000000000, 9999999999);
        } while (Product::where('bar_code', $barcode)->exists());

        return $barcode;
    }
}
