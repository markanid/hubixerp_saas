@extends('layout')

@section('content-header')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{route('profile.dashboard')}}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{route('products.index')}}">Products</a></li>
                    <li class="breadcrumb-item active"> {{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="card card-navy card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-boxes"></i> {{$page_title}}</h3>
        <div class="card-tools">
            <a class="btn btn-warning btn-sm btn-flat" href="{{ route('products.barcode', $product->id) }}" target="_blank" rel="noopener">
                <i class="fas fa-barcode"></i> Legacy Barcode
            </a>
            <a class="btn btn-secondary btn-sm btn-flat" href="{{ route('products.qr', $product->id) }}" target="_blank" rel="noopener">
                <i class="fas fa-qrcode"></i> Legacy QR Code
            </a>
            <a class="btn btn-success btn-sm btn-flat" href="{{route('products.edit', $product->id)}}"><i class="fas fa-edit"></i> Edit</a>
            <a class="btn btn-dark btn-sm btn-flat" href="{{route('products.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card card-navy">
            <div class="card-header">
                <h3 class="card-title"><i class="far fa-file-alt"></i> Basic Details</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
                <table class="table table-bordered ">
                    <tbody>
                        <tr>
                            <td style="width:117px">Product Name</td>
                            <td style="color: #007bff;">{{ $product->product }}</td>
                        </tr>
                        <tr>
                            <td>Product Code</td>
                            <td>{{ $product->product_code }}</td>
                        </tr>
                        <tr>
                            <td>HSN Code</td>
                            <td>{{ $product->hsn_code }}</td>
                        </tr>
                        <tr>
                            <td>Barcode</td>
                            <td>{{ $product->bar_code }}</td>
                        </tr>
                        <tr>
                            <td>Purchase Price</td>
                            <td>{{ $product->pprice }}</td>
                        </tr>
                        <tr>
                            <td>MRP</td>
                            <td>{{ $product->mrp }}</td>
                        </tr>
                        <tr>
                            <td>Margin (%)</td>
                            <td>{{ $product->margin }}%</td>
                        </tr>
                        <tr>
                            <td>Margin (Amt)</td>
                            <td>{{ $product->amt_margin }}</td>
                        </tr>
                        <tr>
                            <td>Sale Price</td>
                            <td><span class="badge bg-green-minimal">{{ $product->price }}</span></td>
                        </tr>
                        <tr>
                            <td>GST</td>
                            <td>{{ $product->gst }}%</td>
                        </tr>
                        <tr>
                            <td>Unit</td>
                            <td>{{ $product->unit }}</td>
                        </tr>
                        <tr>
                            <td>Unit Quantity</td>
                            <td>{{ $product->uqty }}</td>
                        </tr>
                        <tr>
                            <td>Min Quantity</td>
                            <td>{{ $product->minquantity }}</td>
                        </tr>
                        <tr>
                            <td>Max Quantity</td>
                            <td>{{ $product->maxquantity }}</td>
                        </tr>
                        <tr>
                            <td>Stock</td>
                            <td><span class="badge bg-out-stock">{{ $stockQty." ".$product->unit }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- /.card-body -->
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-navy">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-layer-group"></i> Classification</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <td>Brand</td>
                            <td>@if ($product->brandid) {{ $product->brand->brand }} @endif
                            </td>
                        </tr>
                        <tr>
                            <td>Type</td>
                            <td>@if ($product->typeid) @if ($product->typeid == 2) Stockable @elseif ($product->typeid == 1) Non-Stockable @else Service @endif @endif
                            </td>
                        </tr>
                        <tr>
                            <td>Group</td>
                            <td>@if ($product->groupid) {{ $product->group->groups }} @endif
                            </td>
                        </tr>
                        <tr>
                            <td>Category</td>
                            <td>@if ($product->categoryid) {{ $product->category->category }} @endif
                            </td>
                        </tr>
                        <tr>
                            <td>Sub Category</td>
                            <td>@if ($product->subcategoryid) {{ $product->subcategory->subcategory }} @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- /.card-body -->
        </div>
        <div class="card card-navy">
            <div class="card-header">
                <h3 class="card-title"><i class="far fa-image"></i> Images</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <td>Product</td>
                            <td>
                                <div>
                                    @if(!empty($product->product_image) && !empty($product->product_image))
                                    <p><img src="{{tenant_asset('product_logos/'.$product->product_image)}}" alt="Product Photo" style="width: 150px; height: 150px;"></p>
                                    @else
                                    <p><img src="{{asset('uploads/avatar.png')}}" alt="Product Photo" style="width: 150px; height: 150px;"></p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Barcode</td>
                            <td>
                                <div>
                                    @if(!empty($product->bcode_image) && !empty($product->bcode_image))
                                    <p><img src="{{tenant_asset('product_logos/barcode_logos/'.$product->bcode_image)}}" alt="BarCode Photo" style="width: 200px; height: 50px;"></p>
                                    @else
                                    <p><img src="{{asset('uploads/avatar.png')}}" alt="BarCode Photo" style="width:150px; height: 50px;"></p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>QR Code</td>
                            <td>
                                <div>
                                    @if(!empty($product->qrcode_image) && !empty($product->qrcode_image))
                                    <p><img src="{{tenant_asset('product_logos/qrcode_logos/'.$product->qrcode_image)}}" alt="QR Code" style="width: 100px; height: 100px;"></p>
                                    @else
                                    <p><img src="{{asset('uploads/avatar.png')}}" alt="QR Code" style="width:100px; height: 100px;"></p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- /.card-body -->
        </div>
    </div>
</div>
@if(in_array($inventoryMode, ['mrp', 'batch'], true))
    <div class="row">
        <div class="col-12">
            <div class="card card-navy">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-qrcode"></i> Product Barcodes &amp; QR Codes</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-bordered table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width:55px;">Slot</th>
                                @foreach($inventoryTableFieldLabels as $field => $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                                <th>Available</th>
                                <th style="min-width:235px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($inventoryLabelRows as $index => $row)
                                @php $label = $row['label']; @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    @foreach($inventoryTableFieldLabels as $field => $heading)
                                        <td>
                                            @if(in_array($field, ['purchase_price', 'mrp', 'sale_price'], true))
                                                {{ $currencySymbol }} {{ number_format((float) $row[$field], 2) }}
                                            @elseif($field === 'expiry_date')
                                                {{ $row[$field]?->format('d/m/Y') ?? '-' }}
                                            @else
                                                {{ $row[$field] ?? '-' }}
                                            @endif
                                        </td>
                                    @endforeach
                                    <td>
                                        {{ number_format($row['raw_available_quantity'] / max((float) ($product->uqty ?: 1), 1), 2) }} {{ $product->unit }}
                                        @if($row['source_count'] > 1)
                                            <span class="badge badge-info">{{ $row['source_count'] }} lots</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('products.inventory-label.barcode.print', [$product, $label]) }}" target="_blank" class="btn btn-outline-primary btn-sm btn-flat">
                                            <i class="fas fa-barcode"></i> Print Barcode
                                        </a>
                                        <a href="{{ route('products.inventory-label.qr.print', [$product, $label]) }}" target="_blank" class="btn btn-outline-dark btn-sm btn-flat">
                                            <i class="fas fa-qrcode"></i> Print QR Code
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($inventoryTableFieldLabels) + 3 }}" class="text-center text-muted py-3">
                                        No {{ strtoupper($inventoryMode) }} inventory slots are available yet. A slot is created automatically when stock is received.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif
