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
                    <li class="breadcrumb-item"><a href="{{route('purchases.index')}}">Purchase</a></li>
                    <li class="breadcrumb-item active">{{$page_title}}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-cart-arrow-down"></i> {{$page_title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('purchases.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
    </div>  
    @if ($errors->any())
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validation Error');
            });
        </script>
    @endif
    <form id="addPurchase" method="post" action="{{ route('purchases.update')}}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="user_id" name="pu_user" value="{{ session()->get('id') }}">
        @php
            $isEdit = isset($purchase);
            $collectTax = (bool) ($collectTax ?? true);
            $taxLabel = $taxLabel ?? 'GST';
            $allowedPurchaseTypes = $allowedPurchaseTypes ?? ['1', '2'];
            $purchaseTypeOptions = ['1' => 'B2B', '2' => '6B'];
        @endphp
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Purchase No.<sup>*</sup></label>
                        <input type="text" name="pu_vno" id="voucher_no" class="form-control" value="{{ old('pu_vno', $purchase->pu_vno ?? $voucher_no) }}" readonly tabindex="-1">
                        @if ($errors->has('pu_vno'))
                          <span class="text-danger">{{ $errors->first('pu_vno') }}</span>
                        @endif
                        <input type="hidden" id="purchase_items" name="purchase_items">
                        @if ($errors->has('purchase_items'))
                          <span class="text-danger">{{ $errors->first('purchase_items') }}</span>
                        @endif

                        <input type="hidden" id="edit_mode" value="{{ isset($purchase) ? 'true' : 'false' }}">
                        <input type="hidden" id="purchase_id" name="pu_id" value="{{ $purchase->pu_id ?? '' }}">
                        <input type="hidden" id="existing_items" value='@json($purchase->purchaseDetails ?? [])'>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Date<sup>*</sup></label>
                        <div class="input-group date" id="reservationdate" data-target-input="nearest">
                            <input type="text" name="pu_date" id="purchase_date" class="form-control datetimepicker-input" data-target="#reservationdate" value="{{ old('pu_date', isset($purchase) ? \Carbon\Carbon::parse($purchase->pu_date)->format('d/m/Y') : now()->format('d/m/Y')) }}" />
                            <div class="input-group-append" data-target="#reservationdate" data-toggle="datetimepicker">
                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                            </div>
                            @if ($errors->has('pu_date'))
                              <span class="text-danger">{{ $errors->first('pu_date') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Bill Type</label>
                        <select id="pu_type" name="pu_type" class="form-control" {{ $isEdit ? 'disabled' : '' }}>
                            @foreach($allowedPurchaseTypes as $type)
                                @continue(!isset($purchaseTypeOptions[(string) $type]))
                                <option value="{{ $type }}" {{ (!$isEdit && (string) ($pu_type ?? 1) === (string) $type) || ($isEdit && (string) $purchase->pu_type === (string) $type) ? 'selected' : '' }}>{{ $purchaseTypeOptions[(string) $type] }}</option>
                            @endforeach
                        </select>
                        {{-- If disabled, send value via hidden field --}}
                        @if($isEdit)
                            <input type="hidden" name="pu_type" value="{{ $purchase->pu_type }}">
                        @endif
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Supplier Bill No.</label>
                        <input type="text" name="pu_bill_number" id="bill_number" class="form-control" value="{{ old('pu_bill_number', $purchase->pu_bill_number ?? '') }}">
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Supplier</label>
                        <input type="hidden" name="pu_vendor" id="vendor_id" value="{{ old('pu_vendor', $purchase->pu_vendor ?? '') }}">
                        <select id="vendor" class="select2 form-control" {{ $isEdit ? 'disabled' : '' }} style="width: 100%">
                            @if($isEdit && isset($purchase->vendor))
                                <option value="{{ $purchase->vendor->id }}" selected>{{ $purchase->vendor->cp_name }}</option>
                            @endif
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Item</label>
                        <select id="product" class="select2 form-control" style="width: 100%"></select>
                        <input type="hidden" id="product_id">
                        <input type="hidden" id="product_code">
                        <input type="hidden" id="product_name">
                        <input type="hidden" id="p_price">
                        <input type="hidden" id="gst">
                        <input type="hidden" id="unit_qty">
                        <input type="hidden" id="unit">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>HSN Code</label>
                        <input type="text" id="hsn_code" class="form-control" readonly tabindex="-1">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Purchase Price</label>
                        <input type="text" id="purchase_price" class="form-control" readonly tabindex="-1">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Unit</label>
                        <select id="item_unit" class="form-control">
                            <option value="">Select Unit</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Qty</label>
                        <input type="text" id="quantity" class="form-control">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Free</label>
                        <input type="text" id="free_quantity" class="form-control" value="0">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Unit Price</label>
                        <input type="text" id="unit_price" class="form-control">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Discount</label>
                        <input type="text" id="amount_discount" class="form-control" value="0">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Discount(%)</label>
                        <input type="text" id="percentage_discount" class="form-control" value="0">
                    </div>
                </div>
                @if($batchMode)
                <div class="col-md-2 batch-fields" style="display:none">
                    <div class="form-group">
                        <label>Batch No.</label>
                        <input type="text" id="batch_no" class="form-control">
                    </div>
                </div>
                <div class="col-md-2 batch-fields" style="display:none">
                    <div class="form-group">
                        <label>Expiry (MM/YYYY)</label>
                        <input type="text" id="expiry_date" class="form-control" placeholder="12/2026">
                    </div>
                </div>
                @endif
                @if($batchMode || $mrpMode)
                <div class="col-md-2 {{ $batchMode ? 'batch-fields' : 'mrp-fields' }}" style="{{ $batchMode ? 'display:none' : '' }}">
                    <div class="form-group">
                        <label>MRP</label>
                        <input type="number" min="0" step="0.01" id="batch_mrp" class="form-control">
                    </div>
                </div>
                @endif
                @if($mrpMode)
                <div class="col-md-2 mrp-fields">
                    <div class="form-group">
                        <label>Margin Amount</label>
                        <input type="number" min="0" step="0.01" id="lot_margin_amount" class="form-control">
                    </div>
                </div>
                <div class="col-md-2 mrp-fields">
                    <div class="form-group">
                        <label>Margin (%)</label>
                        <input type="number" min="0" max="100" step="0.01" id="lot_margin_percentage" class="form-control">
                    </div>
                </div>
                <div class="col-md-2 mrp-fields">
                    <div class="form-group">
                        <label>Sale Price</label>
                        <input type="number" min="0" step="0.01" id="lot_sale_price" class="form-control" readonly tabindex="-1">
                    </div>
                </div>
                @endif
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div align="center">
                        <a class="btn btn-success btn-flat addBtn" tabindex="0"><i class="fas fa-arrow-alt-circle-down"></i> Add Item</a>
                        <button type="reset" class="btn btn-default btn-flat"><i class="fa fa-undo"></i>  Reset</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12 table-responsive">
                    <table id="purchase_table" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>HSN Code</th>
                                <th>Unit Price</th>
                                <th>Qty</th>
                                <th>Free</th>
                                @if($batchMode)<th>Batch / Expiry / MRP</th>@endif
                                @if($mrpMode)<th>MRP / Sale Price / Margin</th>@endif
                                <th>Total Before Discount</th>
                                <th>Discount(Amt)</th>
                                <th>Total</th>
                                <th class="purchase-tax-column">Taxable Value</th>
                                <th class="purchase-tax-column">{{ $taxLabel }}</th>
                                <th>Delete</th> 
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr style="font-weight: bold; background-color: #f5f5f5;">
                                <td colspan="{{ ($batchMode || $mrpMode) ? 7 : 6 }}" class="text-right">Total</td>
                                <td id="footer_total_before_discount" class="text-right">0.00</td>
                                <td id="footer_discount_amount" class="text-right">0.00</td>
                                <td id="footer_total_after_discount" class="text-right">0.00</td>
                                <td id="footer_taxable_value" class="text-right purchase-tax-column">0.00</td>
                                <td id="footer_gst_value" class="text-right purchase-tax-column">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="row d-flex">
                <div class="col-md-6 justify-content-center">
                </div>
        
                <!-- Amount Payable on the Right -->
                <div class="col-md-6 d-flex">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tr>
                                <th>Amount Payable</th>
                                <th>
                                    <input type="hidden" name="pu_amount" readonly id="amount" value="{{ old('pu_amount', isset($purchase) ? $purchase->pu_amount : '') }}">
                                    <input type="hidden" name="pu_gst" readonly id="totalgst" value="{{ old('pu_gst', isset($purchase) ? $purchase->pu_gst : '') }}">
                                    <input type="hidden" name="pu_discount" readonly id="discount" value="{{ old('pu_discount', isset($purchase) ? $purchase->pu_discount : '') }}">
                                    <input type="hidden" name="pu_grandtotal" readonly id="grand_total" value="{{ old('pu_grandtotal', isset($purchase) ? $purchase->pu_grandtotal : '') }}">
                                    <input type="hidden" name="pu_amount_payable" readonly id="amount_payable" value="{{ old('pu_amount_payable', isset($purchase) ? $purchase->pu_amount_payable : '') }}">
                                    <font color="green">Rs. <label id="amount_payable1">{{ old('pu_amount_payable', isset($purchase) ? $purchase->pu_amount_payable : '') }}</label></font>
                                </th>
                            </tr>
                            <tr>
                                <th>Round Off</th>
                                <th>
                                    <input type="hidden" class="form-control" name="pu_round" id="round_off" value="{{ old('pu_round', isset($purchase) ? $purchase->pu_round : '') }}">
                                    Rs. <label id="round_off1">{{ old('pu_round', isset($purchase) ? $purchase->pu_round : '') }}</label>
                                </th>
                            </tr>
                            @if (!$isEdit)
                            <tr>
                                <th>Payment Mode</th>
                                <th>
                                    <div class="form-group">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fa-solid fa-building-columns"></i>
                                                </span>
                                            </div>
                                            <select name="pu_paymode" id="pu_paymode" class="form-control" accesskey="p">
                                                <option value="">-- Select Payment Mode --</option>
                                                @foreach ($banks as $row)
                                                    <option value="{{ $row['bk_id'] }}" {{ (old('pu_paymode', $purchase->pu_paymode ?? '') == $row['bk_id']) ? 'selected' : '' }}>
                                                        {{ $row['bk_bank'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @if ($errors->has('pu_paymode'))
                                            <span class="text-danger">{{ $errors->first('pu_paymode') }}</span>
                                        @endif
                                    </div>
                                </th>
                            </tr>
                            @else
                                <!-- Hidden input to preserve pu_paymode in edit form -->
                                <input type="hidden" name="pu_paymode" value="{{ old('pu_paymode', $purchase->pu_paymode ?? '') }}">
                            @endif
                            @if(!$isEdit)
                                <tr>
                                    <th>Amount Paid</th>
                                    <th>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa-solid fa-money-bill-1-wave"></i></span>
                                            </div>
                                            <input type="input" class="form-control" name="pu_amount_paid" id="amount_paid" value="{{ old('pu_amount_paid', isset($purchase) ? $purchase->pu_amount_paid : 0) }}">
                                        </div>
                                    </th>
                                </tr>
                                <tr>
                                    <th>Balance to Pay</th>
                                    <th>
                                        <input type="hidden" name="pu_balance" id="balance_to_pay" value="{{ old('pu_balance', isset($purchase) ? $purchase->pu_balance : '') }}">
                                        Rs. <label id="balance_to_pay1">{{ old('pu_balance', isset($purchase) ? $purchase->pu_balance : '') }}</label>
                                    </th>
                                </tr>
                            @else
                                <!-- Hidden inputs to preserve values on submit -->
                                <input type="hidden" name="pu_amount_paid" id="amount_paid" value="{{ old('pu_amount_paid', isset($purchase) ? $purchase->pu_amount_paid : 0) }}">
                                <input type="hidden" name="pu_balance" id="balance_to_pay" value="{{ old('pu_balance', isset($purchase) ? $purchase->pu_balance : '') }}">
                            @endif
                            <tr id="payment_due_days_row">
                                <th>Payment Due Days</th>
                                <th>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa-regular fa-calendar-check"></i></span>
                                        </div>
                                        <input type="number" min="0" max="3650" step="1" class="form-control" name="pu_due_days" id="payment_due_days" value="{{ old('pu_due_days', $purchase->pu_due_days ?? '') }}" placeholder="e.g. 30">
                                    </div>
                                    <small class="text-muted" id="payment_due_date_text"></small>
                                    @if ($errors->has('pu_due_days'))
                                        <span class="text-danger">{{ $errors->first('pu_due_days') }}</span>
                                    @endif
                                </th>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer" align="center">
            <button type="submit" id="savePurchase" class="btn btn-primary  btn-flat"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
</div>
@endsection
@include('partials.vendor-modal')
@include('partials.product-modal')
<meta name="csrf-token" content="{{ csrf_token() }}">

@section('scripts')
<script>
    var purchaseSearchRoute = "{{ route('purchase.search') }}";
    var vendorAddRoute      = "{{ route('add.vendor') }}";
    var productAddRoute     = "{{ route('add.product') }}";
    var productNewCodeRoute = "{{ route('product.new.code') }}";
    var getVoucherAddRoute  = "{{ route('purchases.getVoucher') }}";
    window.batchInventoryMode = @json($batchMode);
    window.mrpInventoryMode = @json($mrpMode);
    window.inventoryMode = @json($inventoryMode);
    window.purchaseCollectTax = @json($collectTax);
</script>

<script src="{{ asset('js/purchase.js') }}?v={{ filemtime(public_path('js/purchase.js')) }}"></script>
@endsection