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
                    <li class="breadcrumb-item"><a href="{{route('purchase-returns.index')}}">Purchase-Return</a></li>
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
        <h3 class="card-title"><i class="fas fa-undo-alt"></i> {{$page_title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('purchase-returns.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
    </div>
    @if ($errors->any())
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validation Error');
            });
        </script>
    @endif
    <form id="addPurchaseReturn" method="post" action="{{ route('purchase-returns.update')}}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="user_id" name="pr_user" value="{{ session()->get('id') }}">
        @php
            $isEdit = isset($return);
        @endphp
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Purchase-Return No.<sup>*</sup></label>
                        <input type="text" name="pr_vno" id="voucher_no" class="form-control" value="{{ old('pr_vno', $return->pr_vno ?? $voucher_no) }}" readonly>
                        @if ($errors->has('pr_vno'))
                            <span class="text-danger">{{ $errors->first('pr_vno') }}</span>
                        @endif
                        <input type="hidden" id="return_items" name="return_items">
                        @if ($errors->has('return_items'))
                            <span class="text-danger">{{ $errors->first('return_items') }}</span>
                        @endif

                        <input type="hidden" id="edit_mode" value="{{ isset($return) ? 'true' : 'false' }}">
                        <input type="hidden" id="return_id" name="pr_id" value="{{ $return->pr_id ?? '' }}">
                        <input type="hidden" id="existing_items" value='@json($return->returnDetails ?? [])'>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Date<sup>*</sup></label>
                        <div class="input-group date" id="reservationdate" data-target-input="nearest">
                            <input type="text" name="pr_date" id="return_date" tabindex="1" class="form-control datetimepicker-input" data-target="#reservationdate" value="{{ old('pr_date', isset($return) ? \Carbon\Carbon::parse($return->pr_date)->format('d/m/Y') : now()->format('d/m/Y')) }}" />
                            <div class="input-group-append" data-target="#reservationdate" data-toggle="datetimepicker">
                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                            </div>
                            @if ($errors->has('pr_date'))
                                <span class="text-danger">{{ $errors->first('pr_date') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Purchase Bill No.</label>
                        <input type="text" name="pr_pvno" id="bill_number" tabindex="2" class="form-control" value="{{ old('pr_pvno', $return->pr_pvno ?? '') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Supplier</label>
                        <input type="hidden" name="pr_vendor" id="vendor_id" value="{{ old('pr_vendor', $return->pr_vendor ?? '') }}">
                        <select id="vendor" tabindex="3" class="select2 form-control" {{ $isEdit ? 'disabled' : '' }} style="width: 100%">
                            @if($isEdit && isset($return->vendor))
                                <option value="{{ $return->vendor->id }}" selected>{{ $return->vendor->cp_name }} - {{ $return->vendor->cp_phone }}</option>
                            @endif
                        </select>
                    </div>
                </div>
                <input type="hidden" id="vendor_ph" value="{{ old('cp_phone', $return->vendor->cp_phone ?? '') }}">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Item</label>
                        <select id="product" tabindex="4" class="select2 form-control" style="width: 100%"></select>
                        <input type="hidden" id="product_id">
                        <input type="hidden" id="product_code">
                        <input type="hidden" id="product_name">
                        <input type="hidden" id="p_price">
                        <input type="hidden" id="gst">
                        <input type="hidden" id="current_stock">
                        <input type="hidden" id="unit_qty">
                        <input type="hidden" id="unit">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>HSN Code</label>
                        <input type="text" id="hsn_code" class="form-control" readonly>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Purchase Price</label>
                        <input type="text" id="purchase_price" class="form-control" readonly>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Unit</label>
                        <select id="item_unit" tabindex="5" class="form-control">
                            <option value="">Select Unit</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>In Stock</label>
                        <input type="text" id="in_stock" class="form-control" readonly>
                    </div>
                </div>
                @if($batchMode)
                <div class="col-md-4" id="return_batch_group" style="display:none">
                    <div class="form-group">
                        <label>Batch</label>
                        <select id="stock_batch_id" class="form-control select2" style="width:100%">
                            <option value="">Select batch</option>
                        </select>
                    </div>
                </div>
                @endif
                @if($mrpMode)
                <div class="col-md-5" id="return_mrp_lot_group" style="display:none">
                    <div class="form-group">
                        <label>Purchase Lot / MRP</label>
                        <select id="mrp_stock_lot_id" class="form-control select2" style="width:100%">
                            <option value="">Select Stock / MRP</option>
                        </select>
                    </div>
                </div>
                @endif
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Qty</label>
                        <input type="text" id="quantity" tabindex="6" class="form-control">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Unit Price</label>
                        <input type="text" id="unit_price" tabindex="7" class="form-control">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div align="center">
                        <a class="btn btn-success btn-flat addBtn" tabindex="8"><i class="fas fa-arrow-alt-circle-down"></i> Add Item</a>
                        <button type="reset" tabindex="9" class="btn btn-default btn-flat"><i class="fa fa-undo"></i> Reset</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12 table-responsive">
                    <table id="return_table" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>HSN Code</th>
                                <th>Unit Price</th>
                                <th>Qty</th>
                                @if($batchMode)<th>Batch</th>@endif
                                @if($mrpMode)<th>Purchase Lot / MRP</th>@endif
                                <th>Total</th>
                                <th>Taxable Value</th>
                                <th>GST</th>
                                <th>Delete</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr style="font-weight: bold; background-color: #f5f5f5;">
                                <td colspan="{{ ($batchMode || $mrpMode) ? 6 : 5 }}" class="text-right">Total</td>
                                <td id="footer_total" class="text-right">0.00</td>
                                <td id="footer_taxable_value" class="text-right">0.00</td>
                                <td id="footer_gst_value" class="text-right">0.00</td>
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
                <div class="col-md-6 d-flex">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tr>
                                <th>Amount Payable</th>
                                <th>
                                    <input type="hidden" name="pr_amount" readonly id="amount" value="{{ old('pr_amount', isset($return) ? $return->pr_amount : '') }}">
                                    <input type="hidden" name="pr_gst" readonly id="totalgst" value="{{ old('pr_gst', isset($return) ? $return->pr_gst : '') }}">
                                    <input type="hidden" name="pr_amount_payable" readonly id="amount_payable" value="{{ old('pr_amount_payable', isset($return) ? $return->pr_amount_payable : '') }}">
                                    <font color="green">Rs. <label id="amount_payable1">{{ old('pr_amount_payable', isset($return) ? $return->pr_amount_payable : '') }}</label></font>
                                </th>
                            </tr>
                            <tr>
                                <th>Round Off</th>
                                <th>
                                    <input type="hidden" class="form-control" name="pr_round" id="round_off" value="{{ old('pr_round', isset($return) ? $return->pr_round : '') }}">
                                    Rs. <label id="round_off1">{{ old('pr_round', isset($return) ? $return->pr_round : '') }}</label>
                                </th>
                            </tr>
                            @if(!$isEdit)
                            <tr>
                                <th>Payment Mode</th>
                                <th>
                                    @php($selectedPaymode = old('pr_paymode', $return->pr_paymode ?? ''))
                                    <div class="form-group">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fa-solid fa-building-columns"></i>
                                                </span>
                                            </div>
                                            <select name="pr_paymode" id="pr_paymode" class="form-control" accesskey="p" tabindex="10">
                                                <option value="">-- Select Payment Mode --</option>
                                                @foreach ($banks as $row)
                                                    <option value="{{ $row['bk_id'] }}" {{ (string) $selectedPaymode === (string) $row['bk_id'] ? 'selected' : '' }}>
                                                        {{ $row['bk_bank'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @if ($errors->has('pr_paymode'))
                                            <span class="text-danger">{{ $errors->first('pr_paymode') }}</span>
                                        @endif
                                    </div>
                                </th>
                            </tr>
                            @else
                                <input type="hidden" name="pr_paymode" value="{{ old('pr_paymode', $return->pr_paymode ?? '') }}">
                            @endif
                            @if(!$isEdit)
                                <tr>
                                    <th>Amount Paid</th>
                                    <th>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa-solid fa-money-bill-1-wave"></i></span>
                                            </div>
                                            <input type="input" class="form-control" name="pr_amount_paid" id="amount_paid" value="{{ old('pr_amount_paid', isset($return) ? $return->pr_amount_paid : 0) }}" tabindex="11">
                                        </div>
                                    </th>
                                </tr>
                                <tr>
                                    <th>Balance to Pay</th>
                                    <th>
                                        <input type="hidden" name="pr_balance" id="balance_to_pay" value="{{ old('pr_balance', isset($return) ? $return->pr_balance : '') }}">
                                        Rs. <label id="balance_to_pay1">{{ old('pr_balance', isset($return) ? $return->pr_balance : '') }}</label>
                                    </th>
                                </tr>
                            @else
                                <input type="hidden" name="pr_amount_paid" id="amount_paid" value="{{ old('pr_amount_paid', isset($return) ? $return->pr_amount_paid : 0) }}">
                                <input type="hidden" name="pr_balance" id="balance_to_pay" value="{{ old('pr_balance', isset($return) ? $return->pr_balance : '') }}">
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer" align="center">
            <button type="submit" id="savePurchaseReturn" tabindex="12" class="btn btn-primary btn-flat"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
</div>
@endsection

@include('partials.vendor-modal')
@include('partials.product-modal')
<meta name="csrf-token" content="{{ csrf_token() }}">

@section('scripts')
<script>
    window.batchInventoryMode = @json($batchMode);
    window.mrpInventoryMode = @json($mrpMode);
    window.inventoryMode = @json($inventoryMode);
    var purchaseReturnSearchRoute = "{{ route('purchase-returns.search') }}";
    var vendorAddRoute = "{{ route('add.vendor') }}";
    var productAddRoute = "{{ route('add.product') }}";
    var productNewCodeRoute = "{{ route('product.new.code') }}";
</script>
<script src="{{ asset('js/purchaseReturn.js') }}"></script>
@endsection