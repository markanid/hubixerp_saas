<?php

namespace Modules\Purchase\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Product\app\Models\Product;

class PurchaseCalculator
{
    public function calculate(array $submittedItems, string $purchaseType = '1', bool $collectTax = true): array
    {
        $products = Product::whereIn('product_code', collect($submittedItems)->pluck('product_id')->unique())
            ->get()
            ->keyBy('product_code');

        return $this->calculateWithProducts($submittedItems, $products, $purchaseType, $collectTax);
    }

    public function calculateWithProducts(array $submittedItems, Collection $products, string $purchaseType = '1', bool $collectTax = true): array
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
                    "purchase_items.$index.product_id" => 'The selected product is no longer available.',
                ]);
            }

            $quantity = round((float) $submitted['quantity'], 2);
            $freeQuantity = round((float) ($submitted['free_quantity'] ?? 0), 2);
            $unitQuantity = max((float) ($product->uqty ?: 1), 1);
            $unit = (string) $submitted['unit'];
            $baseUnit = (string) ($product->unit ?: 'No.s');
            $isLooseUnit = in_array($unit, ['No.s', 'Nos.'], true);

            if ($unit !== $baseUnit && !($isLooseUnit && $unitQuantity > 1)) {
                throw ValidationException::withMessages([
                    "purchase_items.$index.unit" => "Invalid unit selected for {$product->product}.",
                ]);
            }

            $defaultUnitPrice = $isLooseUnit && $unitQuantity > 1
                ? (float) $product->pprice / $unitQuantity
                : (float) $product->pprice;
            $unitPrice = round((float) ($submitted['unit_price'] ?? $defaultUnitPrice), 2);
            $lineAmount = round($quantity * $unitPrice, 2);
            $lineDiscount = round((float) ($submitted['discount_amount'] ?? 0), 2);

            if ($lineDiscount > $lineAmount) {
                throw ValidationException::withMessages([
                    "purchase_items.$index.discount_amount" => "Discount cannot exceed the value of {$product->product}.",
                ]);
            }

            $lineTotal = round($lineAmount - $lineDiscount, 2);
            $gstRate = (!$collectTax || $purchaseType === '2') ? 0.0 : (float) ($product->gst ?? 0);
            $taxable = $gstRate > 0 ? round($lineTotal / (1 + ($gstRate / 100)), 2) : $lineTotal;
            $gstValue = round($lineTotal - $taxable, 2);
            $rawQuantity = round($isLooseUnit ? $quantity : $quantity * $unitQuantity, 2);
            $rawFreeQuantity = round($isLooseUnit ? $freeQuantity : $freeQuantity * $unitQuantity, 2);
            $mrp = isset($submitted['mrp']) ? round((float) $submitted['mrp'], 2) : null;
            $lotSalePrice = isset($submitted['sale_price'])
                ? round((float) $submitted['sale_price'], 2)
                : round((float) ($product->price ?? 0), 2);
            $lotMarginBasis = in_array($submitted['margin_basis'] ?? null, ['amount', 'percentage'], true)
                ? $submitted['margin_basis']
                : null;

            if ($mrp !== null && $lotMarginBasis === 'amount') {
                $lotMarginAmount = round(max(0, (float) ($submitted['margin_amount'] ?? 0)), 2);
                if ($lotMarginAmount > $mrp) {
                    throw ValidationException::withMessages([
                        "purchase_items.$index.margin_amount" => "Margin amount cannot exceed MRP for {$product->product}.",
                    ]);
                }

                $lotSalePrice = round($mrp - $lotMarginAmount, 2);
                $lotMarginPercentage = $mrp > 0
                    ? round(($lotMarginAmount / $mrp) * 100, 2)
                    : 0;
            } elseif ($mrp !== null && $lotMarginBasis === 'percentage') {
                $lotMarginPercentage = round(max(0, min(100, (float) ($submitted['margin_percentage'] ?? 0))), 2);
                $lotMarginAmount = round(($mrp * $lotMarginPercentage) / 100, 2);
                $lotSalePrice = round($mrp - $lotMarginAmount, 2);
            } else {
                $lotMarginAmount = $mrp !== null ? round(max(0, $mrp - $lotSalePrice), 2) : 0;
                $lotMarginPercentage = $mrp > 0
                    ? round(($lotMarginAmount / $mrp) * 100, 2)
                    : 0;
            }

            if ($mrp !== null && $mrp > 0 && $lotSalePrice > $mrp) {
                throw ValidationException::withMessages([
                    "purchase_items.$index.sale_price" => "Sale price cannot exceed MRP for {$product->product}.",
                ]);
            }

            $items[] = [
                'product_id' => $productCode,
                'hsn_code' => $product->hsn_code,
                'quantity' => $quantity,
                'free_quantity' => $freeQuantity,
                'raw_quantity' => $rawQuantity,
                'raw_free_quantity' => $rawFreeQuantity,
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
                'batch_no' => $submitted['batch_no'] ?? null,
                'expiry_date' => $submitted['expiry_date'] ?? null,
                'mrp' => $mrp,
                'sale_price' => $lotSalePrice,
                'margin_percentage' => $lotMarginPercentage,
                'margin_amount' => $lotMarginAmount,
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