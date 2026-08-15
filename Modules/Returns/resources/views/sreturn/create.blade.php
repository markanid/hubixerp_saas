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
                    <li class="breadcrumb-item"><a href="{{route('sales-returns.index')}}">Sale-Return</a></li>
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
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('sales-returns.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
    </div>  
    @if ($errors->any())
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validation Error');
            });
        </script>
    @endif
    <form id="addSaleReturn" method="post" action="{{ route('sales-returns.update')}}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="user_id" name="pr_user" value="{{ session()->get('id') }}">
        @php
            $isEdit = isset($return);
            $taxType = strtolower((string) ($taxType ?? 'gst'));
            $isVat = $taxType === 'vat';
            $taxLabel = $taxLabel ?? ($isVat ? 'VAT' : 'GST');
        @endphp
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Sale-Return No.<sup>*</sup></label>
                        <input type="text" name="pr_vno" id="voucher_no" tabindex="1" class="form-control" value="{{ old('pr_vno', $return->pr_vno ?? $voucher_no) }}" readonly>
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
                            <input type="text" name="pr_date" id="return_date" tabindex="2" class="form-control datetimepicker-input" data-target="#reservationdate" value="{{ old('pr_date', isset($return) ? \Carbon\Carbon::parse($return->pr_date)->format('d/m/Y') : now()->format('d/m/Y')) }}" />
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
                        <label>Sale Bill No.</label>
                        <input type="text" name="pr_pvno" id="bill_number" tabindex="3" class="form-control" value="{{ old('pr_pvno', $return->pr_pvno ?? '') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Customer</label>
                        <input type="hidden" name="pr_vendor" id="customer_id" value="{{ old('pr_vendor', $return->pr_vendor ?? '') }}">
                        <select id="customer" tabindex="4" class="select2 form-control" {{ $isEdit ? 'disabled' : '' }} style="width: 100%">
                            @if($isEdit && isset($return->customer))
                                <option value="{{ $return->customer->id }}" selected>{{ $return->customer->customer }} - {{ $return->customer->phone }}</option>
                            @endif
                        </select>
                    </div>
                </div>
                <input type="hidden" id="phone" value="{{ old('phone', $return->customer->phone ?? '') }}">
                @if(!$isVat)
                <div class="col-md-4">
                    <div class="form-group">
                        <label>GST State</label>
                        <select name="pr_state_code" id="pr_state_code" class="form-control select2" style="width: 100%">
                            <option value="">-- Select State --</option>
                            @foreach($states as $state)
                                <option value="{{ $state->state_code }}"
                                    {{ old('pr_state_code', $return->pr_state_code ?? '') == $state->state_code ? 'selected' : '' }}>
                                    {{ $state->state_code }} - {{ $state->state_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label style="display:block;">IGST?</label>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="pr_is_igst" name="pr_is_igst" value="1" {{ old('pr_is_igst', $return->pr_is_igst ?? 0) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="pr_is_igst">Apply IGST</label>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Item</label>
                        <select id="product" tabindex="6" class="select2 form-control" style="width: 100%"></select>
                        <input type="hidden" id="product_id">
                        <input type="hidden" id="product_code">
                        <input type="hidden" id="product_name">
                        <input type="hidden" id="price">
                        <input type="hidden" id="gst">
                        <input type="hidden" id="unit_qty">
                        <input type="hidden" id="unit">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>HSN Code</label>
                        <input type="text" id="hsn_code" tabindex="7" class="form-control" readonly>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Unit</label>
                        <select id="item_unit" tabindex="8" class="form-control">
                            <option value="">Select Unit</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Qty</label>
                        <input type="text" id="quantity" tabindex="9" class="form-control">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Unit Price</label>
                        <input type="text" id="unit_price" tabindex="10" class="form-control">
                    </div>
                </div>
                @if($mrpMode)
                <div class="col-md-2">
                    <div class="form-group">
                        <label>MRP</label>
                        <input type="number" min="0.01" step="0.01" id="return_mrp" class="form-control">
                    </div>
                </div>
                @endif
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div align="center">
                        <a class="btn btn-success btn-flat addBtn" tabindex="11"><i class="fas fa-arrow-alt-circle-down"></i> Add Item</a>
                        <button type="reset" tabindex="12" class="btn btn-default btn-flat"><i class="fa fa-undo"></i>  Reset</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <table id="return_table" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>SNo</th>
                                <th>Product</th>
                                <th>HSN Code</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                @if($mrpMode)<th>MRP</th>@endif
                                <th>Total</th>
                                <th>Taxable Value</th>
                                <th>{{ $taxLabel }}</th>
                                <th>Delete</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr style="font-weight: bold; background-color: #f5f5f5;">
                                <td colspan="{{ $mrpMode ? 6 : 5 }}" class="text-right">Total</td>
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

                <!-- Amount Payable on the Right -->
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
                                            <select name="pr_paymode" id="pr_paymode" class="form-control" accesskey="p" tabindex="13">
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
                                            <input type="input" class="form-control" name="pr_amount_paid" id="amount_paid" value="{{ old('pr_amount_paid', isset($return) ? $return->pr_amount_paid : 0) }}" tabindex="14">
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
            <button type="submit" tabindex="15" class="btn btn-primary btn-flat"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
</div>
@endsection

@include('partials.customer-modal')
@include('partials.product-modal')
<meta name="csrf-token" content="{{ csrf_token() }}">

@section('scripts')
<script>
    var saleReturnSearchRoute     = "{{ route('sales-returns.search') }}";
    var customerAddRoute          = "{{ route('add.customer') }}";
    var productAddRoute           = "{{ route('add.product') }}";
    var productNewCodeRoute       = "{{ route('product.new.code') }}";
    window.mrpInventoryMode = @json($mrpMode);
    window.inventoryMode = @json($inventoryMode);
    window.saleReturnTaxType = @json($taxType);
    window.saleReturnTaxLabel = @json($taxLabel);
</script>
<script src="{{ asset('js/saleReturn.js') }}"></script>
@endsection