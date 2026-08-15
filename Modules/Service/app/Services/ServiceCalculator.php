<?php

namespace Modules\Service\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Product\app\Models\Product;

class ServiceCalculator
{
    public function calculate(array $saleItems, array $serviceItems, string $serviceType = '1', bool $collectTax = true): array
    {
        $productCodes = collect($saleItems)
            ->pluck('product_id')
            ->merge(collect($serviceItems)->pluck('product_id'))
            ->filter()
            ->unique();

        $products = Product::whereIn('product_code', $productCodes)->get()->keyBy('product_code');

        $calculatedSaleItems = $this->calculateSaleItems($saleItems, $products, $serviceType, $collectTax);
        $calculatedServiceItems = $this->calculateServiceItems($serviceItems, $products, $serviceType, $collectTax);
        $items = array_merge($calculatedSaleItems, $calculatedServiceItems);

        $amount = collect($items)->sum('total_before_discount');
        $discount = collect($items)->sum('discount_amount');
        $gst = collect($items)->sum('gst_value');
        $taxable = collect($items)->sum('taxable_value');
        $unrounded = collect($items)->sum('total_after_discount');
        $amountPayable = round($unrounded);

        return [
            'sale_items' => $calculatedSaleItems,
            'service_items' => $calculatedServiceItems,
            'amount' => round($amount, 2),
            'gst' => round($gst, 2),
            'discount' => round($discount, 2),
            'grand_total' => round($taxable, 2),
            'amount_payable' => round($amountPayable, 2),
            'round' => round($amountPayable - $unrounded, 2),
        ];
    }

    private function calculateSaleItems(array $submittedItems, Collection $products, string $serviceType, bool $collectTax): array
    {
        $items = [];

        foreach ($submittedItems as $index => $submitted) {
            $productCode = (string) $submitted['product_id'];
            $product = $products->get($productCode);

            if (!$product) {
                throw ValidationException::withMessages([
                    "sale_items.$index.product_id" => 'The selected product is no longer available.',
                ]);
            }

            if ((int) $product->typeid === 3) {
                throw ValidationException::withMessages([
                    "sale_items.$index.product_id" => "{$product->product} is a service item. Add it in the service section.",
                ]);
            }

            $quantity = round((float) $submitted['quantity'], 2);
            $unitQuantity = max((float) ($product->uqty ?: 1), 1);
            $unit = (string) $submitted['unit'];
            $baseUnit = (string) ($product->unit ?: 'No.s');
            $isLooseUnit = in_array($unit, ['No.s', 'Nos.'], true);

            if ($unit !== $baseUnit && !($isLooseUnit && $unitQuantity > 1)) {
                throw ValidationException::withMessages([
                    "sale_items.$index.unit" => "Invalid unit selected for {$product->product}.",
                ]);
            }

            $defaultUnitPrice = $isLooseUnit && $unitQuantity > 1
                ? (float) $product->price / $unitQuantity
                : (float) $product->price;
            $unitPrice = round((float) ($submitted['unit_price'] ?? $defaultUnitPrice), 2);
            $lineAmount = round($quantity * $unitPrice, 2);
            $lineDiscount = round((float) ($submitted['discount_amount'] ?? 0), 2);
            $this->assertDiscount($lineDiscount, $lineAmount, $product->product, "sale_items.$index.discount_amount");

            $items[] = $this->lineData($submitted, $product, $lineAmount, $lineDiscount, $serviceType, $collectTax, [
                'type' => 'sale',
                'quantity' => $quantity,
                'raw_quantity' => round($isLooseUnit ? $quantity : $quantity * $unitQuantity, 2),
                'unit' => $isLooseUnit ? 'No.s' : $baseUnit,
                'unit_price' => $unitPrice,
                'unit_qty' => $unitQuantity,
                'manufacturing_date' => $submitted['manufacturing_date'] ?? null,
                'stock_batch_id' => !empty($submitted['stock_batch_id']) ? (int) $submitted['stock_batch_id'] : null,
                'mrp_stock_lot_id' => !empty($submitted['mrp_stock_lot_id']) ? (int) $submitted['mrp_stock_lot_id'] : null,
            ]);
        }

        return $items;
    }

    private function calculateServiceItems(array $submittedItems, Collection $products, string $serviceType, bool $collectTax): array
    {
        $items = [];

        foreach ($submittedItems as $index => $submitted) {
            $productCode = (string) $submitted['product_id'];
            $product = $products->get($productCode);

            if (!$product) {
                throw ValidationException::withMessages([
                    "service_items.$index.product_id" => 'The selected service item is no longer available.',
                ]);
            }

            if ((int) $product->typeid !== 3) {
                throw ValidationException::withMessages([
                    "service_items.$index.product_id" => "{$product->product} is a product item. Add it in the sale section.",
                ]);
            }

            $lineAmount = round((float) $submitted['unit_price'], 2);
            $lineDiscount = round((float) ($submitted['discount_amount'] ?? 0), 2);
            $this->assertDiscount($lineDiscount, $lineAmount, $product->product, "service_items.$index.discount_amount");

            $items[] = $this->lineData($submitted, $product, $lineAmount, $lineDiscount, $serviceType, $collectTax, [
                'type' => 'service',
                'quantity' => 1,
                'raw_quantity' => 0,
                'unit' => 'No.s',
                'unit_price' => $lineAmount,
                'unit_qty' => 1,
                'stock_batch_id' => null,
                'mrp_stock_lot_id' => null,
                'remarks' => $submitted['remarks'] ?? null,
            ]);
        }

        return $items;
    }

    private function lineData(array $submitted, Product $product, float $lineAmount, float $lineDiscount, string $serviceType, bool $collectTax, array $extra): array
    {
        $lineTotal = round($lineAmount - $lineDiscount, 2);
        $gstRate = (!$collectTax || $serviceType === '0') ? 0.0 : (float) ($product->gst ?? 0);
        $taxable = $gstRate > 0 ? round($lineTotal / (1 + ($gstRate / 100)), 2) : $lineTotal;
        $gstValue = round($lineTotal - $taxable, 2);

        return array_merge([
            'product_id' => (string) $product->product_code,
            'hsn_code' => $product->hsn_code,
            'discount_amount' => $lineDiscount,
            'discount_percentage' => $lineAmount > 0 ? round(($lineDiscount / $lineAmount) * 100, 2) : 0,
            'gst_rate' => $gstRate,
            'gst_value' => $gstValue,
            'taxable_value' => $taxable,
            'total_before_discount' => $lineAmount,
            'total_after_discount' => $lineTotal,
        ], $extra);
    }

    private function assertDiscount(float $discount, float $amount, string $name, string $field): void
    {
        if ($discount > $amount) {
            throw ValidationException::withMessages([
                $field => "Discount cannot exceed the value of {$name}.",
            ]);
        }
    }
}