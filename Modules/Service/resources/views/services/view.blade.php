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
                    <li class="breadcrumb-item"><a href="{{route('services.index')}}">Service</a></li>
                    <li class="breadcrumb-item active"> {{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
@php
    $serviceItems = collect($serviceItems ?? []);
    $saleItems = collect($saleItems ?? []);
    $service_total_before_discount = $serviceItems->sum(fn ($detail) => (float) $detail->svd_price);
    $service_discount_amount = $serviceItems->sum(fn ($detail) => (float) $detail->svd_adisc);
    $service_total_after_discount = $serviceItems->sum(fn ($detail) => (float) $detail->svd_total);
    $service_taxable_value = $serviceItems->sum(fn ($detail) => (float) $detail->svd_total - (float) $detail->svd_gst);
    $service_gst_value = $serviceItems->sum(fn ($detail) => (float) $detail->svd_gst);
    $sale_total_before_discount = $saleItems->sum(fn ($detail) => (float) $detail->svd_price);
    $sale_discount_amount = $saleItems->sum(fn ($detail) => (float) $detail->svd_adisc);
    $sale_total_after_discount = $saleItems->sum(fn ($detail) => (float) $detail->svd_total);
    $sale_taxable_value = $saleItems->sum(fn ($detail) => (float) $detail->svd_total - (float) $detail->svd_gst);
    $sale_gst_value = $saleItems->sum(fn ($detail) => (float) $detail->svd_gst);
    $taxType = strtolower((string) ($taxType ?? 'gst'));
    $isVat = $taxType === 'vat';
    $taxLabel = $taxLabel ?? ($isVat ? 'VAT' : 'GST');
    $state = isset($states) ? $states->firstWhere('state_code', $service->sv_state_code) : null;
    $showTax = (bool) ($collectTax ?? true) && (string) ($service->sv_type ?? '1') !== '0';
    $invoiceType = (string) ($service->sv_type ?? '1') === '2' ? 'B2B' : ((string) ($service->sv_type ?? '1') === '0' ? 'Legacy No '.$taxLabel : 'B2C');
    $showManufacturingDate = $saleItems->contains(fn ($detail) => !empty($detail->manufacturing_date));
@endphp

<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-th"></i> {{$page_title}}</h3>
        <div class="card-tools">
            @can('service.create')
            <a class="btn btn-primary btn-sm btn-flat" href="{{route('services.create')}}"><i class="fa fa-plus-circle"></i> Create New</a>
            @endcan
            @if(!$is_cancelled)
                @can('service.update')
                <a class="btn btn-info btn-sm btn-flat" href="{{route('services.edit', $service->sv_id)}}"><i class="fas fa-edit"></i> Edit</a>
                @endcan
            @endif
            <a class="btn btn-warning btn-sm btn-flat" href="{{route('services.print', $service->sv_id)}}"><i class="fas fa-print"></i> Print</a>
            <a class="btn btn-dark btn-sm btn-flat" href="{{route('services.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 d-flex">
        <div class="card flex-fill">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" style="margin-bottom: 5px;">
                        <tbody>
                            <tr>
                                <td>Service Date</td>
                                <td style="color:#f50303"><b>{{ \Carbon\Carbon::parse($service->sv_date)->format('d/m/Y') }}</b></td>
                            </tr>
                            <tr>
                                <td>Bill No</td>
                                <td style="color:#1d91be"><b>{{ $service->sv_vno }}</b></td>
                            </tr>
                            <tr>
                                <td>Bill Type</td>
                                <td><b>{{ $invoiceType }}</b></td>
                            </tr>
                            @if($service->sv_due_date)
                                <tr>
                                    <td>Payment Due Date</td>
                                    <td>
                                        <b>{{ \Carbon\Carbon::parse($service->sv_due_date)->format('d/m/Y') }}</b>
                                        @if($service->dueBalance() > 0 && \Carbon\Carbon::parse($service->sv_due_date)->lte(now()))
                                            <span class="badge badge-warning">Payment Due</span>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <td>{{ $isVat ? 'E-Invoice' : 'E-Way Bill No' }}</td>
                                <td><b>{{ $service->sv_eway_bill_no ?? '-' }}</b></td>
                            </tr>
                            <tr>
                                <td>Remarks</td>
                                <td><b>{{ $service->sv_remark ?? '-' }}</b></td>
                            </tr>
                            <tr>
                                <td>User</td>
                                <td><b>{{ $service->user?->user_name ?? '-' }}</b></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 d-flex">
        <div class="card flex-fill">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" style="margin-bottom: 5px;">
                        <tbody>
                            <tr>
                                <td>Customer Name</td>
                                <td><b><a href="{{ route('customers.show', $service->customer->id) }}" target="_blank">{{ $service->customer?->customer ?? '-' }}</a></b></td>
                            </tr>
                            <tr>
                                <td>Customer Phone No</td>
                                <td><b>{{ $service->customer?->phone ?? '-' }}</b></td>
                            </tr>
                            <tr>
                                <td>Location</td>
                                <td><b>{{ $service->sv_loc ?? '-' }}</b></td>
                            </tr>
                            <tr>
                                <td>Vehicle</td>
                                <td><b>{{ $service->sv_vehicle ?? '-' }}</b></td>
                            </tr>
                            @if(!$isVat && $showTax)
                                <tr>
                                    <td>GST State</td>
                                    <td><b>{{ $state ? ($state->state_name.' ('.$state->state_code.')') : '-' }}</b></td>
                                </tr>
                                <tr>
                                    <td>GST Type</td>
                                    <td><b>{{ ($service->sv_is_igst ?? 0) == 1 ? 'IGST' : 'CGST + SGST' }}</b></td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if($serviceItems->count())
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-wrench"></i> Service Items</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="service_table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Service</th>
                        <th>Price</th>
                        <th>Discount(Amt)</th>
                        <th>Total</th>
                        @if($showTax)
                            <th>Taxable Value</th>
                            <th>{{ $taxLabel }}</th>
                        @endif
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($serviceItems as $serviceDetail)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                @if($serviceDetail->product)
                                    <a href="{{ route('products.show',$serviceDetail->product->id) }}">{{ $serviceDetail->product->product }}</a>
                                @else
                                    <span class="text-muted">Removed item</span>
                                @endif
                            </td>
                            <td>{{ $serviceDetail->svd_uprice }}</td>
                            <td>{{ $serviceDetail->svd_adisc.' ('.$serviceDetail->svd_pdisc.'%)' }}</td>
                            <td>{{ $serviceDetail->svd_total }}</td>
                            @if($showTax)
                                <td>{{ $serviceDetail->svd_total - $serviceDetail->svd_gst }}</td>
                                <td>{{ $serviceDetail->svd_gst.' ('.($serviceDetail->product?->gst ?? 0).'%)' }}</td>
                            @endif
                            <td>{{ $serviceDetail->svd_remark }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="font-weight: bold; background-color: #f5f5f5;">
                        <td colspan="2" class="text-right">Total</td>
                        <td class="text-right">Rs. {{ number_format($service_total_before_discount, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($service_discount_amount, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($service_total_after_discount, 2) }}</td>
                        @if($showTax)
                            <td class="text-right">Rs. {{ number_format($service_taxable_value, 2) }}</td>
                            <td class="text-right">Rs. {{ number_format($service_gst_value, 2) }}</td>
                        @endif
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endif

@if($saleItems->count())
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-cash-register"></i> Product Items</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="sale_table" class="table table-bordered table-striped">
                @php($showBatch = $saleItems->contains(fn($detail) => $detail->batchMovements()->exists()))
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>HSN Code</th>
                        @if($showManufacturingDate)
                            <th>MFG Date</th>
                        @endif
                        @if($showBatch)<th>Batch / Expiry</th>@endif
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
                    @foreach($saleItems as $saleDetail)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                @if($saleDetail->product)
                                    <a href="{{ route('products.show',$saleDetail->product->id) }}">{{ $saleDetail->product->product }}</a>
                                @else
                                    <span class="text-muted">Removed item</span>
                                @endif
                            </td>
                            <td>{{ $saleDetail->svd_hsn }}</td>
                            @if($showManufacturingDate)
                                <td>{{ $saleDetail->manufacturing_date ?? '-' }}</td>
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
                            <td>{{ $saleDetail->svd_uprice }}</td>
                            <td>{{ $saleDetail->svd_itemqty.' '.$saleDetail->svd_unit }}</td>
                            <td>{{ $saleDetail->svd_price }}</td>
                            <td>{{ $saleDetail->svd_adisc.' ('.$saleDetail->svd_pdisc.'%)' }}</td>
                            <td>{{ $saleDetail->svd_total }}</td>
                            @if($showTax)
                                <td>{{ $saleDetail->svd_total - $saleDetail->svd_gst }}</td>
                                <td>{{ $saleDetail->svd_gst.' ('.($saleDetail->product?->gst ?? 0).'%)' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="font-weight: bold; background-color: #f5f5f5;">
                        <td colspan="{{ 5 + ($showManufacturingDate ? 1 : 0) + ($showBatch ? 1 : 0) }}" class="text-right">Total</td>
                        <td class="text-right">Rs. {{ number_format($sale_total_before_discount, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($sale_discount_amount, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($sale_total_after_discount, 2) }}</td>
                        @if($showTax)
                            <td class="text-right">Rs. {{ number_format($sale_taxable_value, 2) }}</td>
                            <td class="text-right">Rs. {{ number_format($sale_gst_value, 2) }}</td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endif

<div class="row">
    <div class="col-md-6 offset-md-6">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" style="margin-bottom: 5px;">
                        <tr>
                            <th>Amount Payable</th>
                            <th><font color="green">Rs. <label>{{ number_format((float)  $service->sv_amount_payable, 2) }}</label></font></th>
                        </tr>
                        <tr>
                            <th>Round Off</th>
                            <th>Rs. <label>{{ number_format((float) $service->sv_round, 2) }}</label></th>
                        </tr>
                        <tr>
                            <th>Payment Mode</th>
                            <th>{{ $service->banking?->bk_bank ?? '-' }}</th>
                        </tr>
                        <tr>
                            <th>Amount Paid</th>
                            <th>Rs. {{ number_format((float) $service->sv_amount_paid, 2) }}</th>
                        </tr>
                        <tr>
                            <th>Balance</th>
                            <th>Rs. {{ number_format((float) $service->sv_balance, 2) }}</th>
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
