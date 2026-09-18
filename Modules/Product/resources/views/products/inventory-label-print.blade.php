<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $page_title ?? 'Print Inventory Labels' }}</title>
    @php $labelPage = \Modules\Settings\app\Models\BarcodeSetting::pageSize($thermalSettings); @endphp
    @include('product::products.partials.thermal-label-style')
</head>
<body @if(!($printAgentRender ?? false) && ($printSetting->print_method ?? 'browser') === 'browser' && ($printSetting->auto_print ?? $thermalSettings['auto_print'])) onload="window.print();" @endif>
    <div class="dontprint">
        <a class="btn" href="{{ $backUrl }}">Back</a>
        <button class="btn" onclick="window.print()">Print</button>
        @include('product::products.partials.thermal-label-guidance')
    </div>

    <div class="sheet">
        @forelse($rows->chunk($labelPage['columns']) as $labelRow)
            <div class="label-row">
            @foreach($labelRow as $row)
            @php $label = $row['label']; @endphp
            <div class="label" data-inventory="true">
                <div class="meta">
                    @foreach($barcodeFieldLabels as $field => $heading)
                        <div class="label-field {{ $field === 'product_name' ? 'product-name' : '' }}">
                            @include('product::products.partials.label-field-heading')
                            @if($field === 'purchase_price')
                                <span class="label-currency">{{ $currencySymbol }}</span> {{ $maskPurchasePrice
                                    ? \Modules\Settings\app\Models\BarcodeSetting::maskPurchasePrice($row[$field] ?? null)
                                    : number_format((float) ($row[$field] ?? 0), 2) }}
                            @elseif(in_array($field, ['mrp', 'sale_price'], true))
                                <span class="label-currency">{{ $currencySymbol }}</span> {{ number_format((float) $row[$field], 2) }}
                            @elseif($field === 'expiry_date')
                                {{ $row[$field]?->format('d/m/Y') ?? '-' }}
                            @else
                                {{ $row[$field] ?? '-' }}
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="codes">
                    @if(in_array($codeType, ['barcode', 'both'], true))
                        <div class="barcode">
                            <img src="{{ $row['barcode_image_src'] ?? route('inventory-labels.barcode', $label) }}" alt="Barcode {{ $label->barcode }}">
                            <div class="number">{{ $label->barcode }}</div>
                        </div>
                    @endif
                    @if(in_array($codeType, ['qr', 'both'], true))
                        <div class="qr">
                            <img src="{{ $row['qr_image_src'] ?? route('inventory-labels.qr', $label) }}" alt="QR code {{ $label->barcode }}">
                            <div class="number">{{ $label->barcode }}</div>
                        </div>
                    @endif
                </div>
            </div>
            @endforeach
            </div>
        @empty
            <p>No inventory labels are available for this product in the active inventory mode.</p>
        @endforelse
    </div>

    @if($rows->isNotEmpty())
        @include('partials.local-print-script', ['printSetting' => $printSetting ?? null, 'documentType' => 'barcode', 'documentId' => null, 'printPayload' => $printPayload ?? null])
    @endif
</body>
</html>
