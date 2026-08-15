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
        <h3 class="card-title"><i class="fas fa-wrench"></i> {{$page_title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('services.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
    </div>  
    @if ($errors->any())
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validation Error');
            });
        </script>
    @endif
    <form id="addService" method="post" action="{{ route('services.update')}}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="user_id" name="sv_user" value="{{ session()->get('id') }}">
        @php
            $isEdit = isset($service);
            $taxType = strtolower((string) ($taxType ?? 'gst'));
            $isVat = $taxType === 'vat';
            $taxLabel = $taxLabel ?? ($isVat ? 'VAT' : 'GST');
            $collectTax = (bool) ($collectTax ?? true);
            $allowedServiceTypes = $allowedServiceTypes ?? ['1', '2'];
            $serviceTypeOptions = ['1' => 'B2C', '2' => 'B2B'];
            if ($isEdit && !isset($serviceTypeOptions[(string) ($service->sv_type ?? '1')])) {
                $serviceTypeOptions[(string) ($service->sv_type ?? '1')] = (string) ($service->sv_type ?? '1') === '0' ? 'Legacy No '.$taxLabel : 'Legacy';
            }
            $allowOutOfStockSale = (bool) ($saleSettings['allow_out_of_stock_sale'] ?? false);
            $showManufacturingDate = (bool) ($showManufacturingDate ?? ($saleSettings['manufacturing_date'] ?? false));
            $useMrpPricingMode = (bool) ($saleSettings['mrp_pricing_mode'] ?? false);
        @endphp
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Service No.<sup>*</sup></label>
                        <input type="text" name="sv_vno" id="voucher_no" tabindex="1" class="form-control" value="{{ old('sv_vno', $service->sv_vno ?? $voucher_no) }}" readonly>
                        @if ($errors->has('sv_vno'))
                          <span class="text-danger">{{ $errors->first('sv_vno') }}</span>
                        @endif
                        <input type="hidden" id="sale_items" name="sale_items">
                        @if ($errors->has('sale_items'))
                          <span class="text-danger">{{ $errors->first('sale_items') }}</span>
                        @endif
                        @if ($errors->has('service_items'))
                          <span class="text-danger">{{ $errors->first('service_items') }}</span>
                        @endif
                        <input type="hidden" id="service_items" name="service_items">
                        <input type="hidden" id="edit_mode" value="{{ isset($service) ? 'true' : 'false' }}">
                        <input type="hidden" id="service_id" name="sv_id" value="{{ $service->sv_id ?? '' }}">
                        <input type="hidden" id="existing_sale_items" value='@json($saleItems ?? [])'>
                        <input type="hidden" id="existing_service_items" value='@json($serviceItems ?? [])'>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Date<sup>*</sup></label>
                        <div class="input-group date" id="reservationdate" data-target-input="nearest">
                            <input type="text" name="sv_date" id="service_date" tabindex="2" class="form-control datetimepicker-input" data-target="#reservationdate" value="{{ old('sv_date', isset($service) ? \Carbon\Carbon::parse($service->sv_date)->format('d/m/Y') : now()->format('d/m/Y')) }}" />
                            <div class="input-group-append" data-target="#reservationdate" data-toggle="datetimepicker">
                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                            </div>
                            @if ($errors->has('sv_date'))
                              <span class="text-danger">{{ $errors->first('sv_date') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>{{ $isVat ? 'E-Invoice' : 'E-Way Bill No' }}</label>
                        <input type="text" name="sv_eway_bill_no" id="sv_eway_bill_no" tabindex="4" class="form-control" value="{{ old('sv_eway_bill_no', $service->sv_eway_bill_no ?? '') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Bill Type</label>
                        <select id="sv_type" name="sv_type" tabindex="3" class="form-control" {{ $isEdit ? 'disabled' : '' }}>
                            @foreach($allowedServiceTypes as $type)
                                @continue(!isset($serviceTypeOptions[(string) $type]))
                                <option value="{{ $type }}" {{ (!$isEdit && (string) ($sv_type ?? 1) === (string) $type) || ($isEdit && (string) ($service->sv_type ?? 1) === (string) $type) ? 'selected' : '' }}>{{ $serviceTypeOptions[(string) $type] }}</option>
                            @endforeach
                        </select>
                        @if($isEdit)
                            <input type="hidden" name="sv_type" value="{{ $service->sv_type ?? 1 }}">
                        @endif
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Customer</label>
                        <input type="hidden" name="sv_customer" id="customer_id" value="{{ old('sv_customer', $service->sv_customer ?? '') }}">
                        <select id="customer" tabindex="5" class="select2 form-control" {{ $isEdit ? 'disabled' : '' }} style="width: 100%">
                            @if($isEdit && isset($service->customer))
                                <option value="{{ $service->customer->id }}" selected>{{ $service->customer->customer }}</option>
                            @endif
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Vehicle</label>
                        <input type="text" name="sv_vehicle" id="sv_vehicle" tabindex="6" class="form-control" value="{{ old('sv_vehicle', $service->sv_vehicle ?? '') }}">
                    </div>
                </div>
                @if(!$isVat && $collectTax)
                <div class="col-md-4">
                    <div class="form-group">
                        <label>GST State</label>
                        <select name="sv_state_code" id="sv_state_code" class="form-control select2">
                            <option value="">-- Select State --</option>
                            @foreach($states as $state)
                                <option value="{{ $state->state_code }}"
                                    {{ old('sv_state_code', $service->sv_state_code ?? '') == $state->state_code ? 'selected' : '' }}>
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
                            <input type="checkbox" class="custom-control-input" id="sv_is_igst" name="sv_is_igst" value="1" {{ old('sv_is_igst', $service->sv_is_igst ?? 0) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="sv_is_igst">Apply IGST</label>
                        </div>
                    </div>
                </div>
                @endif
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Ship To / Delivery Location</label>
                         <textarea name="sv_loc" id="sv_loc" tabindex="7" class="form-control" rows="2">{{ old('sv_loc', $service->sv_loc ?? '') }}</textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="sv_remark" id="sv_remark" tabindex="8" class="form-control" rows="2">{{ old('sv_remark', $service->sv_remark ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Service Item</label>
                        <select id="svproduct" tabindex="9" class="select2 form-control" style="width: 100%"></select>
                        <input type="hidden" id="svproduct_id">
                        <input type="hidden" id="svproduct_code">
                        <input type="hidden" id="svproduct_name">
                        <input type="hidden" id="svprice">
                        <input type="hidden" id="svgst">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Service Charge</label>
                        <input type="text" id="svunit_price" tabindex="10" class="form-control">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Discount</label>
                        <input type="text" id="svamount_discount" tabindex="11" class="form-control" value="0">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Discount(%)</label>
                        <input type="text" id="svpercentage_discount" tabindex="12" class="form-control" value="0">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Remarks</label>
                        <input type="text" id="remarks" tabindex="13" class="form-control" value="">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div align="center">
                        <a class="btn btn-success btn-flat addSBtn"  tabindex="14"><i class="fas fa-arrow-alt-circle-down"></i> Add Item</a>
                        <button type="reset" class="btn btn-default btn-flat"><i class="fa fa-undo"></i>  Reset</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <table id="service_table" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>SNo</th>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Discount(Amt)</th>
                                <th>Total</th>
                                <th class="service-tax-column">Taxable Value</th>
                                <th class="service-tax-column">{{ $taxLabel }}</th>
                                <th>Remark</th>
                                <th>Delete</th> 
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr style="font-weight: bold; background-color: #f5f5f5;">
                                <td colspan="2" class="text-right">Total</td>
                                <td id="svfooter_total_before_discount" class="text-right">0.00</td>
                                <td id="svfooter_discount_amount" class="text-right">0.00</td>
                                <td id="svfooter_total_after_discount" class="text-right">0.00</td>
                                <td id="svfooter_taxable_value" class="text-right service-tax-column">0.00</td>
                                <td id="svfooter_gst_value" class="text-right service-tax-column">0.00</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card" id="service_sale_choice_card">
        <div class="card-body text-center">
            <button type="button" class="btn btn-info btn-flat" id="show_service_sale_section">
                <i class="fas fa-plus-circle"></i> Add Product Sale
            </button>
        </div>
    </div>

    <div class="card" id="service_sale_section" style="display: none;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-cash-register"></i> {{$page_title}} Sale</h3>
            <button type="button" class="btn btn-outline-secondary btn-sm btn-flat float-right" id="hide_service_sale_section">
                <i class="fas fa-eye-slash"></i> Hide Sale
            </button>
        </div>  
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Item</label>
                        <select id="product" tabindex="15" class="select2 form-control" style="width: 100%"></select>
                        <input type="hidden" id="product_id">
                        <input type="hidden" id="product_code">
                        <input type="hidden" id="product_name">
                        <input type="hidden" id="price">
                        <input type="hidden" id="current_stock">
                        <input type="hidden" id="gst">
                        <input type="hidden" id="unit_qty">
                        <input type="hidden" id="unit">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>HSN Code</label>
                        <input type="text" id="hsn_code" tabindex="16" class="form-control" readonly>
                    </div>
                </div>
                @if($showManufacturingDate)
                <div class="col-md-2">
                    <div class="form-group">
                        <label>MFG Date</label>
                        <input type="text" id="manufacturingdate" tabindex="17" class="form-control">
                    </div>
                </div>
                @endif
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Unit</label>
                        <select id="item_unit" tabindex="18" class="form-control">
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
                <div class="col-md-4" id="service_batch_selector_group" style="display:none">
                    <div class="form-group">
                        <label>Batch <small class="text-muted">(leave Auto FIFO for automatic allocation)</small></label>
                        <select id="stock_batch_id" class="form-control select2" style="width:100%">
                            <option value="">Auto FIFO / earliest expiry</option>
                        </select>
                    </div>
                </div>
                @endif
                @if($mrpMode)
                <div class="col-md-5" id="service_mrp_lot_selector_group" style="display:none">
                    <div class="form-group">
                        <label>Available Stock (MRP Wise)</label>
                        <select id="mrp_stock_lot_id" class="form-control select2" style="width:100%">
                            <option value="">Select Stock / MRP</option>
                        </select>
                    </div>
                </div>
                @endif
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Qty</label>
                        <input type="text" id="quantity" tabindex="19" class="form-control">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>{{ $useMrpPricingMode ? 'MRP' : 'Unit Price' }}</label>
                        <input type="text" id="unit_price" tabindex="20" class="form-control">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>{{ $useMrpPricingMode ? 'Margin / Discount' : 'Discount' }}</label>
                        <input type="text" id="amount_discount" tabindex="21" class="form-control" value="0">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>{{ $useMrpPricingMode ? 'Margin / Discount(%)' : 'Discount(%)' }}</label>
                        <input type="text" id="percentage_discount" tabindex="22" class="form-control" value="0">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div align="center">
                        <a class="btn btn-success btn-flat addBtn" tabindex="23"><i class="fas fa-arrow-alt-circle-down"></i> Add Item</a>
                        <button type="reset" tabindex="24" class="btn btn-default btn-flat"><i class="fa fa-undo"></i>  Reset</button>
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
                                <th>SNo</th>
                                <th>Product</th>
                                <th>HSN Code</th>
                                @if($showManufacturingDate)
                                    <th>MFG Date</th>
                                @endif
                                @if($batchMode)<th>Batch</th>@endif
                                @if($mrpMode)<th>Stock Lot / MRP</th>@endif
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Total Before Discount</th>
                                <th>Discount(Amt)</th>
                                <th>Total</th>
                                <th class="service-tax-column">Taxable Value</th>
                                <th class="service-tax-column">{{ $taxLabel }}</th>
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
                                <td id="footer_taxable_value" class="text-right service-tax-column">0.00</td>
                                <td id="footer_gst_value" class="text-right service-tax-column">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
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
                                    <input type="hidden" name="sv_amount" readonly id="amount" value="{{ old('sv_amount', isset($service) ? $service->sv_amount : '') }}">
                                    <input type="hidden" name="sv_gst" readonly id="totalgst" value="{{ old('sv_gst', isset($service) ? $service->sv_gst : '') }}">
                                    <input type="hidden" name="sv_discount" readonly id="discount" value="{{ old('sv_discount', isset($service) ? $service->sv_discount : '') }}">
                                    <input type="hidden" name="sv_grandtotal" readonly id="grand_total" value="{{ old('sv_grandtotal', isset($service) ? $service->sv_grandtotal : '') }}">
                                    <input type="hidden" name="sv_amount_payable" readonly id="amount_payable" value="{{ old('sv_amount_payable', isset($service) ? $service->sv_amount_payable : '') }}">
                                    <font color="green">Rs. <label id="amount_payable1">{{ old('sv_amount_payable', isset($service) ? $service->sv_amount_payable : '') }}</label></font>
                                </th>
                            </tr>
                            <tr>
                                <th>Round Off</th>
                                <th>
                                    <input type="hidden" class="form-control" name="sv_round" id="round_off" value="{{ old('sv_round', isset($service) ? $service->sv_round : '') }}">
                                    Rs. <label id="round_off1">{{ old('sv_round', isset($service) ? $service->sv_round : '') }}</label>
                                </th>
                            </tr>
                            @if (!$isEdit)
                            <tr>
                                <th>Payment Mode</th>
                                <th>
                                    @php($selectedPaymode = old('sv_paymode', $service->sv_paymode ?? $defaultPaymode ?? ''))
                                    <div class="form-group">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fa-solid fa-building-columns"></i>
                                                </span>
                                            </div>
                                            <select name="sv_paymode" id="sv_paymode" class="form-control" accesskey="p" tabindex="25">
                                                <option value="">-- Select Payment Mode --</option>
                                                @foreach ($banks as $row)
                                                    <option value="{{ $row['bk_id'] }}" {{ (string) $selectedPaymode === (string) $row['bk_id'] ? 'selected' : '' }}>
                                                        {{ $row['bk_bank'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @if ($errors->has('sv_paymode'))
                                            <span class="text-danger">{{ $errors->first('sv_paymode') }}</span>
                                        @endif
                                    </div>
                                </th>
                            </tr>
                            @else
                                <!-- Hidden input to preserve sv_paymode in edit form -->
                                <input type="hidden" name="sv_paymode" value="{{ old('sv_paymode', $service->sv_paymode ?? '') }}">
                            @endif

                            @if(!$isEdit)
                                <tr>
                                    <th>Amount Paid</th>
                                    <th>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa-solid fa-money-bill-1-wave"></i></span>
                                            </div>
                                            <input type="input" class="form-control" name="sv_amount_paid" id="amount_paid" value="{{ old('sv_amount_paid', isset($service) ? $service->sv_amount_paid : 0) }}">
                                        </div>
                                    </th>
                                </tr>
                                <tr>
                                    <th>Balance to Pay</th>
                                    <th>
                                        <input type="hidden" name="sv_balance" id="balance_to_pay" value="{{ old('sv_balance', isset($service) ? $service->sv_balance : '') }}">
                                        Rs. <label id="balance_to_pay1">{{ old('sv_balance', isset($service) ? $service->sv_balance : '') }}</label>
                                    </th>
                                </tr>
                            @else
                                <input type="hidden" name="sv_amount_paid" id="amount_paid" value="{{ old('sv_amount_paid', isset($service) ? $service->sv_amount_paid : 0) }}">
                                <input type="hidden" name="sv_balance" id="balance_to_pay" value="{{ old('sv_balance', isset($service) ? $service->sv_balance : '') }}">
                            @endif
                            <tr id="payment_due_days_row">
                                <th>Payment Due Days</th>
                                <th>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa-regular fa-calendar-check"></i></span>
                                        </div>
                                        <input type="number" min="0" max="3650" step="1" class="form-control" name="sv_due_days" id="payment_due_days" value="{{ old('sv_due_days', $service->sv_due_days ?? '') }}" placeholder="e.g. 30">
                                    </div>
                                    <small class="text-muted" id="payment_due_date_text"></small>
                                    @if ($errors->has('sv_due_days'))
                                        <span class="text-danger">{{ $errors->first('sv_due_days') }}</span>
                                    @endif
                                </th>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-footer" align="center">
            <button type="submit" id="saveService" tabindex="27" class="btn btn-primary btn-flat"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
</div>
@endsection

@include('partials.customer-modal')
@include('partials.product-modal')

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('scripts')
<script>
    var serviceSearchRoute  = "{{ route('services.search') }}";
    var customerAddRoute    = "{{ route('services.addCustomer') }}";
    var productAddRoute     = "{{ route('services.addProduct') }}";
    var productNewCodeRoute = "{{ route('services.productNewCode') }}";
    var getVoucherAddRoute  = "{{ route('services.getVoucher') }}";
    var serviceProductBatchesRoute = @json(route('services.product-batches', ['product' => '__PRODUCT__']));
    var serviceProductMrpLotsRoute = @json(route('services.product-mrp-lots', ['product' => '__PRODUCT__']));
    window.batchInventoryMode = @json($batchMode);
    window.mrpInventoryMode = @json($mrpMode);
    window.inventoryMode = @json($inventoryMode);
    window.allowOutOfStockSale = @json($allowOutOfStockSale);
    window.useServiceMrpPricingMode = @json($useMrpPricingMode);
    window.serviceTaxType = @json($taxType);
    window.serviceTaxLabel = @json($taxLabel);
    window.serviceCollectTax = @json($collectTax);
    window.showServiceManufacturingDate = @json($showManufacturingDate);
</script>
<script src="{{ asset('js/service.js') }}"></script>
@endsection