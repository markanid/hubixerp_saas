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
        <h3 class="card-title"><i class="fas fa-cash-register"></i> {{$page_title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('sales.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
    </div>  
    @if ($errors->any())
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validation Error');
            });
        </script>
    @endif
    <form id="addSale" method="post" action="{{ route('sales.update')}}" enctype="multipart/form-data">
        @csrf
        @php
            $isEdit = isset($sale);
            $showManufacturingDate = (bool) ($saleSettings['manufacturing_date'] ?? false);
            $allowOutOfStockSale = (bool) ($saleSettings['allow_out_of_stock_sale'] ?? false);
            $useMrpPricingMode = (bool) ($saleSettings['mrp_pricing_mode'] ?? false);
            $taxType = strtolower((string) ($taxType ?? 'gst'));
            $isVat = $taxType === 'vat';
            $taxLabel = $taxLabel ?? ($isVat ? 'VAT' : 'GST');
            $collectTax = (bool) ($collectTax ?? true);
            $allowedSaleTypes = $allowedSaleTypes ?? ['1', '2'];
            $saleTypeOptions = ['1' => 'B2C', '2' => 'B2B'];
            if ($isEdit && !isset($saleTypeOptions[(string) $sale->sa_type])) {
                $saleTypeOptions[(string) $sale->sa_type] = (string) $sale->sa_type === '0' ? 'Legacy No '.$taxLabel : 'Legacy';
            }
            @endphp
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Sale No.<sup>*</sup></label>
                        <input type="text" name="sa_vno" id="voucher_no" class="form-control" value="{{ old('sa_vno', $sale->sa_vno ?? $voucher_no) }}" readonly tabindex="-1">
                        @if ($errors->has('sa_vno'))
                          <span class="text-danger">{{ $errors->first('sa_vno') }}</span>
                        @endif
                        <input type="hidden" id="sale_items" name="sale_items">
                        @if ($errors->has('sale_items'))
                          <span class="text-danger">{{ $errors->first('sale_items') }}</span>
                        @endif

                        <input type="hidden" id="edit_mode" value="{{ isset($sale) ? 'true' : 'false' }}">
                        <input type="hidden" id="sale_id" name="sa_id" value="{{ $sale->sa_id ?? '' }}">
                        <input type="hidden" id="existing_items" value='@json($sale->saleDetails ?? [])'>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Date<sup>*</sup></label>
                        <div class="input-group date" id="reservationdate" data-target-input="nearest">
                            <input type="text" name="sa_date" id="sale_date" class="form-control datetimepicker-input" data-target="#reservationdate" value="{{ old('sa_date', isset($sale) ? \Carbon\Carbon::parse($sale->sa_date)->format('d/m/Y') : now()->format('d/m/Y')) }}" />
                            <div class="input-group-append" data-target="#reservationdate" data-toggle="datetimepicker">
                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                            </div>
                            @if ($errors->has('sa_date'))
                              <span class="text-danger">{{ $errors->first('sa_date') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Bill Type</label>
                        <select id="sa_type" name="sa_type" class="form-control" {{ $isEdit ? 'disabled' : '' }}>
                            @foreach($allowedSaleTypes as $type)
                                @continue(!isset($saleTypeOptions[(string) $type]))
                                <option value="{{ $type }}" {{ (!$isEdit && (string) ($sa_type ?? 1) === (string) $type) || ($isEdit && (string) $sale->sa_type === (string) $type) ? 'selected' : '' }}>{{ $saleTypeOptions[(string) $type] }}</option>
                            @endforeach
                        </select>
                        {{-- If disabled, send value via hidden field --}}
                        @if($isEdit)
                            <input type="hidden" name="sa_type" value="{{ $sale->sa_type }}">
                        @endif
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>{{ $isVat ? 'E-Invoice' : 'E-Way Bill No' }}</label>
                        <input type="text" name="sa_eway_bill_no" id="sa_eway_bill_no" class="form-control" value="{{ old('sa_eway_bill_no', $sale->sa_eway_bill_no ?? '') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Customer</label>
                        <input type="hidden" name="sa_customer" id="customer_id" value="{{ old('sa_customer', $sale->sa_customer ?? '') }}">
                        <select id="customer" class="select2 form-control" {{ $isEdit ? 'disabled' : '' }} style="width: 100%">
                            @if($isEdit && isset($sale->customer))
                                <option value="{{ $sale->customer->id }}" selected>{{ $sale->customer->customer }}</option>
                            @endif
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Ship To</label>
                        <textarea name="sa_loc" id="sa_loc" class="form-control" rows="2">{{ old('sa_loc', $sale->sa_loc ?? '') }}</textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="sa_remark" id="sa_remark" class="form-control" rows="2">{{ old('sa_remark', $sale->sa_remark ?? '') }}</textarea>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Vehicle</label>
                        <input type="text" name="sa_vehicle" id="sa_vehicle" class="form-control" value="{{ old('sa_vehicle', $sale->sa_vehicle ?? '') }}">
                    </div>
                </div>
                @if(!$isVat && $collectTax)
                @php($selectedStateCode = old('sa_state_code', $sale->sa_state_code ?? '32'))
                <div class="col-md-4">
                    <div class="form-group">
                        <label>GST State</label>
                        <select name="sa_state_code" id="sa_state_code" class="form-control select2">
                            <option value="">-- Select State --</option>
                            @foreach($states as $state)
                                <option value="{{ $state->state_code }}"
                                    {{ $selectedStateCode == $state->state_code ? 'selected' : '' }}>
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
                            <input type="checkbox" class="custom-control-input" id="sa_is_igst" name="sa_is_igst" value="1" {{ old('sa_is_igst', $sale->sa_is_igst ?? 0) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="sa_is_igst">Apply IGST</label>
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
                        <select id="product" class="select2 form-control" style="width: 100%"></select>
                        <input type="hidden" id="product_id">
                        <input type="hidden" id="product_code">
                        <input type="hidden" id="product_name">
                        <input type="hidden" id="price">
                        <input type="hidden" id="sale_price">
                        <input type="hidden" id="mrp">
                        <input type="hidden" id="margin">
                        <input type="hidden" id="amt_margin">
                        <input type="hidden" id="current_stock">
                        @if($mrpMode)
                        <input type="hidden" id="mrp_stock_lot_id">
                        @endif
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
                @if($showManufacturingDate)
                <div class="col-md-2">
                    <div class="form-group">
                        <label>MFG Date</label>
                        <input type="text" id="manufacturingdate" class="form-control">
                    </div>
                </div>
                @endif
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
                        <label>In Stock</label>
                        <input type="text" id="in_stock" class="form-control" readonly tabindex="-1">
                    </div>
                </div>
                @if($batchMode)
                <div class="col-md-4" id="batch_selector_group" style="display:none">
                    <div class="form-group">
                        <label>Batch <small class="text-muted">(leave Auto FIFO for automatic allocation)</small></label>
                        <select id="stock_batch_id" class="form-control select2" style="width:100%">
                            <option value="">Auto FIFO / earliest expiry</option>
                        </select>
                    </div>
                </div>
                @endif
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Qty</label>
                        <input type="text" id="quantity" class="form-control">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>{{ $useMrpPricingMode ? 'MRP' : 'Unit Price' }}</label>
                        <input type="text" id="unit_price" class="form-control">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>{{ $useMrpPricingMode ? 'Margin / Discount' : 'Discount' }}</label>
                        <input type="text" id="amount_discount" class="form-control" value="0">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>{{ $useMrpPricingMode ? 'Margin / Discount(%)' : 'Discount(%)' }}</label>
                        <input type="text" id="percentage_discount" class="form-control" value="0">
                    </div>
                </div>
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
                <div class="col-md-12">
                    <table id="sale_table" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>HSN Code</th>
                                @if($showManufacturingDate)
                                    <th>MFG Date</th>
                                @endif
                                @if($batchMode)<th>Batch</th>@endif
                                @if($mrpMode)<th>MRP</th>@endif
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Total Before Discount</th>
                                <th>Discount(Amt)</th>
                                <th>Total</th>
                                <th class="sale-tax-column">Taxable Value</th>
                                <th class="sale-tax-column">{{ $taxLabel }}</th>
                                <th>Delete</th> 
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr style="font-weight: bold; background-color: #f5f5f5;">
                                <td colspan="{{ 5 + ($showManufacturingDate ? 1 : 0) + (($batchMode || $mrpMode) ? 1 : 0) }}" class="text-right">Total</td>
                                <td id="footer_total_before_discount" class="text-right">0.00</td>
                                <td id="footer_discount_amount" class="text-right">0.00</td>
                                <td id="footer_total_after_discount" class="text-right">0.00</td>
                                <td id="footer_taxable_value" class="text-right sale-tax-column">0.00</td>
                                <td id="footer_gst_value" class="text-right sale-tax-column">0.00</td>
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
                                    <input type="hidden" name="sa_amount" readonly id="amount" value="{{ old('sa_amount', isset($sale) ? $sale->sa_amount : '') }}">
                                    <input type="hidden" name="sa_gst" readonly id="totalgst" value="{{ old('sa_gst', isset($sale) ? $sale->sa_gst : '') }}">
                                    <input type="hidden" name="sa_discount" readonly id="discount" value="{{ old('sa_discount', isset($sale) ? $sale->sa_discount : '') }}">
                                    <input type="hidden" name="sa_grandtotal" readonly id="grand_total" value="{{ old('sa_grandtotal', isset($sale) ? $sale->sa_grandtotal : '') }}">
                                    <input type="hidden" name="sa_amount_payable" readonly id="amount_payable" value="{{ old('sa_amount_payable', isset($sale) ? $sale->sa_amount_payable : '') }}">
                                    <font color="green">Rs. <label id="amount_payable1">{{ old('sa_amount_payable', isset($sale) ? $sale->sa_amount_payable : '') }}</label></font>
                                </th>
                            </tr>
                            <tr>
                                <th>Round Off</th>
                                <th>
                                    <input type="hidden" class="form-control" name="sa_round" id="round_off" value="{{ old('sa_round', isset($sale) ? $sale->sa_round : '') }}">
                                    Rs. <label id="round_off1">{{ old('sa_round', isset($sale) ? $sale->sa_round : '') }}</label>
                                </th>
                            </tr>
                            @if (!$isEdit)
                            <tr>
                                <th>Payment Mode</th>
                                <th>
                                    @php($selectedPaymode = old('sa_paymode', $sale->sa_paymode ?? $defaultPaymode ?? ''))
                                    <div class="form-group">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fa-solid fa-building-columns"></i>
                                                </span>
                                            </div>
                                            <select name="sa_paymode" id="sa_paymode" class="form-control" accesskey="p">
                                                <option value="">-- Select Payment Mode --</option>
                                                @foreach ($banks as $row)
                                                    <option value="{{ $row['bk_id'] }}" {{ (string) $selectedPaymode === (string) $row['bk_id'] ? 'selected' : '' }}>
                                                        {{ $row['bk_bank'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @if ($errors->has('sa_paymode'))
                                            <span class="text-danger">{{ $errors->first('sa_paymode') }}</span>
                                        @endif
                                    </div>
                                </th>
                            </tr>
                            @else
                                <!-- Hidden input to preserve sa_paymode in edit form -->
                                <input type="hidden" name="sa_paymode" value="{{ old('sa_paymode', $sale->sa_paymode ?? '') }}">
                            @endif
                            @if(!$isEdit)
                                <tr>
                                    <th>Amount Paid</th>
                                    <th>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa-solid fa-money-bill-1-wave"></i></span>
                                            </div>
                                            <input type="input" class="form-control" name="sa_amount_paid" id="amount_paid" value="{{ old('sa_amount_paid', isset($sale) ? $sale->sa_amount_paid : 0) }}">
                                        </div>
                                    </th>
                                </tr>
                                <tr>
                                    <th>Balance to Pay</th>
                                    <th>
                                        <input type="hidden" name="sa_balance" id="balance_to_pay" value="{{ old('sa_balance', isset($sale) ? $sale->sa_balance : '') }}">
                                        Rs. <label id="balance_to_pay1">{{ old('sa_balance', isset($sale) ? $sale->sa_balance : '') }}</label>
                                    </th>
                                </tr>
                            @else
                                <!-- Hidden inputs to preserve values on submit -->
                                <input type="hidden" name="sa_amount_paid" id="amount_paid" value="{{ old('sa_amount_paid', isset($sale) ? $sale->sa_amount_paid : 0) }}">
                                <input type="hidden" name="sa_balance" id="balance_to_pay" value="{{ old('sa_balance', isset($sale) ? $sale->sa_balance : '') }}">
                            @endif
                            <tr id="payment_due_days_row">
                                <th>Payment Due Days</th>
                                <th>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa-regular fa-calendar-check"></i></span>
                                        </div>
                                        <input type="number" min="0" max="3650" step="1" class="form-control" name="sa_due_days" id="payment_due_days" value="{{ old('sa_due_days', $sale->sa_due_days ?? '') }}" placeholder="e.g. 30">
                                    </div>
                                    <small class="text-muted" id="payment_due_date_text"></small>
                                    @if ($errors->has('sa_due_days'))
                                        <span class="text-danger">{{ $errors->first('sa_due_days') }}</span>
                                    @endif
                                </th>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-footer" align="center">
            <button type="submit" id="saveSale" class="btn btn-primary btn-flat"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
</div>
@endsection

@include('partials.customer-modal')
@include('partials.product-modal')
@if($mrpMode)
@include('sale::sales.partials.mrp-stock-lot-modal')
@endif
<meta name="csrf-token" content="{{ csrf_token() }}">

@section('scripts')
<script>
    var saleSearchRoute     = "{{ route('search') }}";
    var customerAddRoute    = "{{ route('add.customer') }}";
    var productAddRoute     = "{{ route('add.product') }}";
    var productNewCodeRoute = "{{ route('product.new.code') }}";
    var getVoucherAddRoute  = "{{ route('sales.getVoucher') }}";
    var productBatchesRoute = @json(route('sales.product-batches', ['product' => '__PRODUCT__']));
    var productMrpLotsRoute = @json(route('sales.product-mrp-lots', ['product' => '__PRODUCT__']));
</script>
<script>
    window.saleSettings = @json($saleSettings);
    window.showSaleManufacturingDate = @json($showManufacturingDate);
    window.allowOutOfStockSale = @json($allowOutOfStockSale);
    window.useSaleMrpPricingMode = @json($useMrpPricingMode);
    window.saleTaxType = @json($taxType);
    window.saleTaxLabel = @json($taxLabel);
    window.saleCollectTax = @json($collectTax);
    window.batchInventoryMode = @json($batchMode);
    window.mrpInventoryMode = @json($mrpMode);
    window.inventoryMode = @json($inventoryMode);
</script>
<script src="{{ asset('js/sale.js') }}?v={{ filemtime(public_path('js/sale.js')) }}"></script>

@endsection