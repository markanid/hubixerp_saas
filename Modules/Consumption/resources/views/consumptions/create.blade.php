@extends('layout')
<link rel="stylesheet" href="{{asset('css/custom.css')}}">
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
        <h3 class="card-title"><i class="fas fa-box-open"></i> {{$page_title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('consumptions.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
    </div>  
   @if ($errors->any())
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validation Error');
            });
        </script>
    @endif
    @if (session('error'))
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                toastr.error(`{!! session('error') !!}`, 'Error');
            });
        </script>
    @endif
    <form id="addConsumption" method="post" action="{{ route('consumptions.update')}}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="user_id" name="con_user" value="{{ session()->get('id') }}">
        @php
            $isEdit = isset($consumption);
            $batchMode = (bool) ($batchMode ?? false);
        @endphp
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Consumption No.<sup>*</sup></label>
                        <input type="text" name="con_vno" id="voucher_no" tabindex="1" class="form-control" value="{{ old('con_vno', $consumption->con_vno ?? $voucher_no) }}" readonly>
                        @if ($errors->has('con_vno'))
                          <span class="text-danger">{{ $errors->first('con_vno') }}</span>
                        @endif
                        <input type="hidden" id="consumption_items" name="consumption_items">
                        @if ($errors->has('consumption_items'))
                          <span class="text-danger">{{ $errors->first('consumption_items') }}</span>
                        @endif

                        <input type="hidden" id="edit_mode" value="{{ isset($consumption) ? 'true' : 'false' }}">
                        <input type="hidden" id="consumption_id" name="con_id" value="{{ $consumption->con_id ?? '' }}">
                        <input type="hidden" id="existing_items" value='@json($consumption->consumptionDetails ?? [])'>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Date<sup>*</sup></label>
                        <div class="input-group date" id="reservationdate" data-target-input="nearest">
                            <input type="text" name="con_date" id="con_date" tabindex="2" class="form-control datetimepicker-input" data-target="#reservationdate" value="{{ old('con_date', isset($consumption) ? \Carbon\Carbon::parse($consumption->con_date)->format('d/m/Y') : now()->format('d/m/Y')) }}" />
                            <div class="input-group-append" data-target="#reservationdate" data-toggle="datetimepicker">
                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                            </div>
                            @if ($errors->has('con_date'))
                              <span class="text-danger">{{ $errors->first('con_date') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-8"></div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Item</label>
                        <select id="product" tabindex="3" class="select2 form-control" style="width: 100%"></select>
                        <input type="hidden" id="product_id">
                        <input type="hidden" id="product_code">
                        <input type="hidden" id="product_name">
                        <input type="hidden" id="price">
                        <input type="hidden" id="current_stock">
                        <input type="hidden" id="unit_qty">
                        <input type="hidden" id="unit">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>HSN Code</label>
                        <input type="text" id="hsn_code" tabindex="4" class="form-control" readonly>
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
                        <input type="text" id="in_stock" tabindex="6" class="form-control" readonly>
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
                @if($mrpMode)
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
                        <label>Qty</label>
                        <input type="text" id="quantity" tabindex="7" class="form-control">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Purchased Price</label>
                        <input type="text" id="unit_price" tabindex="8" class="form-control" readonly>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Remark</label>
                        <input type="text" id="remark" tabindex="9" class="form-control">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div align="center">
                        <a class="button btn btn-success btn-flat addBtn"  tabindex="10"><i class="fas fa-arrow-alt-circle-down"></i> Add Item</a>
                        <button type="reset" tabindex="10" class="btn btn-default btn-flat"><i class="fa fa-undo"></i>  Reset</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <table id="consumption_table" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>HSN Code</th>
                                @if($batchMode)<th>Batch</th>@endif
                                @if($mrpMode)<th>Stock Lot / MRP</th>@endif
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Remarks</th>
                                <th>Delete</th> 
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr style="font-weight: bold; background-color: #f5f5f5;">
                                <td colspan="{{ 3 + (($batchMode || $mrpMode) ? 1 : 0) }}" class="text-right">Total</td>
                                <td></td>
                                <td></td>
                                <td id="footer_total_amount" class="text-right">0.00</td>
                                <td></td>
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
                                <th>Total Amount</th>
                                <th>
                                    <input type="hidden" name="con_amount" readonly id="amount" value="{{ old('con_amount', isset($consumption) ? $consumption->con_amount : '') }}">
                                    <font color="green">Rs. <label id="amount_payable1">{{ old('con_amount', isset($consumption) ? $consumption->con_amount : '') }}</label></font>
                                </th>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-footer" align="center">
            <button type="submit" id="saveConsumption" tabindex="11" class="btn btn-primary btn-flat"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
    </div>
</div>
@endsection

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('scripts')
<script>
    var consumptionSearchRoute     = "{{ route('consumptions.search') }}";
    var productBatchesRoute        = @json(route('consumptions.product-batches', ['product' => '__PRODUCT__']));
    var productMrpLotsRoute        = @json(route('consumptions.product-mrp-lots', ['product' => '__PRODUCT__']));
    window.batchInventoryMode      = @json($batchMode);
    window.mrpInventoryMode        = @json($mrpMode);
    window.inventoryMode           = @json($inventoryMode);
</script>
<script src="{{ asset('js/consumption.js') }}"></script>
@endsection