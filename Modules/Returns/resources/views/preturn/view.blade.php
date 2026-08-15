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
                    <li class="breadcrumb-item"><a href="{{ route('purchase-returns.index') }}">Purchase-Return</a></li>
                    <li class="breadcrumb-item active">{{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
@php
    $returnDetails = collect($return->returnDetails ?? []);
    $showBatch = $returnDetails->contains(fn ($detail) => !empty($detail->batch_no));
    $total_after_discount = $returnDetails->sum(fn ($detail) => (float) $detail->prd_total);
    $taxable_value = $returnDetails->sum(fn ($detail) => (float) $detail->prd_total - (float) $detail->prd_gst);
    $gst_value = $returnDetails->sum(fn ($detail) => (float) $detail->prd_gst);
    $taxType = strtolower((string) ($taxType ?? 'gst'));
    $taxLabel = $taxLabel ?? ($taxType === 'vat' ? 'VAT' : 'GST');
    $showTax = round((float) $gst_value, 2) > 0 || round((float) ($return->pr_gst ?? 0), 2) > 0;
@endphp

<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-th"></i> {{ $page_title }}</h3>
        <div class="card-tools">
            <a class="btn btn-primary btn-sm btn-flat" href="{{ route('purchase-returns.create') }}"><i class="fa fa-plus-circle"></i> Create New</a>
            @if(!$is_cancelled)
                <a class="btn btn-info btn-sm btn-flat" href="{{ route('purchase-returns.edit', $return->pr_id) }}"><i class="fas fa-edit"></i> Edit</a>
            @endif
            <a class="btn btn-warning btn-sm btn-flat" href="{{ route('purchase-returns.print', $return->pr_id) }}"><i class="fas fa-print"></i> Print</a>
            <a class="btn btn-dark btn-sm btn-flat" href="{{ route('purchase-returns.index') }}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
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
                                <td>Return Date</td>
                                <td style="color:#f50303"><b>{{ \Carbon\Carbon::parse($return->pr_date)->format('d/m/Y') }}</b></td>
                            </tr>
                            <tr>
                                <td>Return No</td>
                                <td style="color:#1d91be"><b>{{ $return->pr_vno }}</b></td>
                            </tr>
                            <tr>
                                <td>Purchase Bill No</td>
                                <td><b>{{ $return->pr_pvno ?? '-' }}</b></td>
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
                                <td>Supplier Name</td>
                                <td>
                                    <b>
                                        @if($return->vendor)
                                            <a href="{{ route('vendors.show', $return->vendor->id) }}">{{ $return->vendor->cp_name }}</a>
                                        @else
                                            -
                                        @endif
                                    </b>
                                </td>
                            </tr>
                            <tr>
                                <td>Supplier Phone No</td>
                                <td><b>{{ $return->vendor?->cp_phone ?? '-' }}</b></td>
                            </tr>
                            <tr>
                                <td>User</td>
                                <td><b>{{ $return->user?->user_name ?? '-' }}</b></td>
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
            <table id="return_table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th style="width: 230px;">Product</th>
                        <th>HSN Code</th>
                        @if($showBatch)
                            <th>Batch</th>
                        @endif
                        <th>Unit Price</th>
                        <th>Qty</th>
                        <th>Total</th>
                        @if($showTax)
                            <th>Taxable Value</th>
                            <th>{{ $taxLabel }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($returnDetails as $returnDetail)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                @if($returnDetail->product)
                                    <a href="{{ route('products.show', $returnDetail->product->id) }}">{{ $returnDetail->product->product }}</a>
                                @else
                                    <span class="text-muted">Missing Product</span>
                                @endif
                            </td>
                            <td>{{ $returnDetail->prd_hsn }}</td>
                            @if($showBatch)
                                <td>{{ $returnDetail->batch_no ?: '-' }}</td>
                            @endif
                            <td>{{ number_format((float) $returnDetail->prd_uprice, 2) }}</td>
                            <td>{{ $returnDetail->prd_itemqty . ' ' . $returnDetail->prd_unit }}</td>
                            <td>{{ number_format((float) $returnDetail->prd_total, 2) }}</td>
                            @if($showTax)
                                <td>{{ number_format((float) $returnDetail->prd_total - (float) $returnDetail->prd_gst, 2) }}</td>
                                <td>{{ number_format((float) $returnDetail->prd_gst, 2) . ' (' . ($returnDetail->product?->gst ?? 0) . '%)' }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 6 + ($showBatch ? 1 : 0) + ($showTax ? 2 : 0) }}" class="text-center">
                                No purchase return details found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr style="font-weight: bold; background-color: #f5f5f5;">
                        <td colspan="{{ 5 + ($showBatch ? 1 : 0) }}" class="text-right">Total</td>
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
                            <th><span class="text-success">Rs. <label>{{ number_format((float) $return->pr_amount_payable, 2) }}</label></span></th>
                        </tr>
                        <tr>
                            <th>Round Off</th>
                            <th>Rs. <label>{{ number_format((float) $return->pr_round, 2) }}</label></th>
                        </tr>
                        <tr>
                            <th>Payment Mode</th>
                            <th>{{ $return->banking?->bk_bank ?? '-' }}</th>
                        </tr>
                        <tr>
                            <th>Amount Paid</th>
                            <th>Rs. {{ number_format((float) $return->pr_amount_paid, 2) }}</th>
                        </tr>
                        <tr>
                            <th>Balance</th>
                            <th>Rs. {{ number_format((float) $return->pr_balance, 2) }}</th>
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
