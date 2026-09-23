<div class="label">
    <div class="product-meta">
        @include('product::products.partials.legacy-label-fields')
    </div>
    <div class="codes">
        @if(in_array($codeType, ['barcode', 'both'], true))
            <div class="barcode">
                @if(!empty($product->bcode_image))
                    <img src="{{ asset('storage/product_logos/barcode_logos/'.$product->bcode_image) }}" alt="Barcode">
                @else
                    <div class="barcode-number">Barcode image not available</div>
                @endif
            </div>
        @endif
        @if(in_array($codeType, ['qr', 'both'], true))
            <div class="qr">
                @if(!empty($product->qrcode_image))
                    <img src="{{ asset('storage/product_logos/qrcode_logos/'.$product->qrcode_image) }}" alt="QR code">
                @else
                    <div class="barcode-number">QR code image not available</div>
                @endif
                <div class="barcode-number">{{ $product->bar_code }}</div>
            </div>
        @endif
    </div>
</div>
