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
                    <li class="breadcrumb-item"><a href="{{route('estimations.index')}}">Estimation</a></li>
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
        <h3 class="card-title"><i class="fa-solid fa-receipt"></i> {{$page_title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('estimations.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
    </div>  
    @if ($errors->any())
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validation Error');
            });
        </script>
    @endif
    <form id="addEstimation" method="post" action="{{ route('estimations.update')}}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="user_id" name="es_user" value="{{ session()->get('id') }}">
        @php
            $isEdit = isset($estimation);
            $showAccountEffect = (bool) ($accountEffectEnabled ?? false);
            $useMrpPricingMode = (bool) ($useMrpPricingMode ?? false);
        @endphp
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Estimation No.<sup>*</sup></label>
                        <input type="text" name="es_vno" id="voucher_no" tabindex="1" class="form-control" value="{{ old('es_vno', $estimation->es_vno ?? $voucher_no) }}" readonly>
                        @if ($errors->has('es_vno'))
                          <span class="text-danger">{{ $errors->first('es_vno') }}</span>
                        @endif
                        <input type="hidden" id="estimation_items" name="estimation_items">
                        @if ($errors->has('estimation_items'))
                          <span class="text-danger">{{ $errors->first('estimation_items') }}</span>
                        @endif
                        <input type="hidden" id="edit_mode" value="{{ isset($estimation) ? 'true' : 'false' }}">
                        <input type="hidden" id="estimation_id" name="es_id" value="{{ $estimation->es_id ?? '' }}">
                        <input type="hidden" id="existing_items" value='@json($estimation->estimationDetails ?? [])'>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Date<sup>*</sup></label>
                        <div class="input-group date" id="reservationdate" data-target-input="nearest">
                            <input type="text" name="es_date" id="estimation_date" tabindex="2" class="form-control datetimepicker-input" data-target="#reservationdate" value="{{ old('es_date', isset($estimation) ? \Carbon\Carbon::parse($estimation->es_date)->format('d/m/Y') : now()->format('d/m/Y')) }}" />
                            <div class="input-group-append" data-target="#reservationdate" data-toggle="datetimepicker">
                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                            </div>
                            @if ($errors->has('es_date'))
                              <span class="text-danger">{{ $errors->first('es_date') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-2"></div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Customer</label>
                        <input type="hidden" name="es_customer" id="customer_id" value="{{ old('es_customer', $estimation->es_customer ?? '') }}">
                        <select id="customer" tabindex="3" class="select2 form-control" {{ $isEdit ? 'disabled' : '' }} style="width: 100%"></select>
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
                        <select id="product" tabindex="4" class="select2 form-control" style="width: 100%"></select>
                        <input type="hidden" id="product_id">
                        <input type="hidden" id="product_code">
                        <input type="hidden" id="product_name">
                        <input type="hidden" id="price">
                        <input type="hidden" id="sale_price">
                        <input type="hidden" id="mrp">
                        <input type="hidden" id="margin">
                        <input type="hidden" id="amt_margin">
                        <input type="hidden" id="current_stock">
                        <input type="hidden" id="unit_qty">
                        <input type="hidden" id="unit">
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
                        <label>Qty</label>
                        <input type="text" id="quantity" tabindex="6" class="form-control">
                    </div>
                </div>
                @if($mrpMode && $showAccountEffect)
                <div class="col-md-2">
                    <div class="form-group">
                        <label>In Stock</label>
                        <input type="text" id="in_stock" class="form-control" readonly>
                    </div>
                </div>
                @endif
                <div class="col-md-2">
                    <div class="form-group">
                        <label>{{ $useMrpPricingMode ? 'MRP' : 'Unit Price' }}</label>
                        <input type="text" id="unit_price" tabindex="7" class="form-control">
                    </div>
                </div>
                @if($mrpMode && $showAccountEffect)
                <div class="col-md-5" id="mrp_lot_selector_group" style="display:none">
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
                        <label>{{ $useMrpPricingMode ? 'Margin / Discount' : 'Discount' }}</label>
                        <input type="text" id="amount_discount" tabindex="8" class="form-control" value="0">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>{{ $useMrpPricingMode ? 'Margin / Discount(%)' : 'Discount(%)' }}</label>
                        <input type="text" id="percentage_discount" tabindex="9" class="form-control" value="0">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div align="center">
                        <a class="button btn btn-info addBtn"  tabindex="10"><i class="fas fa-arrow-alt-circle-down"></i> Add Item</a>
                        <button type="reset" tabindex="11" class="btn btn-default btn-flat"><i class="fa fa-undo"></i>  Reset</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <table id="estimation_table" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>SNo</th>
                                <th>Product</th>
                                @if($mrpMode && $showAccountEffect)<th>Stock Lot / MRP</th>@endif
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Total Before Discount</th>
                                <th>Discount(Amt)</th>
                                <th>Total</th>
                                <th>Delete</th> 
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr style="font-weight: bold; background-color: #f5f5f5;">
                                <td colspan="{{ ($mrpMode && $showAccountEffect) ? 5 : 4 }}" class="text-right">Total</td>
                                <td id="footer_total_before_discount" class="text-right">0.00</td>
                                <td id="footer_discount_amount" class="text-right">0.00</td>
                                <td id="footer_total_after_discount" class="text-right">0.00</td>
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
                                    <input type="hidden" name="es_amount" readonly id="amount" value="{{ old('es_amount', isset($estimation) ? $estimation->es_amount : '') }}">
                                    <input type="hidden" name="es_discount" readonly id="discount" value="{{ old('es_discount', isset($estimation) ? $estimation->es_discount : '') }}">
                                    <input type="hidden" name="es_grandtotal" readonly id="grand_total" value="{{ old('es_grandtotal', isset($estimation) ? $estimation->es_grandtotal : '') }}">
                                    <input type="hidden" name="es_amount_payable" readonly id="amount_payable" value="{{ old('es_amount_payable', isset($estimation) ? $estimation->es_amount_payable : '') }}">
                                    <font color="green">Rs. <label id="amount_payable1">{{ old('es_amount_payable', isset($estimation) ? $estimation->es_amount_payable : '') }}</label></font>
                                </th>
                            </tr>
                            <tr>
                                <th>Round Off</th>
                                <th>
                                    <input type="hidden" class="form-control" name="es_round" id="round_off" value="{{ old('es_round', isset($estimation) ? $estimation->es_round : '') }}">
                                    Rs. <label id="round_off1">{{ old('es_round', isset($estimation) ? $estimation->es_round : '') }}</label>
                                </th>
                            </tr>
                            @if($showAccountEffect)
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
                                            <select name="es_paymode" id="paymode" class="form-control">
                                                <option value="">Select Payment Mode</option>
                                                @foreach($banks as $bank)
                                                    <option value="{{ $bank->bk_id }}" {{ (string) old('es_paymode', $estimation->es_paymode ?? $defaultPaymode ?? '') === (string) $bank->bk_id ? 'selected' : '' }}>
                                                        {{ $bank->bk_bank }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </th>
                            </tr>
                            <tr>
                                <th>Amount Paid</th>
                                <th>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa-solid fa-money-bill-1-wave"></i></span>
                                        </div>
                                        <input type="number" step="0.01" min="0" name="es_amount_paid" id="amount_paid" class="form-control" value="{{ old('es_amount_paid', isset($estimation) ? $estimation->es_amount_paid : 0) }}">
                                    </div>
                                </th>
                            </tr>
                            <tr>
                                <th>Balance to Pay</th>
                                <th>
                                    <input type="hidden" name="es_balance" id="balance" value="{{ old('es_balance', isset($estimation) ? $estimation->es_balance : 0) }}">
                                    Rs. <label id="balance1">{{ old('es_balance', isset($estimation) ? number_format((float) $estimation->es_balance, 2) : '0.00') }}</label>
                                </th>
                            </tr>
                            <tr id="due_days_row" style="display:none;">
                                <th>Due Days</th>
                                <th>
                                    <input type="number" min="0" name="es_due_days" id="due_days" class="form-control" value="{{ old('es_due_days', $estimation->es_due_days ?? '') }}">
                                    <small class="text-muted">Due Date: <span id="due_date_text">{{ isset($estimation->es_due_date) && $estimation->es_due_date ? \Carbon\Carbon::parse($estimation->es_due_date)->format('d-m-Y') : '-' }}</span></small>
                                </th>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-footer" align="center">
            <button type="submit" id="addEstimation" tabindex="13" class="btn btn-primary  btn-flat"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
</div>
@endsection

<!-- Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" role="dialog" aria-labelledby="addCustomerModalLabel" aria-hidden="true" style="display: none;">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addCustomerModalLabel">Create New Customer</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="customerForm">
        <div class="modal-body">
            <div class="form-group">
                <label for="new_customer_name">Customer Name</label>
                <input type="text" class="form-control" id="new_customer_name">
                <span class="text-danger error-name"></span>
            </div>
            <div class="form-group">
                <label for="new_customer_phone">Phone</label>
                <input type="text" class="form-control" id="new_customer_phone">
                <span class="text-danger error-phone"></span>
            </div>
        </div>
        <div class="modal-footer d-flex justify-content-center">
            <button type="submit" class="btn btn-primary  btn-flat"><i class="fas fa-save"></i> Save</button>
            <button type="button" class="btn btn-secondary  btn-flat" data-dismiss="modal"><i class="fas fa-undo-alt"></i> Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

@include('partials.product-modal')

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('scripts')
<script>
    var estimationSearchRoute   = "{{ route('estimations.search') }}";
    var estimationProductMrpLotsRoute = @json(route('estimations.product-mrp-lots', ['product' => '__PRODUCT__']));
    var customerAddRoute        = "{{ route('add.customer') }}";
    var productAddRoute         = "{{ route('estimations.addProduct') }}";
    var productNewCodeRoute     = "{{ route('estimations.productNewCode') }}";
    var estimationAccountEffect = @json($showAccountEffect);
    window.mrpInventoryMode = @json($mrpMode);
    var useEstimationMrpPricingMode = @json($useMrpPricingMode);
</script>
<script src="{{ asset('js/estimation.js') }}"></script>
@endsection