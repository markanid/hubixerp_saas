<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $page_title ?? 'Print Barcode' }}</title>
    @php $labelPage = \Modules\Settings\app\Models\BarcodeSetting::pageSize($thermalSettings); @endphp
    @include('product::products.partials.thermal-label-style')
</head>
<body @if(!($printAgentRender ?? false) && ($printSetting->print_method ?? 'browser') === 'browser' && ($printSetting->auto_print ?? $thermalSettings['auto_print'])) onload="window.print();" @endif>
    <div class="dontprint">
        <a class="btn" href="{{ route('products.show', $product->id) }}">Back</a>
        <button class="btn" onclick="window.print()">Print</button>
        @include('product::products.partials.thermal-label-guidance')
    </div>
    <div class="sheet">
        <div class="label-row">
            @include('product::products.partials.legacy-label-content')
        </div>
    </div>
    @include('partials.local-print-script', ['printSetting' => $printSetting ?? null, 'documentType' => 'barcode', 'documentId' => null, 'printPayload' => $printPayload ?? null])
</body>
</html>
