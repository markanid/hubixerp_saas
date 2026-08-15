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
                    <li class="breadcrumb-item">
                        <a href="{{ route('profile.dashboard') }}">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('purchases.index') }}">Purchase</a>
                    </li>
                    <li class="breadcrumb-item active">{{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
@php
    $purchaseDetails = collect($purchase->purchaseDetails ?? []);

    $showBatch = $purchaseDetails->contains(fn ($detail) => !empty($detail->batch_no));
    $showStockMrp = $purchaseDetails->contains(fn ($detail) => (float) ($detail->batch_mrp ?? 0) > 0);
    $showInventoryLot = $showBatch || $showStockMrp;

    $total_before_discount = $purchaseDetails->sum(fn ($detail) => (float) $detail->pud_price);
    $discount_amount = $purchaseDetails->sum(fn ($detail) => (float) $detail->pud_adisc);
    $total_after_discount = $purchaseDetails->sum(fn ($detail) => (float) $detail->pud_total);
    $taxable_value = $purchaseDetails->sum(fn ($detail) => (float) $detail->pud_total - (float) $detail->pud_gst);
    $gst_value = $purchaseDetails->sum(fn ($detail) => (float) $detail->pud_gst);

    $isCancelled = $is_cancelled ?? false;
    $taxType = strtolower((string) ($taxType ?? 'gst'));
    $taxLabel = $taxLabel ?? ($taxType === 'vat' ? 'VAT' : 'GST');
    $showTax = (bool) ($collectTax ?? true) && (string) $purchase->pu_type !== '2';
    $purchaseItemLabels = collect($purchaseItemLabels ?? []);
@endphp

<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa fa-th"></i> {{ $page_title }}
        </h3>

        <div class="card-tools">
            @can('purchase.create')
            <a class="btn btn-primary btn-sm btn-flat" href="{{ route('purchases.create') }}">
                <i class="fa fa-plus-circle"></i> Create New
            </a>
            @endcan

            @if(!$isCancelled)
                @can('purchase.update')
                <a class="btn btn-info btn-sm btn-flat" href="{{ route('purchases.edit', $purchase->pu_id) }}">
                    <i class="fas fa-edit"></i> Edit
                </a>
                @endcan
            @endif

            <a class="btn btn-warning btn-sm btn-flat" href="{{ route('purchases.print', $purchase->pu_id) }}">
                <i class="fas fa-print"></i> Print
            </a>

            <a class="btn btn-dark btn-sm btn-flat" href="{{ route('purchases.index') }}">
                <i class="fas fa-arrow-alt-circle-left"></i> Back
            </a>
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
                                    <td>Purchase Date</td>
                                    <td style="color:#f50303">
                                        <b>{{ \Carbon\Carbon::parse($purchase->pu_date)->format('d/m/Y') }}</b>
                                    </td>
                                </tr>

                                <tr>
                                    <td>Purchase No</td>
                                    <td style="color:#1d91be">
                                        <b>{{ $purchase->pu_vno }}</b>
                                    </td>
                                </tr>

                                <tr>
                                    <td>Bill No</td>
                                    <td>
                                        <b>{{ $purchase->pu_bill_number }}</b>
                                    </td>
                                </tr>

                                <tr>
                                    <td>Bill Type</td>
                                    <td>
                                        <b>{{ (string) $purchase->pu_type === '2' ? '6B' : 'B2B' }}</b>
                                    </td>
                                </tr>

                                @if($purchase->pu_due_date)
                                    <tr>
                                        <td>Payment Due Date</td>
                                        <td>
                                            <b>{{ \Carbon\Carbon::parse($purchase->pu_due_date)->format('d/m/Y') }}</b>

                                            @if($purchase->dueBalance() > 0 && \Carbon\Carbon::parse($purchase->pu_due_date)->lte(now()))
                                                <span class="badge badge-warning">Payment Due</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
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
                                    <td>Supplier Name</td>
                                    <td>
                                        <b>
                                            @if($purchase->vendor)
                                                <a href="{{ route('vendors.show', $purchase->vendor->id) }}">{{ $purchase->vendor->cp_name }}</a>
                                            @else
                                                -
                                            @endif
                                        </b>
                                    </td>
                                </tr>

                                <tr>
                                    <td>Supplier Phone No</td>
                                    <td>
                                        <b>{{ $purchase->vendor?->cp_phone ?? '-' }}</b>
                                    </td>
                                </tr>

                                <tr>
                                    <td>User</td>
                                    <td>
                                        <b>{{ $purchase->user?->user_name ?? '-' }}</b>
                                    </td>
                                </tr>
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
            <table id="purchase_table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product Code</th>
                        <th style="width: 230px;">Product</th>
                        <th>HSN Code</th>
                        <th>Unit Price</th>
                        <th>Qty</th>
                        <th>Free</th>

                        @if($showInventoryLot)
                            <th>{{ $showBatch ? 'Batch / Expiry / MRP' : 'MRP / Sale Price' }}</th>
                        @endif

                        <th>Total<br>Before<br>Discount</th>
                        <th>Discount</th>
                        <th>Total</th>
                        @if($showTax)
                            <th>Taxable<br>Value</th>
                            <th>{{ $taxLabel }}</th>
                        @endif
                        <th class="text-center">Print Codes</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($purchaseDetails as $purchaseDetail)
                        <tr>
                            <td>{{ $loop->iteration }}</td>

                            <td>{{ $purchaseDetail->pud_itemid ?: '-' }}</td>

                            <td>
                                @if($purchaseDetail->product)
                                <a href="{{ route('products.show', $purchaseDetail->product->id) }}">
                                    {{ $purchaseDetail->product->product }}
                                </a>
                                @else
                                    <span class="text-muted">Missing Product</span>
                                @endif
                            </td>
                            <td>{{ $purchaseDetail->pud_hsn }}</td>
                            <td>{{ $purchaseDetail->pud_uprice }}</td>
                            <td>{{ $purchaseDetail->pud_itemqty . ' ' . $purchaseDetail->pud_unit }}</td>
                            <td>{{ $purchaseDetail->pud_free . ' ' . $purchaseDetail->pud_unit }}</td>

                            @if($showInventoryLot)
                                <td>
                                    @if($showBatch)
                                        {{ $purchaseDetail->batch_no ?: '-' }}<br>
                                        {{ $purchaseDetail->expiry_date?->format('m/Y') ?: 'No expiry' }}<br>
                                    @endif
                                    MRP {{ number_format($purchaseDetail->batch_mrp ?? 0, 2) }}
                                    @if(!$showBatch)
                                        <br>Sale {{ number_format($purchaseDetail->lot_sale_price ?? 0, 2) }}
                                        {{-- <br>Margin {{ number_format($purchaseDetail->lot_margin_amount ?? 0, 2) }} ({{ number_format($purchaseDetail->lot_margin_percentage ?? 0, 2) }}%) --}}
                                    @endif
                                </td>
                            @endif

                            <td>{{ $purchaseDetail->pud_price }}</td>
                            <td>{{ $purchaseDetail->pud_adisc . ' (' . $purchaseDetail->pud_pdisc . '%)' }}</td>
                            <td>{{ $purchaseDetail->pud_total }}</td>
                            @if($showTax)
                                <td>{{ $purchaseDetail->pud_total - $purchaseDetail->pud_gst }}</td>
                                <td>{{ $purchaseDetail->pud_gst . ' (' . ($purchaseDetail->product?->gst ?? 0) . '%)' }}</td>
                            @endif
                            <td class="text-center text-nowrap">
                                @if($purchaseDetail->product)
                                    @php
                                        $itemLabel = $purchaseItemLabels->get($purchaseDetail->pud_id);
                                        $barcodeUrl = $itemLabel
                                            ? route('products.inventory-label.barcode.print', [$purchaseDetail->product, $itemLabel])
                                            : route('products.barcode', $purchaseDetail->product->id);
                                        $qrUrl = $itemLabel
                                            ? route('products.inventory-label.qr.print', [$purchaseDetail->product, $itemLabel])
                                            : route('products.qr', $purchaseDetail->product->id);
                                        $codeScope = $itemLabel ? strtoupper($itemLabel->label_type) : 'Legacy';
                                    @endphp
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Print {{ $purchaseDetail->product->product }} codes">
                                        <a href="{{ $barcodeUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary" title="Print {{ $codeScope }} barcode">
                                            <i class="fas fa-barcode"></i> Barcode
                                        </a>
                                        <a href="{{ $qrUrl }}" target="_blank" rel="noopener" class="btn btn-outline-dark" title="Print {{ $codeScope }} QR code">
                                            <i class="fas fa-qrcode"></i> QR
                                        </a>
                                    </div>
                                    {{-- <div class="mt-1"><span class="badge badge-light border">{{ $codeScope }}</span></div> --}}
                                @else
                                    <span class="text-muted">Unavailable</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($showInventoryLot ? 10 : 9) + ($showTax ? 2 : 0) + 2 }}" class="text-center">
                                No purchase details found
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                <tfoot>
                    <tr style="font-weight: bold; background-color: #f5f5f5;">
                        <td colspan="{{ $showInventoryLot ? 8 : 7 }}" class="text-right">Total</td>
                        <td class="text-right">Rs. {{ number_format($total_before_discount, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($discount_amount, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($total_after_discount, 2) }}</td>
                        @if($showTax)
                            <td class="text-right">Rs. {{ number_format($taxable_value, 2) }}</td>
                            <td class="text-right">Rs. {{ number_format($gst_value, 2) }}</td>
                        @endif
                        <td></td>
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
                                <span class="text-success">
                                    Rs. <label>{{ number_format((float) $purchase->pu_amount_payable, 2) }}</label>
                                </span>
                            </th>
                        </tr>

                        <tr>
                            <th>Round Off</th>
                            <th>
                                Rs. <label>{{ number_format((float) $purchase->pu_round, 2) }}</label>
                            </th>
                        </tr>
                        <tr>
                            <th>Payment Mode</th>
                            <th>{{ $purchase->banking?->bk_bank ?? '-' }}</th>
                        </tr>
                        <tr>
                            <th>Amount Paid</th>
                            <th>Rs. {{ number_format((float) $purchase->pu_amount_paid, 2) }}</th>
                        </tr>
                        <tr>
                            <th>Balance</th>
                            <th>Rs. {{ number_format((float) $purchase->pu_balance, 2) }}</th>
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
    $(document).ready(function () {
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