<?php

namespace Modules\Sale\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Product\app\Models\Product;

class SaleCalculator
{
    public function calculate(array $submittedItems, string $saleType, bool $collectTax = true): array
    {
        $products = Product::whereIn('product_code', collect($submittedItems)->pluck('product_id')->unique())
            ->get()
            ->keyBy('product_code');

        return $this->calculateWithProducts($submittedItems, $saleType, $products, $collectTax);
    }

    public function calculateWithProducts(array $submittedItems, string $saleType, Collection $products, bool $collectTax = true): array
    {
        $items = [];
        $amount = 0.0;
        $discount = 0.0;
        $gstTotal = 0.0;
        $taxableTotal = 0.0;
        $unroundedTotal = 0.0;

        foreach ($submittedItems as $index => $submitted) {
            $productCode = (string) $submitted['product_id'];
            $product = $products->get($productCode);

            if (!$product) {
                throw ValidationException::withMessages([
                    "sale_items.$index.product_id" => 'The selected product is no longer available.',
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

            if ($lineDiscount > $lineAmount) {
                throw ValidationException::withMessages([
                    "sale_items.$index.discount_amount" => "Discount cannot exceed the value of {$product->product}.",
                ]);
            }

            $lineTotal = round($lineAmount - $lineDiscount, 2);
            $gstRate = (!$collectTax || $saleType === '0') ? 0.0 : (float) ($product->gst ?? 0);
            $taxable = $gstRate > 0 ? round($lineTotal / (1 + ($gstRate / 100)), 2) : $lineTotal;
            $gstValue = round($lineTotal - $taxable, 2);
            $rawQuantity = round($isLooseUnit ? $quantity : $quantity * $unitQuantity, 2);

            $items[] = [
                'product_id' => $productCode,
                'hsn_code' => $product->hsn_code,
                'manufacturing_date' => $submitted['manufacturing_date'] ?? null,
                'stock_batch_id' => !empty($submitted['stock_batch_id']) ? (int) $submitted['stock_batch_id'] : null,
                'mrp_stock_lot_id' => !empty($submitted['mrp_stock_lot_id']) ? (int) $submitted['mrp_stock_lot_id'] : null,
                'quantity' => $quantity,
                'raw_quantity' => $rawQuantity,
                'unit' => $isLooseUnit ? 'No.s' : $baseUnit,
                'unit_price' => $unitPrice,
                'unit_qty' => $unitQuantity,
                'total_before_discount' => $lineAmount,
                'discount_amount' => $lineDiscount,
                'discount_percentage' => $lineAmount > 0
                    ? round(($lineDiscount / $lineAmount) * 100, 2)
                    : 0,
                'gst_rate' => $gstRate,
                'gst_value' => $gstValue,
                'taxable_value' => $taxable,
                'total_after_discount' => $lineTotal,
            ];

            $amount += $lineAmount;
            $discount += $lineDiscount;
            $gstTotal += $gstValue;
            $taxableTotal += $taxable;
            $unroundedTotal += $lineTotal;
        }

        $amountPayable = round($unroundedTotal);

        return [
            'items' => $items,
            'amount' => round($amount, 2),
            'gst' => round($gstTotal, 2),
            'discount' => round($discount, 2),
            'grand_total' => round($taxableTotal, 2),
            'amount_payable' => round($amountPayable, 2),
            'round' => round($amountPayable - $unroundedTotal, 2),
        ];
    }
}