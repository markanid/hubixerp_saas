@if(($thermalSettings['layout_mode'] ?? \Modules\Settings\app\Models\BarcodeSetting::LAYOUT_THERMAL) === \Modules\Settings\app\Models\BarcodeSetting::LAYOUT_COMMON)
<p class="print-guidance">
    Common sticker roll &middot;
    One centred label per roll position &middot;
    Sticker: {{ $thermalSettings['label_width_mm'] }} &times; {{ $thermalSettings['label_height_mm'] }} mm.
    Print at 100% / actual size, with no margins or headers and footers.
</p>
@else
<p class="print-guidance">
    {{ $labelPage['columns'] }} label(s) across &middot;
    One sticker: {{ $thermalSettings['label_width_mm'] }} &times; {{ $thermalSettings['label_height_mm'] }} mm &middot;
    Printer paper: {{ $labelPage['width_mm'] }} &times; {{ $labelPage['height_mm'] }} mm.
    Print at 100% / actual size, with no margins or headers and footers. Set the printer to gap/web labels.
    Change label dimensions and columns in Company Settings &rarr; Barcode &amp; QR Settings.
</p>
@endif
