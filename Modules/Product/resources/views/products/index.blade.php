@extends('layout')

@section('content-header')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"></h1>
            </div>
            <!-- /.col -->
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item">Dashboard</li>
                    <li class="breadcrumb-item active">{{$page_title}}</li>
                </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
@endsection
<!-- /.content-header -->
@section('body')       
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-boxes"></i> {{$page_title}}</h3>
                    <div class="card-tools">
                        <button type="submit" form="product-label-print-form" formaction="{{ route('products.barcodes.print') }}" class="btn btn-warning btn-sm btn-flat" formtarget="_blank">
                            <i class="fas fa-barcode"></i> Print Selected Barcodes
                        </button>
                        <button type="submit" form="product-label-print-form" formaction="{{ route('products.qrcodes.print') }}" class="btn btn-dark btn-sm btn-flat" formtarget="_blank">
                            <i class="fas fa-qrcode"></i> Print Selected QR Codes
                        </button>
                        <a class="btn btn-primary btn-sm btn-flat" href="{{ route('products.create') }}"><i class="fas fa-plus-circle"></i> Create</a>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <form id="product-label-print-form" method="POST" action="{{ route('products.barcodes.print') }}" target="_blank">
                        @csrf
                        <table id="product_table" class="table table-bordered table-striped text-nowrap">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="select_all_products" title="Select all">
                                    </th>
                                    <th>SNo</th>
                                    <th>Product Code</th>
                                    <th>Product</th>
                                    <th>HSN Code</th>
                                    <th>Purchase Price</th>
                                    <th>Sale Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Options</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $i=1;
                                @endphp
                                @foreach($products as $row)
                                    @php
                                        $currentStock = (optional($row->stock)->stock_qty ?? 0) / ($row->uqty ?: 1);
                                        $hasStock = optional($row->stock)->stock_qty > 0;
                                        $isUsed = $row->purchaseInDetails->isNotEmpty() || $row->saleInDetails->isNotEmpty() || $row->returnInDetails->isNotEmpty() || $row->serviceInDetails->isNotEmpty();
                                    @endphp
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="product_ids[]" value="{{ $row->id }}" class="product-select">
                                        </td>
                                        <td>{{$i++;}}</td>
                                        <td><a href="{{ route('products.show',$row->id) }}">{{$row->product_code}}</a></td>
                                        <td>{{$row->product}}</td>
                                        <td>{{$row->hsn_code}}</td>
                                        <td>{{ $maskPurchasePrice ? \Modules\Settings\app\Models\BarcodeSetting::maskPurchasePrice($row->pprice) : $row->pprice }}</td>
                                        <td>{{$row->price}}</td>
                                        <td> @if($row->typeid==2)
                                            {{ $currentStock }} {{ $row->unit }}
                                            @else
                                            <span class="badge bg-dark-minimal">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->typeid==2)
                                                @if($currentStock <= 0)
                                                    <span class="badge bg-out-stock">Out of Stock</span>
                                                @elseif($currentStock > 0 && $currentStock < $row->minquantity)
                                                    <span class="badge bg-yellow-minimal">Low Stock</span>
                                                @elseif($currentStock >= $row->minquantity && $currentStock <= $row->maxquantity)
                                                    <span class="badge bg-green-minimal">In Stock</span>
                                                @elseif($currentStock > $row->maxquantity)
                                                    <span class="badge bg-blue-minimal">Stock Overload</span>
                                                @endif
                                            @else
                                                <span class="badge bg-dark-minimal">Non-Stockable</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a class="btn btn-app" href="{{ route('products.barcode', $row->id) }}" target="_blank" title="Print Barcode"><i class="fas fa-barcode text-primary"></i></a>
                                            <a class="btn btn-app" href="{{ route('products.qr', $row->id) }}" target="_blank" title="Print QR Code"><i class="fas fa-qrcode text-dark"></i></a>
                                            <a class="btn btn-app" href="{{route('products.edit', $row->id)}}"><i class="far fa-edit"></i></a>
                                            @if($isUsed)
                                                <span title="Cannot delete: stock transfered">
                                                    <a href="#" class="btn btn-app-delete disabled">
                                                        <i class="far fa-trash-alt"></i>
                                                    </a>
                                                </span>
                                            @else
                                                <a href="#" class="btn btn-app-delete delete-btn" data-url="{{ route('products.delete', ['id' => $row->id]) }}"><i class="far fa-trash-alt"></i></a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@include('partials.delete-modal')
@section('scripts')
@include('partials.delete-modal-script')
@include('partials.common-index-script', ['tableId' => 'product_table'])
<script>
    $(function () {
        $('#select_all_products').on('change', function () {
            $('.product-select').prop('checked', $(this).is(':checked'));
        });

        $('#product-label-print-form').on('submit', function (event) {
            if ($('.product-select:checked').length === 0) {
                event.preventDefault();
                if (typeof toastr !== 'undefined') {
                    toastr.warning('Please select at least one product.', 'No Products Selected');
                } else {
                    alert('Please select at least one product.');
                }
            }
        });
    });
</script>
@endsection