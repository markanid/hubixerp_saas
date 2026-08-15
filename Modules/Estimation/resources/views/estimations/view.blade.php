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
                    <li class="breadcrumb-item"><a href="{{ route('profile.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('estimations.index') }}">Estimation</a></li>
                    <li class="breadcrumb-item active">{{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
@php
    $estimationDetails = collect($estimation->estimationDetails ?? []);
    $total_before_discount = $estimationDetails->sum(fn ($detail) => (float) $detail->esd_price);
    $discount_amount = $estimationDetails->sum(fn ($detail) => (float) $detail->esd_adisc);
    $total_after_discount = $estimationDetails->sum(fn ($detail) => (float) $detail->esd_total);
@endphp

<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-receipt"></i> {{ $page_title }}</h3>
        <div class="card-tools">
            <a class="btn btn-primary btn-sm btn-flat" href="{{ route('estimations.create') }}"><i class="fa fa-plus-circle"></i> Create New</a>
            @if(!$is_cancelled)
                <a class="btn btn-info btn-sm btn-flat" href="{{ route('estimations.edit', $estimation->es_id) }}"><i class="fas fa-edit"></i> Edit</a>
            @endif
            <a class="btn btn-warning btn-sm btn-flat" href="{{ route('estimations.print', $estimation->es_id) }}"><i class="fas fa-print"></i> Print</a>
            <a class="btn btn-dark btn-sm btn-flat" href="{{ route('estimations.index') }}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
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
                                <td>Estimation Date</td>
                                <td style="color:#f50303"><b>{{ \Carbon\Carbon::parse($estimation->es_date)->format('d/m/Y') }}</b></td>
                            </tr>
                            <tr>
                                <td>Estimation No</td>
                                <td style="color:#1d91be"><b>{{ $estimation->es_vno }}</b></td>
                            </tr>
                            <tr>
                                <td>User</td>
                                <td><b>{{ $estimation->user?->user_name ?? '-' }}</b></td>
                            </tr>
                            <tr>
                                <td>Account Effect</td>
                                <td>
                                    @if($estimation->es_account_effect)
                                        <span class="badge badge-success">Affected</span>
                                    @else
                                        <span class="badge badge-secondary">No Effect</span>
                                    @endif
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
                                <td>Customer Name</td>
                                <td>
                                    <b>
                                        @if($estimation->customer)
                                            <a href="{{ route('customers.show', $estimation->customer->id) }}">{{ $estimation->customer->customer }}</a>
                                        @else
                                            -
                                        @endif
                                    </b>
                                </td>
                            </tr>
                            <tr>
                                <td>Customer Phone No</td>
                                <td><b>{{ $estimation->customer?->phone ?? '-' }}</b></td>
                            </tr>
                            <tr>
                                <td>Customer Address</td>
                                <td><b>{{ $estimation->customer?->address ?? '-' }}</b></td>
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
            <table id="estimation_table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th style="width: 230px;">Product</th>
                        <th>Unit Price</th>
                        <th>Qty</th>
                        <th>Total Before Discount</th>
                        <th>Discount(Amt)</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($estimationDetails as $estimationDetail)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                @if($estimationDetail->product)
                                    <a href="{{ route('products.show', $estimationDetail->product->id) }}">
                                        {{ $estimationDetail->product->product }}
                                    </a>
                                @else
                                    <span class="text-muted">Missing Product</span>
                                @endif
                            </td>
                            <td>{{ number_format((float) $estimationDetail->esd_uprice, 2) }}</td>
                            <td>{{ $estimationDetail->esd_itemqty . ' ' . $estimationDetail->esd_unit }}</td>
                            <td>{{ number_format((float) $estimationDetail->esd_price, 2) }}</td>
                            <td>{{ number_format((float) $estimationDetail->esd_adisc, 2) . ' (' . $estimationDetail->esd_pdisc . '%)' }}</td>
                            <td>{{ number_format((float) $estimationDetail->esd_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No estimation details found</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr style="font-weight: bold; background-color: #f5f5f5;">
                        <td colspan="4" class="text-right">Total</td>
                        <td class="text-right">Rs. {{ number_format($total_before_discount, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($discount_amount, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($total_after_discount, 2) }}</td>
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
                            <th><span class="text-success">Rs. <label>{{ number_format((float) $estimation->es_amount_payable, 2) }}</label></span></th>
                        </tr>
                        <tr>
                            <th>Round Off</th>
                            <th>Rs. <label>{{ number_format((float) $estimation->es_round, 2) }}</label></th>
                        </tr>
                        @if($estimation->es_account_effect)
                        <tr>
                            <th>Payment Mode</th>
                            <th>{{ $estimation->banking->bk_bank ?? '-' }}</th>
                        </tr>
                        <tr>
                            <th>Amount Paid</th>
                            <th>Rs. <label>{{ number_format((float) $estimation->es_amount_paid, 2) }}</label></th>
                        </tr>
                        <tr>
                            <th>Balance</th>
                            <th>Rs. <label>{{ number_format((float) $estimation->es_balance, 2) }}</label></th>
                        </tr>
                        <tr>
                            <th>Due Date</th>
                            <th>{{ $estimation->es_due_date ? \Carbon\Carbon::parse($estimation->es_due_date)->format('d-m-Y') : '-' }}</th>
                        </tr>
                        @endif
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
