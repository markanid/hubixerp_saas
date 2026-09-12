<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $page_title ?? 'Print Barcodes' }}</title>
    @php
        $labelWidth = (int) $thermalSettings['label_width_mm'];
        $labelHeight = (int) $thermalSettings['label_height_mm'];
        $labelMargin = (int) $thermalSettings['label_margin_mm'];
    @endphp
    <style>
        @page {
            size: {{ $labelWidth }}mm {{ $labelHeight }}mm;
            margin: 0;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
        }
        .dontprint {
            margin-bottom: 10px;
        }
        .btn {
            display: inline-block;
            padding: 6px 10px;
            border: 1px solid #333;
            color: #111;
            text-decoration: none;
            font-size: 12px;
        }
        .sheet { display: block; }
        .label {
            width: {{ $labelWidth }}mm;
            height: {{ $labelHeight }}mm;
            padding: {{ $labelMargin }}mm;
            text-align: center;
            page-break-inside: avoid;
            page-break-after: always;
            overflow: hidden;
        }
        .product-name {
            font-size: 10px;
            font-weight: bold;
            line-height: 1.2;
            max-height: 24px;
            overflow: hidden;
        }
        .product-meta {
            font-size: 8px;
            margin-top: 1mm;
        }
        .label-field {
            line-height: 1.25;
        }
        .barcode {
            margin-top: 1mm;
        }
        .barcode img {
            width: 52mm;
            max-height: 15mm;
            object-fit: contain;
        }
        .barcode-number {
            font-size: 8px;
            margin-top: 1mm;
        }
        .qr { margin-top: 1mm; }
        .qr img { width: 24mm; height: 24mm; object-fit: contain; }
        @media print {
            .dontprint {
                display: none !important;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body @if(!($printAgentRender ?? false) && ($printSetting->print_method ?? 'browser') === 'browser' && ($printSetting->auto_print ?? $thermalSettings['auto_print'])) onload="window.print();" @endif>
    <div class="dontprint">
        <a class="btn" href="{{ route('products.index') }}">Back</a>
        <button class="btn" onclick="window.print()">Print</button>
    </div>

    <div class="sheet">
        @foreach($products as $product)
            <div class="label">
                <div class="product-meta">
                    @include('product::products.partials.legacy-label-fields')
                </div>
                @if($codeType === 'barcode')
                    <div class="barcode">
                        @if(!empty($product->bcode_image))
                            <img src="{{ tenant_asset('product_logos/barcode_logos/'.$product->bcode_image) }}" alt="Barcode">
                        @else
                            <div class="barcode-number">Barcode image not available</div>
                        @endif
                    </div>
                @elseif(!empty($product->qrcode_image))
                    <div class="qr"><img src="{{ tenant_asset('product_logos/qrcode_logos/'.$product->qrcode_image) }}" alt="QR code"></div>
                @else
                    <div class="barcode-number">QR code image not available</div>
                @endif
                <div class="barcode-number">{{ $product->bar_code }}</div>
            </div>
        @endforeach
    </div>

    @include('partials.local-print-script', ['printSetting' => $printSetting ?? null, 'documentType' => 'barcode', 'documentId' => null, 'printPayload' => $printPayload ?? null])
</body>
</html>
