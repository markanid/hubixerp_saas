<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $page_title ?? 'Print Inventory Labels' }}</title>
    @php
        $labelWidth = (int) $thermalSettings['label_width_mm'];
        $labelHeight = (int) $thermalSettings['label_height_mm'];
        $labelMargin = (int) $thermalSettings['label_margin_mm'];
    @endphp
    <style>
        @page { size: {{ $labelWidth }}mm {{ $labelHeight }}mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: Arial, Helvetica, sans-serif; }
        .dontprint { margin-bottom: 10px; }
        .btn { display: inline-block; padding: 6px 10px; border: 1px solid #333; color: #111; background: #fff; text-decoration: none; font-size: 12px; }
        .sheet { display: block; }
        .label { width: {{ $labelWidth }}mm; height: {{ $labelHeight }}mm; padding: {{ $labelMargin }}mm; display: flex; align-items: center; gap: 2mm; page-break-inside: avoid; page-break-after: always; overflow: hidden; }
        .meta { flex: 1; min-width: 0; font-size: 8px; line-height: 1.25; }
        .codes { flex: 1.35; min-width: 0; display: flex; align-items: center; justify-content: center; gap: 2mm; }
        .barcode { flex: 1; min-width: 0; text-align: center; }
        .barcode img { width: 50mm; max-width: 100%; height: 13mm; object-fit: fill; }
        .qr img { width: 24mm; height: 24mm; }
        .number { margin-top: .5mm; font-size: 8px; letter-spacing: .5px; }
        @media print {
            .dontprint { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body @if($thermalSettings['auto_print']) onload="window.print();" @endif>
    <div class="dontprint">
        <a class="btn" href="{{ $backUrl }}">Back</a>
        <button class="btn" onclick="window.print()">Print</button>
    </div>

    <div class="sheet">
        @forelse($rows as $row)
            @php $label = $row['label']; @endphp
            <div class="label">
                <div class="meta">
                    @foreach($barcodeFieldLabels as $field => $heading)
                        <div>
                            <strong>{{ $heading }}:</strong>
                            @if($field === 'purchase_price')
                                {{ $currencySymbol }} {{ $maskPurchasePrice
                                    ? \Modules\Settings\app\Models\BarcodeSetting::maskPurchasePrice($row[$field] ?? null)
                                    : number_format((float) ($row[$field] ?? 0), 2) }}
                            @elseif(in_array($field, ['mrp', 'sale_price'], true))
                                {{ $currencySymbol }} {{ number_format((float) $row[$field], 2) }}
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
                            <img src="{{ route('inventory-labels.barcode', $label) }}" alt="Barcode {{ $label->barcode }}">
                            <div class="number">{{ $label->barcode }}</div>
                        </div>
                    @endif
                    @if(in_array($codeType, ['qr', 'both'], true))
                        <div class="qr">
                            <img src="{{ route('inventory-labels.qr', $label) }}" alt="QR code {{ $label->barcode }}">
                            <div class="number">{{ $label->barcode }}</div>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <p>No inventory labels are available for this product in the active inventory mode.</p>
        @endforelse
    </div>
</body>
</html>