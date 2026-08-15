@php
    $legacyFieldValues = [
        'product_code' => $product->product_code,
        'product_name' => $product->product,
        'purchase_price' => $product->pprice,
        'mrp' => $product->mrp,
        'sale_price' => $product->price,
        'batch_number' => null,
        'expiry_date' => null,
    ];
@endphp

@foreach($barcodeFieldLabels as $field => $heading)
    <div class="label-field {{ $field === 'product_name' ? 'product-name' : '' }}">
        <strong>{{ $heading }}:</strong>
        @if($field === 'purchase_price')
            {{ $currencySymbol }} {{ $maskPurchasePrice
                ? \Modules\Settings\app\Models\BarcodeSetting::maskPurchasePrice($legacyFieldValues[$field] ?? null)
                : number_format((float) ($legacyFieldValues[$field] ?? 0), 2) }}
        @elseif(in_array($field, ['mrp', 'sale_price'], true))
            {{ $currencySymbol }} {{ number_format((float) ($legacyFieldValues[$field] ?? 0), 2) }}
        @elseif($field === 'expiry_date')
            {{ $legacyFieldValues[$field] instanceof \Carbon\CarbonInterface ? $legacyFieldValues[$field]->format('d/m/Y') : '-' }}
        @else
            {{ $legacyFieldValues[$field] ?? '-' }}
        @endif
    </div>
@endforeach