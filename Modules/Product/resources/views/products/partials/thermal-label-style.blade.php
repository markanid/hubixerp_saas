@php
    $labelWidth = (float) $thermalSettings['label_width_mm'];
    $labelHeight = (float) $thermalSettings['label_height_mm'];
    $labelMargin = (float) $thermalSettings['label_margin_mm'];
    $compact = $labelWidth <= 50 || $labelHeight <= 30;
    $barcodeHeight = min(13, max(4, $labelHeight * 0.27));
    $qrSize = min(24, max(8, $labelHeight - 2 * $labelMargin - 8));
@endphp
<style>
    @page { size: {{ $labelPage['width_mm'] }}mm {{ $labelPage['height_mm'] }}mm; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #000; font-family: Arial, Helvetica, sans-serif; }
    .dontprint { margin: 10px; font-size: 12px; }
    .btn { display: inline-block; padding: 6px 10px; border: 1px solid #333; color: #111; background: #fff; text-decoration: none; font-size: 12px; }
    .print-guidance { margin: 8px 0; }
    .label-row {
        display: grid;
        grid-template-columns: repeat({{ $labelPage['columns'] }}, {{ $labelWidth }}mm);
        column-gap: {{ $labelPage['column_gap_mm'] }}mm;
        width: {{ $labelPage['width_mm'] }}mm;
        height: {{ $labelHeight }}mm;
        overflow: hidden;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    .label {
        width: {{ $labelWidth }}mm;
        height: {{ $labelHeight }}mm;
        padding: {{ $labelMargin }}mm;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        text-align: center;
        min-width: 0;
    }
    .product-meta, .meta {
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
        font-size: {{ $compact ? 7.5 : 9 }}px;
        line-height: 1.05;
    }
    .label-field { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .product-name { font-weight: bold; }
    .heading-short { display: none; }
    @if($compact)
    .product-meta, .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-auto-flow: dense; align-content: start; column-gap: 2px; text-align: left; }
    .product-name { grid-column: 1 / -1; text-align: center; }
    .heading-long { display: none; }
    .heading-short { display: inline; }
    .product-name .heading-short { display: none; }
    @endif
    @if($labelWidth <= 32)
    .label-currency { display: none; }
    @endif
    .codes { flex: 0 0 auto; min-width: 0; display: flex; align-items: center; justify-content: center; gap: 1mm; }
    .barcode { flex: 1; min-width: 0; padding: 0 2.5mm; }
    .barcode img { display: block; width: 100%; height: {{ $barcodeHeight }}mm; object-fit: fill; image-rendering: pixelated; }
    .qr img { display: block; width: {{ $qrSize }}mm; height: {{ $qrSize }}mm; object-fit: contain; }
    .barcode-number, .number { font-size: {{ $compact ? 7.5 : 9 }}px; line-height: 1.1; margin-top: .2mm; white-space: nowrap; }
    @if(!$compact && $codeType !== 'barcode')
    .label[data-inventory] { flex-direction: row; align-items: center; gap: 2mm; }
    .label[data-inventory] .meta { flex: 1; min-width: 0; }
    .label[data-inventory] .codes { flex: 1.35; }
    @endif
    @media screen {
        .label-row { outline: 1px dashed #aaa; margin-bottom: 8px; }
        .label { outline: 1px dotted #ddd; }
    }
    @media print {
        .dontprint { display: none !important; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .label-row + .label-row { break-before: page; page-break-before: always; }
    }
</style>
