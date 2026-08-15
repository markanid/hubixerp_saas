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
                    <li class="breadcrumb-item"><a href="{{route('sales.index')}}">Sale</a></li>
                    <li class="breadcrumb-item active"> {{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
@php
    $saleDetails = collect($sale->saleDetails ?? []);
    $total_before_discount = $saleDetails->sum(fn ($saleDetail) => (float) $saleDetail->sad_price);
    $discount_amount = $saleDetails->sum(fn ($saleDetail) => (float) $saleDetail->sad_adisc);
    $total_after_discount = $saleDetails->sum(fn ($saleDetail) => (float) $saleDetail->sad_total);
    $taxable_value = $saleDetails->sum(fn ($saleDetail) => (float) $saleDetail->sad_total - (float) $saleDetail->sad_gst);
    $gst_value = $saleDetails->sum(fn ($saleDetail) => (float) $saleDetail->sad_gst);
    $taxType = strtolower((string) ($taxType ?? 'gst'));
    $isVat = $taxType === 'vat';
    $taxLabel = $taxLabel ?? ($isVat ? 'VAT' : 'GST');
    $taxNumberLabel = $isVat ? 'VAT No' : 'GSTIN';
    $showTax = (bool) ($collectTax ?? true) && (string) ($sale->sa_type ?? '1') !== '0';
@endphp
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-th"></i> {{$page_title}}</h3>
        <div class="card-tools">
            @can('sale.create')
            <a class="btn btn-primary btn-sm btn-flat" href="{{route('sales.create')}}"><i class="fa fa-plus-circle"></i> Create New</a>
            @endcan
            @if(!$is_cancelled)
                @can('sale.update')
                <a class="btn btn-info btn-sm btn-flat" href="{{route('sales.edit', $sale->sa_id)}}"><i class="fas fa-edit"></i> Edit</a>
                @endcan
            @endif
            <a class="btn btn-warning btn-sm btn-flat" href="{{route('sales.print', $sale->sa_id)}}"><i class="fas fa-print"></i> Print</a>
            <a class="btn btn-dark btn-sm btn-flat" href="{{route('sales.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6 d-flex">
        <div class="card flex-fill">
            <div class="card-body">
                <div class="col-md-12">
                    <div class="table-responsive">
                        <table class="table table-bordered" style="margin-bottom: 5px;">
                            <tbody>
                                <tr>
                                    <td>Sale Date</td>
                                    <td style="color:#f50303"><b>{{ \Carbon\Carbon::parse($sale->sa_date)->format('d/m/Y') }}</b></td>
                                </tr>
                                <tr>
                                    <td>Bill No</td>
                                    <td style="color:#1d91be"><b>{{ ($sale->sa_vno)}}</b></td>
                                </tr>
                                @if($sale->sa_due_date)
                                    <tr>
                                        <td>Payment Due Date</td>
                                        <td>
                                            <b>{{ \Carbon\Carbon::parse($sale->sa_due_date)->format('d/m/Y') }}</b>

                                            @if($sale->dueBalance() > 0 && \Carbon\Carbon::parse($sale->sa_due_date)->lte(now()))
                                                <span class="badge badge-warning">Payment Due</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td>Invoice Type</td>
                                    <td>
                                        <b>
                                             {{ $sale->sa_type == 1 ? 'B2C' : ($sale->sa_type == 2 ? 'B2B' : 'Legacy No '.$taxLabel) }}
                                        </b>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{ $isVat ? 'E-Invoice' : 'E-Way Bill No' }}</td>
                                    <td><b>{{ $sale->sa_eway_bill_no ?? '-' }}</b></td>
                                </tr>
                                <tr>
                                    <td>Remarks</td>
                                    <td><b>{{ $sale->sa_remark ?? '-' }}</b></td>
                                </tr>
                                <tr>
                                    <td>User</td>
                                    <td><b>{{ $sale->user?->user_name ?? '-' }}</b></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 d-flex">
        <div class="card flex-fill">
            <div class="card-body">
                <div class="col-md-12">
                    <div class="table-responsive">
                        <table class="table table-bordered" style="margin-bottom: 5px;">
                            <tbody>
                                <tr>
                                    <td>Customer Name</td>
                                    <td>
                                        <b>
                                            @if($sale->customer)
                                                <a href="{{ route('customers.show', $sale->customer->id) }}">{{ $sale->customer->customer }}</a>
                                            @else
                                                -
                                            @endif
                                        </b>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Customer Phone No</td>
                                    <td><b>{{ $sale->customer?->phone ?? '-' }}</b></td>
                                </tr>
                                <tr>
                                    <td>Customer Address</td>
                                    <td><b>{{ $sale->customer?->address ?? '-' }}</b></td>
                                </tr>
                                <tr>
                                    <td>{{ $taxNumberLabel }}</td>
                                    <td><b>{{ $sale->customer?->gstin_no ?? '-' }}</b></td>
                                </tr>
                                <tr>
                                    <td>Location</td>
                                    <td><b>{{ $sale->sa_loc ?? '-' }}</b></td>
                                </tr>
                                <tr>
                                    <td>Vehicle</td>
                                    <td><b>{{ $sale->sa_vehicle ?? '-' }}</b></td>
                                </tr>
                                @php
                                    $state = isset($states) ? $states->firstWhere('state_code', $sale->sa_state_code) : null;
                                @endphp
                                @if(!$isVat && $showTax)
                                    <tr>
                                        <td>GST State</td>
                                        <td><b>{{ $state? ($state->state_name.' ('.$state->state_code.')') : '-' }}</b></td>
                                    </tr>
                                    <tr>
                                        <td>GST Type</td>
                                        <td><b>{{ ($sale->sa_is_igst ?? 0) == 1 ? 'IGST' : 'CGST + SGST' }}</b></td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="sale_table" class="table table-bordered table-striped">
                <thead>
                    @php
                        $showBatch = $sale->saleDetails->contains(fn($detail) => $detail->batchMovements()->exists());
                        $showMrpLot = $sale->saleDetails->contains(fn($detail) => !empty($detail->mrp_stock_lot_id));
                        $showInventoryLot = $showBatch || $showMrpLot;
                    @endphp
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>HSN Code</th>
                        @if($saleSettings['manufacturing_date'] ?? false)
                            <th>MFG Date</th>
                        @endif
                        @if($showBatch)<th>Batch / Expiry</th>@endif
                        @if($showMrpLot)<th>Purchase Lot / MRP</th>@endif
                        <th>Unit Price</th>
                        <th>Qty</th>
                        <th>Total Before Discount</th>
                        <th>Discount(Amt)</th>
                        <th>Total</th>
                        @if($showTax)
                            <th>Taxable Value</th>
                            <th>{{ $taxLabel }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                        @foreach($saleDetails as $saleDetail)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    @if($saleDetail->product)
                                        <a href="{{ route('products.show',$saleDetail->product->id) }}">{{ $saleDetail->product->product }}</a>
                                    @else
                                        <span class="text-muted">Removed item</span>
                                    @endif
                                </td>
                                <td>{{$saleDetail->sad_hsn}}</td>
                                @if($saleSettings['manufacturing_date'] ?? false)
                                    <td>{{$saleDetail->manufacturing_date}}</td>
                                @endif
                                @if($showBatch)
                                    <td>
                                        @forelse($saleDetail->batchMovements as $movement)
                                            {{ $movement->batch?->batch_no }} ({{ number_format($movement->quantity_out, 2) }})
                                            @if($movement->batch?->expiry_date) - {{ $movement->batch->expiry_date->format('m/Y') }} @endif
                                            @if(!$loop->last)<br>@endif
                                        @empty - @endforelse
                                    </td>
                                @endif
                                @if($showMrpLot)
                                    <td>
                                        {{ $saleDetail->mrpStockLot?->purchase_voucher ?: '-' }}
                                        @if($saleDetail->mrpStockLot?->purchase_date)
                                            - {{ $saleDetail->mrpStockLot->purchase_date->format('d/m/Y') }}
                                        @endif
                                        <br>MRP {{ number_format($saleDetail->stock_mrp ?? 0, 2) }}
                                    </td>
                                @endif
                                <td>{{$saleDetail->sad_uprice}}</td>
                                <td>{{$saleDetail->sad_itemqty.' '.$saleDetail->sad_unit}}</td>
                                <td>{{$saleDetail->sad_price}}</td>
                                <td>{{$saleDetail->sad_adisc.' ('.$saleDetail->sad_pdisc.'%)'}}</td>
                                <td>{{$saleDetail->sad_total}}</td>
                                @if($showTax)
                                    <td>{{$saleDetail->sad_total - $saleDetail->sad_gst}}</td>
                                    <td>{{$saleDetail->sad_gst.' ('.($saleDetail->product?->gst ?? 0).'%)'}}</td>
                                @endif
                            </tr>
                        @endforeach
                </tbody>
                <tfoot>
                    <tr style="font-weight: bold; background-color: #f5f5f5;">
                        <td colspan="{{ (($saleSettings['manufacturing_date'] ?? false) ? 6 : 5) + ($showInventoryLot ? 1 : 0) }}" class="text-right">Total</td>
                        <td class="text-right">Rs. {{ number_format($total_before_discount, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($discount_amount, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($total_after_discount, 2) }}</td>
                        @if($showTax)
                            <td class="text-right">Rs. {{ number_format($taxable_value, 2) }}</td>
                            <td class="text-right">Rs. {{ number_format($gst_value, 2) }}</td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6 offset-md-6">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" style="margin-bottom: 5px;">
                        <tr>
                            <th>Amount Payable</th>
                            <th>
                                <font color="green">Rs. <label>{{ number_format((float) $sale->sa_amount_payable, 2) }}</label></font>
                            </th>
                        </tr>
                        <tr>
                            <th>Round Off</th>
                            <th>
                                Rs. <label>{{ number_format((float) $sale->sa_round, 2) }}</label>
                            </th>
                        </tr>
                        <tr>
                            <th>Payment Mode</th>
                            <th>{{ $sale->banking?->bk_bank ?? '-' }}</th>
                        </tr>
                        <tr>
                            <th>Amount Paid</th>
                            <th>Rs. {{ number_format((float) $sale->sa_amount_paid, 2) }}</th>
                        </tr>
                        <tr>
                            <th>Balance</th>
                            <th>Rs. {{ number_format((float) $sale->sa_balance, 2) }}</th>
                        </tr>
                    </table>
                </div>
            </div>
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