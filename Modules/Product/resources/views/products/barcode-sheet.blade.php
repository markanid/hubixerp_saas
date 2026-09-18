<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $page_title ?? 'Print Barcodes' }}</title>
    @php $labelPage = \Modules\Settings\app\Models\BarcodeSetting::pageSize($thermalSettings); @endphp
    @include('product::products.partials.thermal-label-style')
</head>
<body @if(!($printAgentRender ?? false) && ($printSetting->print_method ?? 'browser') === 'browser' && ($printSetting->auto_print ?? $thermalSettings['auto_print'])) onload="window.print();" @endif>
    <div class="dontprint">
        <a class="btn" href="{{ route('products.index') }}">Back</a>
        <button class="btn" onclick="window.print()">Print</button>
        @include('product::products.partials.thermal-label-guidance')
    </div>
    <div class="sheet">
        @foreach($products->chunk($labelPage['columns']) as $labelRow)
            <div class="label-row">
                @foreach($labelRow as $product)
                    @include('product::products.partials.legacy-label-content')
                @endforeach
            </div>
        @endforeach
    </div>
    @include('partials.local-print-script', ['printSetting' => $printSetting ?? null, 'documentType' => 'barcode', 'documentId' => null, 'printPayload' => $printPayload ?? null])
</body>
</html>
