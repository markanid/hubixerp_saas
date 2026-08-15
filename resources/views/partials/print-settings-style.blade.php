@php
    $paperSize = strtoupper((string) ($printSetting->paper_size ?? 'A4'));
    $orientation = strtolower((string) ($printSetting->orientation ?? 'portrait'));
    $scale = max(50, min(150, (int) ($printSetting->scale ?? 100)));
    $margin = max(0, min(50, (int) ($printSetting->margin_mm ?? 10)));
@endphp
<style>
    @page {
        size: {{ $paperSize }} {{ $orientation }};
        margin: {{ $margin }}mm;
    }

    @media print {
        body {
            zoom: {{ $scale / 100 }};
        }
    }
</style>