<div class="row">
    <div class="col-md-6">
        <div class="card card-navy">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-tie"></i> Supplier Flow</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body table-responsive p-0">
                <table class="table">
                  <thead>
                    <tr>
                      <th style="width: 10px">#</th>
                      <th style="width: 220px">Supplier</th>
                      <th>No of Times</th>
                      <th>Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    @php $total = 0; @endphp
                    @foreach($purchases as $index => $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $row->vendor_name ?? 'N/A' }}</td>
                        <td>{{ $row->purchase_count }}</td>
                        <td>{{ number_format($row->total_amount, 2) }}</td>
                    </tr>
                    @php $total += $row->total_amount; @endphp
                    @endforeach
                    <tr>
                        <th colspan="3">Net Purchase Amount</th>
                        <th>{{ number_format($total, 2) }}</th>
                    </tr>
                </tbody>
                </table>
            </div>
            <!-- /.card-body -->
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-navy">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-tag"></i> Customer Flow</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body table-responsive p-0">
                <table class="table">
                  <thead>
                    <tr>
                      <th style="width: 10px">#</th>
                      <th style="width: 220px">Customer</th>
                      <th>No of Times</th>
                      <th>Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    @php $total = 0; @endphp
                    @foreach($merged_customer_summary as $index => $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $row->customer_name }}</td>
                        <td>{{ $row->total_qty }}</td>
                        <td>{{ number_format($row->total_amount, 2) }}</td>
                    </tr>
                    @php $total += $row->total_amount; @endphp
                    @endforeach
                    <tr>
                        <th colspan="3">Total Usage Amount</th>
                        <th>{{ number_format($total_customer_amount, 2) }}</th>
                    </tr>
                </tbody>
                </table>
            </div>
            <!-- /.card-body -->
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script>
    $(document).ready(function(){
        var Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        // Check for the flash message and display the SweetAlert2 popup
        @if(session('success'))
            Toast.fire({
                icon: 'success',
                title: '{{ session('success') }}'
            });
        @endif
        @if(session('info'))
            Toast.fire({
                icon: 'info',
                title: '{{ session('info') }}'
            });
        @endif
    });
</script>
@endsection
