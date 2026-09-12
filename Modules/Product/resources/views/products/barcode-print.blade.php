<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $page_title ?? 'Print Barcode' }}</title>
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
            margin: 10px;
        }
        .btn {
            display: inline-block;
            padding: 6px 10px;
            border: 1px solid #333;
            color: #111;
            text-decoration: none;
            font-size: 12px;
        }
        .label {
            width: {{ $labelWidth }}mm;
            height: {{ $labelHeight }}mm;
            padding: {{ $labelMargin }}mm;
            text-align: center;
            page-break-inside: avoid;
            overflow: hidden;
        }
        .product-name {
            font-size: 11px;
            font-weight: bold;
            line-height: 1.2;
            max-height: 26px;
            overflow: hidden;
        }
        .product-meta {
            font-size: 9px;
            margin-top: 1mm;
        }
        .label-field {
            line-height: 1.25;
        }
        .barcode {
            flex: 1;
            min-width: 0;
        }
        .barcode img {
            width: 48mm;
            max-width: 100%;
            max-height: 14mm;
            object-fit: contain;
        }
        .codes {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2mm;
            margin-top: 1mm;
        }
        .qr img {
            width: 24mm;
            height: 24mm;
            object-fit: contain;
        }
        .barcode-number {
            font-size: 9px;
            margin-top: 1mm;
        }
        @media print {
            .dontprint {
                display: none !important;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .label { page-break-after: always; }
        }
    </style>
</head>
<body @if(!($printAgentRender ?? false) && ($printSetting->print_method ?? 'browser') === 'browser' && ($printSetting->auto_print ?? $thermalSettings['auto_print'])) onload="window.print();" @endif>
    <div class="dontprint">
        <a class="btn" href="{{ route('products.show', $product->id) }}">Back</a>
        <button class="btn" onclick="window.print()">Print</button>
    </div>

    <div class="label">
        <div class="product-meta">
            @include('product::products.partials.legacy-label-fields')
        </div>
        <div class="codes">
            @if($codeType === 'barcode')
                <div class="barcode">
                    @if(!empty($product->bcode_image))
                        <img src="{{ tenant_asset('product_logos/barcode_logos/'.$product->bcode_image) }}" alt="Barcode">
                    @else
                        <div class="barcode-number">Barcode image not available</div>
                    @endif
                    <div class="barcode-number">{{ $product->bar_code }}</div>
                </div>
            @elseif(!empty($product->qrcode_image))
                <div class="qr">
                    <img src="{{ tenant_asset('product_logos/qrcode_logos/'.$product->qrcode_image) }}" alt="QR code">
                    <div class="barcode-number">{{ $product->bar_code }}</div>
                </div>
            @else
                <div class="barcode-number">QR code image not available</div>
            @endif
        </div>
    </div>

    @include('partials.local-print-script', ['printSetting' => $printSetting ?? null, 'documentType' => 'barcode', 'documentId' => null, 'printPayload' => $printPayload ?? null])
</body>
</html>
