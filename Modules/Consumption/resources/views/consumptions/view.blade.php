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
                    <li class="breadcrumb-item"><a href="{{route('consumptions.index')}}">Consumption</a></li>
                    <li class="breadcrumb-item active"> {{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
@php
    $consumptionDetails = collect($consumption->consumptionDetails ?? []);
    $totalAmount = $consumptionDetails->sum(fn ($detail) => (float) $detail->cond_total);
    $showBatch = $consumptionDetails->contains(fn ($detail) => $detail->batchMovements->isNotEmpty() || !empty($detail->batch_no));
@endphp
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-box-open"></i> {{$page_title}}</h3>
        <div class="card-tools">
            <a class="btn btn-primary btn-sm btn-flat" href="{{route('consumptions.create')}}"><i class="fa fa-plus-circle"></i> Create New</a>
            @if(!$is_cancelled)
                <a class="btn btn-info btn-sm btn-flat" href="{{route('consumptions.edit', $consumption->con_id)}}"><i class="fas fa-edit"></i> Edit</a>
            @endif
            <a class="btn btn-warning btn-sm btn-flat" href="{{route('consumptions.print', $consumption->con_id)}}"><i class="fas fa-print"></i> Print</a>
            <a class="btn btn-dark btn-sm btn-flat" href="{{route('consumptions.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
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
                                <td>Consumption Date</td>
                                <td style="color:#f50303"><b>{{ \Carbon\Carbon::parse($consumption->con_date)->format('d/m/Y') }}</b></td>
                            </tr>
                            <tr>
                                <td>Voucher No</td>
                                <td style="color:#1d91be"><b>{{ $consumption->con_vno }}</b></td>
                            </tr>
                            <tr>
                                <td>Status</td>
                                <td>
                                    <b>
                                        @if($is_cancelled)
                                            <span class="badge badge-danger">Cancelled</span>
                                        @else
                                            <span class="badge badge-success">Active</span>
                                        @endif
                                    </b>
                                </td>
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
                                <td>User</td>
                                <td><b>{{ $consumption->user?->user_name ?? '-' }}</b></td>
                            </tr>
                            <tr>
                                <td>Total Items</td>
                                <td><b>{{ $consumptionDetails->count() }}</b></td>
                            </tr>
                            <tr>
                                <td>Total Quantity</td>
                                <td><b>{{ number_format($consumptionDetails->sum(fn ($detail) => (float) $detail->cond_qty), 2) }}</b></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="consumption_table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>HSN Code</th>
                        @if($showBatch)<th>Batch / Expiry</th>@endif
                        <th>Unit Price</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($consumptionDetails as $consumptionDetail)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                @if($consumptionDetail->product)
                                    <a href="{{ route('products.show',$consumptionDetail->product->id) }}">{{ $consumptionDetail->product->product_code.' - '.$consumptionDetail->product->product }}</a>
                                @else
                                    <span class="text-muted">Removed item</span>
                                @endif
                            </td>
                            <td>{{ $consumptionDetail->cond_hsn }}</td>
                            @if($showBatch)
                                <td>
                                    @forelse($consumptionDetail->batchMovements as $movement)
                                        {{ $movement->batch?->batch_no }} ({{ number_format($movement->quantity_out, 2) }})
                                        @if($movement->batch?->expiry_date) - {{ $movement->batch->expiry_date->format('m/Y') }} @endif
                                        @if(!$loop->last)<br>@endif
                                    @empty
                                        {{ $consumptionDetail->batch_no ?? '-' }}
                                    @endforelse
                                </td>
                            @endif
                            <td>{{ number_format((float) $consumptionDetail->cond_uprice, 2) }}</td>
                            <td>{{ number_format((float) $consumptionDetail->cond_qty, 2).' '.$consumptionDetail->cond_unit }}</td>
                            <td>{{ number_format((float) $consumptionDetail->cond_total, 2) }}</td>
                            <td>{{ $consumptionDetail->cond_remark ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="font-weight: bold; background-color: #f5f5f5;">
                        <td colspan="{{ 5 + ($showBatch ? 1 : 0) }}" class="text-right">Total</td>
                        <td class="text-right">Rs. {{ number_format($totalAmount, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-5 offset-md-7">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" style="margin-bottom: 5px;">
                        <tr>
                            <th>Total Amount</th>
                            <th>
                                <font color="green">Rs. <label>{{ number_format((float) $consumption->con_amount, 2) }}</label></font>
                            </th>
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
