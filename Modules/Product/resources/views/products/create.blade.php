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
                    <li class="breadcrumb-item"><a href="{{route('products.index')}}">Products</a></li>
                    <li class="breadcrumb-item active">{{$page_title}}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="card card-navy card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-boxes"></i> {{$page_title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('products.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
        @if ($errors->any())
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validation Error');
                });
            </script>
        @endif
    </div>
</div>
<form id="addProduct" method="post" action="{{ route('products.update') }}" enctype="multipart/form-data">
    @csrf
    @php
        // Keep the form safe during rolling deployments or calls from older cached controllers.
        $isUsed = $isUsed ?? false;
        $batchMode = $batchMode ?? false;
        $inventoryMode = $inventoryMode ?? ($batchMode ? 'batch' : 'standard');
        $stockEditable = $stockEditable ?? false;
    @endphp
    <input type="hidden" id="id" name="id" value="{{ $product->id ?? '' }}">
    <div class="card card-navy">
        <div class="card-header">
            <h3 class="card-title"><i class="far fa-file-alt"></i> Basic Details</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-6">
                    <label>Product Code<sup>*</sup></label>
                    <input type="text" name="product_code" id="product_code" tabindex="1" class="form-control" value="{{ !empty($product->product_code) ? $product->product_code : $product_code }}" readonly>
                    @if ($errors->has('product_code'))
                    <span class="text-danger">{{ $errors->first('product_code') }}</span>
                    @endif
                </div>
                <div class="form-group col-md-6">
                    <label>Product<sup>*</sup></label>
                    <input type="text" name="product" id="product" tabindex="2" class="form-control" value="{{ !empty($product->product) ? $product->product : '' }}">
                    @if ($errors->has('product'))
                    <span class="text-danger">{{ $errors->first('product') }}</span>
                    @endif
                </div>
                <div class="form-group col-md-3">
                    <label>HSN Code</label>
                    <input type="text" name="hsn_code" id="hsn_code" tabindex="3" class="form-control" value="{{ !empty($product->hsn_code) ? $product->hsn_code : '' }}">
                    @if ($errors->has('hsn_code'))
                    <span class="text-danger">{{ $errors->first('hsn_code') }}</span>
                    @endif
                </div>
                <div class="form-group col-md-3">
                    <label>Barcode</label>
                    <div class="input-group">
                        <input type="text" name="bar_code" id="bar_code" tabindex="4" class="form-control"
                            value="{{ !empty($product->bar_code) ? $product->bar_code : '' }}">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-default" id="generate_barcode_btn" title="Generate Barcode"><i class="fas fa-barcode"></i></button>
                        </div>
                    </div>
                    @if ($errors->has('bar_code'))
                        <span class="text-danger">{{ $errors->first('bar_code') }}</span>
                    @endif
                </div>
                <div class="form-group col-md-3">
                    <label>Purchase Price</label>
                    <input type="text" name="pprice" id="purchase_price" tabindex="5" class="form-control" value="{{ !empty($product->pprice) ? $product->pprice : '' }}">
                    @if ($errors->has('pprice'))
                    <span class="text-danger">{{ $errors->first('pprice') }}</span>
                    @endif
                </div>
                <div class="form-group col-md-3">
                    <label>MRP</label>
                    <input type="text" name="mrp" id="mrp" tabindex="6" class="form-control" value="{{ !empty($product->mrp) ? $product->mrp : '' }}">
                    @if ($errors->has('mrp'))
                    <span class="text-danger">{{ $errors->first('mrp') }}</span>
                    @endif
                </div>
                <div class="form-group col-md-3">
                    <label>Margin (%)</label>
                    <input type="text" name="margin" id="margin" tabindex="7" class="form-control" value="{{ !empty($product->margin) ? $product->margin : '' }}">
                    @if ($errors->has('margin'))
                    <span class="text-danger">{{ $errors->first('margin') }}</span>
                    @endif
                </div>
                <div class="form-group col-md-3">
                    <label>Margin (Amt)</label>
                    <input type="text" name="amt_margin" id="amt_margin" tabindex="8" class="form-control" value="{{ !empty($product->amt_margin) ? $product->amt_margin : '' }}">
                    @if ($errors->has('amt_margin'))
                    <span class="text-danger">{{ $errors->first('amt_margin') }}</span>
                    @endif
                </div>
                <div class="form-group col-md-3">
                    <label>Sale Price</label>
                    <input type="text" name="price" id="price" tabindex="9" class="form-control" value="{{ !empty($product->price) ? $product->price : '' }}">
                    @if ($errors->has('price'))
                    <span class="text-danger">{{ $errors->first('price') }}</span>
                    @endif
                </div>
                <div class="form-group col-md-3">
                    <label>GST(%)</label>
                    <select name="gst" id="gst" tabindex="10" class="form-control">
                        <option value="">-- Select GST --</option>
                        <option value="0" {{ old('gst', $product->gst ?? '0') == '0' ? 'selected' : '' }}>0 %</option>
                        <option value="5" {{ old('gst', $product->gst ?? '') == '5' ? 'selected' : '' }}>5 %</option>
                        <option value="12" {{ old('gst', $product->gst ?? '') == '12' ? 'selected' : '' }}>12 %</option>
                        <option value="18" {{ old('gst', $product->gst ?? '') == '18' ? 'selected' : '' }}>18 %</option>
                        <option value="28" {{ old('gst', $product->gst ?? '') == '28' ? 'selected' : '' }}>28 %</option>
                    </select> 
                    @if ($errors->has('gst'))
                    <span class="text-danger">{{ $errors->first('gst') }}</span>
                    @endif
                </div>
                <div class="form-group col-md-3">
                    <label>Unit</label>
                    <select name="unit" id="unit" tabindex="11" class="form-control" {{ $isUsed ? 'disabled' : '' }}>
                        <option value="">-- Select Unit --</option>
                        <option value="No.s" {{ old('unit', $product->unit ?? 'No.s') == 'No.s' ? 'selected' : '' }}>No.s</option>
                        <option value="Kg" {{ old('unit', $product->unit ?? '') == 'Kg' ? 'selected' : '' }}>Kg</option>
                        <option value="Ltr" {{ old('unit', $product->unit ?? '') == 'Ltr' ? 'selected' : '' }}>Litre</option>
                        <option value="Box" {{ old('unit', $product->unit ?? '') == 'Box' ? 'selected' : '' }}>Box</option>
                        <option value="Pkt" {{ old('unit', $product->unit ?? '') == 'Pkt' ? 'selected' : '' }}>Packet</option>
                    </select> 
                    @if ($errors->has('unit'))
                    <span class="text-danger">{{ $errors->first('unit') }}</span>
                    @endif
                    @if ($isUsed)
                        <input type="hidden" name="unit" value="{{ $product->unit }}"> 
                    @endif
            </div>
                <div class="form-group col-md-3">
                    <label>Unit Qty</label>
                    <input type="text" name="uqty" id="unit_qty" tabindex="12" class="form-control"
                        value="{{ old('uqty', !empty($product->uqty) ? $product->uqty : '1.00') }}" {{ $isUsed ? 'disabled' : '' }}>
                    @if ($isUsed)
                        <input type="hidden" name="uqty" value="{{ $product->uqty }}">
                    @endif
                    @if ($errors->has('uqty'))
                    <span class="text-danger">{{ $errors->first('uqty') }}</span>
                    @endif
                </div>
                <div class="form-group col-md-3">
                    <label>Min Qty</label>
                    <input type="text" name="minquantity" id="min_quantity" tabindex="13" class="form-control" value="{{ !empty($product->minquantity) ? $product->minquantity : '1' }}">
                    @if ($errors->has('minquantity'))
                    <span class="text-danger">{{ $errors->first('minquantity') }}</span>
                    @endif
                </div>
                <div class="form-group col-md-3">
                    <label>Max Qty</label>
                    <input type="text" name="maxquantity" id="max_quantity" tabindex="14" class="form-control" value="{{ !empty($product->maxquantity) ? $product->maxquantity : '5' }}">
                    @if ($errors->has('maxquantity'))
                    <span class="text-danger">{{ $errors->first('maxquantity') }}</span>
                    @endif
                </div>
                <div class="form-group col-md-3">
                    <label>Stock Qty</label>
                    <input type="text" name="stock_qty" id="stock_qty" tabindex="15" class="form-control"
                        value="{{ old('stock_qty', $stockQty) }}" {{ !$stockEditable ? 'disabled' : '' }}>
                    @if($isUsed)
                        <small class="text-muted">Stock is locked because this product has inventory transactions. Use the related transaction or stock adjustment workflow.</small>
                    @elseif($inventoryMode === 'mrp')
                        <small class="text-muted">Stock is calculated from MRP lots and must be changed through inventory transactions.</small>
                    @elseif($batchMode)
                        <small class="text-muted">Stock for batch-managed products must be changed through inventory transactions with batch details.</small>
                    @endif
                    <span class="text-danger"></span>
                </div>
                <div class="form-group col-md-3">
                    <label>Batch Managed</label>
                    <div class="custom-control custom-switch mt-2">
                        @if (!$isUsed)
                            <input type="hidden" name="is_batch_managed" value="0">
                        @endif
                        <input type="checkbox" class="custom-control-input" id="is_batch_managed"
                            name="is_batch_managed" value="1" {{ old('is_batch_managed', $product->is_batch_managed ?? false) ? 'checked' : '' }}
                            {{ $isUsed ? 'disabled' : '' }}>
                        <label class="custom-control-label" for="is_batch_managed">Track batches and expiry</label>
                        @if ($isUsed)
                            <input type="hidden" name="is_batch_managed" value="{{ $product->is_batch_managed ? 1 : 0 }}">
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row"> 
        <div class="col-md-6">     
            <div class="card card-navy">
                <div class="card-header">
                    <h3 class="card-title"><i class="far fa-file-alt"></i> Classifications</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Brand</label>
                        <select name="brandid" id="brand_id" tabindex="16" class="form-control">
                            <option value="">Select Brand</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}" {{ isset($product->brandid) && $product->brandid == $brand->id ? 'selected' : '' }}>
                                    {{ $brand->brand }}
                                </option>
                            @endforeach
                        </select> 
                        @if ($errors->has('brandid'))
                            <span class="text-danger">{{ $errors->first('brandid') }}</span> 
                        @endif
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="categoryid" id="category_id" tabindex="17" class="form-control">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ isset($product->categoryid) && $product->categoryid == $category->id ? 'selected' : '' }}>
                                    {{ $category->category }}
                                </option>
                            @endforeach
                        </select>
                        @if ($errors->has('categoryid'))
                            <span class="text-danger">{{ $errors->first('categoryid') }}</span> 
                        @endif
                    </div>
                    <div class="form-group">
                        <label>Sub-Category</label>
                        <select name="subcategoryid" id="subcategory_id" tabindex="18" class="form-control">
                            <option value="">Select Sub-Category</option>
                        </select> 
                        @if ($errors->has('subcategoryid'))
                            <span class="text-danger">{{ $errors->first('subcategoryid') }}</span> 
                        @endif
                    </div>
                    <div class="form-group">
                        <label>Group</label>
                        <select name="groupid" id="group_id" tabindex="19" class="form-control">
                            <option value="">Select Group</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" {{ isset($product->groupid) && $product->groupid == $group->id ? 'selected' : '' }}>
                                {{ $group->groups }}</option>
                            @endforeach
                        </select> 
                        @if ($errors->has('groupid'))
                            <span class="text-danger">{{ $errors->first('groupid') }}</span> 
                        @endif
                    </div>
                    <div class="form-group">
                        <label>Type<sup>*</sup></label>
                        <select name="typeid" id="type_id" tabindex="20" class="form-control" {{ $isUsed ? 'disabled' : '' }}>
                            <option value="2"{{ isset($product->typeid) && $product->typeid == 2 ? 'selected' : '' }}>Stockable</option>
                            <option value="1"{{ isset($product->typeid) && $product->typeid == 1 ? 'selected' : '' }}>Non-Stockable</option>
                            <option value="3"{{ isset($product->typeid) && $product->typeid == 3 ? 'selected' : '' }}>Service</option>
                        </select> 
                        @if ($errors->has('typeid'))
                            <span class="text-danger">{{ $errors->first('typeid') }}</span> 
                        @endif 
                        @if ($isUsed)
                            <input type="hidden" name="typeid" value="{{ $product->typeid }}"> 
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">   
            <div class="card card-navy">
                <div class="card-header">
                    <h3 class="card-title"><i class="far fa-file-alt"></i> Images</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="customFile">Image(200 X 50)</label>
                        <div id="photo_preview" class="mt-2">
                            @if(!empty($product->product_image))
                                <img src="{{tenant_asset('product_logos/'.$product->product_image   )}}" alt="Product Photo" style="width: 200px; height: 100px;">
                            @else
                                <img src="{{asset('uploads/avatar.png')}}" alt="Product Photo" style="width: 150px; height: 150px;">
                            @endif
                        </div>
                        <div class="input-group mt-2">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="customFile" tabindex="21" name="product_image" accept="image/png, image/jpeg, image/jpg, image/gif, image/webp">
                                <label class="custom-file-label" for="customFile">Choose file</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> 
    </div>
</div>
<div class="col-12">
    <div class="card">
        <div class="card-footer" align="center">
            <button type="submit" id="submitBtn" tabindex="22" class="btn btn-primary  btn-flat"><i class="fas fa-save"></i> Save</button>
            <button type="reset" value="Reset" id="resetbtn" tabindex="23" class="btn btn-secondary  btn-flat"><i class="fas fa-undo-alt"></i> Reset</button>
        </div>
    </div>
</div>
</form>

@endsection

@section('scripts')
<script>
$(function () {
    
    bsCustomFileInput.init();
    $('#customFile').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass("selected").html(fileName);
        
        var file = this.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#photo_preview').html('<img src="' + e.target.result + '" alt="Product Photo" style="width: 150px; height: 150px;">');
            }
            reader.readAsDataURL(file);
        }
    });
    $.validator.setDefaults({
        submitHandler: function (form) {
            $('#submitBtn').prop('disabled', true); // Disable the submit button
            form.submit();
        }
    });
});
</script>

<script>
    $(document).ready(function() {
        var selectedBrandId = "{{ isset($product->brandid) ? $product->brandid : '' }}";
        var selectedCategoryId = "{{ isset($product->categoryid) ? $product->categoryid : '' }}";
        var selectedSubcategoryId = "{{ isset($product->subcategoryid) ? $product->subcategoryid : '' }}";

        function loadSubcategories(categoryId) {
            $('#subcategory_id').html('<option value="">Select Sub-Category</option>');
            if (categoryId) {
                $.ajax({
                    url: '/get-subcategories/' + categoryId,
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        $.each(data, function(key, value) {
                            var selected = (value.id == selectedSubcategoryId) ? 'selected' : '';
                            $('#subcategory_id').append('<option value="' + value.id + '" ' + selected + '>' + value.subcategory + '</option>');
                        });
                    }
                });
            }
        }

    // Trigger subcategory loading when category changes
        $('#category_id').change(function() {
            var categoryId = $(this).val();
            loadSubcategories(categoryId);
        });

    // Auto-load values if editing
        if (selectedBrandId) {
            $('#brand_id').val(selectedBrandId);
        }
        if (selectedCategoryId) {
            $('#category_id').val(selectedCategoryId);
            loadSubcategories(selectedCategoryId);
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mrpInput = document.getElementById('mrp');
        const marginInput = document.getElementById('margin');
        const amtMarginInput = document.getElementById('amt_margin');
        const priceInput = document.getElementById('price');

        // Helper to safely get float values
        function getFloat(val) {
            return parseFloat(val) || 0;
        }

        function updateFromMargin() {
            const mrp = getFloat(mrpInput.value);
            const margin = getFloat(marginInput.value);
            if (mrp > 0) {
                const amt_margin = (mrp * margin) / 100;
                const price = mrp - amt_margin;
                amtMarginInput.value = amt_margin.toFixed(2);
                priceInput.value = price.toFixed(2);
            }
        }

        function updateFromAmtMargin() {
            const mrp = getFloat(mrpInput.value);
            const amt_margin = getFloat(amtMarginInput.value);
            if (mrp > 0) {
                const margin = (amt_margin * 100) / mrp;
                const price = mrp - amt_margin;
                marginInput.value = margin.toFixed(2);
                priceInput.value = price.toFixed(2);
            }
        }

        function updateFromPrice() {
            const mrp = getFloat(mrpInput.value);
            const price = getFloat(priceInput.value);
            if (mrp > 0 && price <= mrp) {
                const amt_margin = mrp - price;
                const margin = (amt_margin * 100) / mrp;
                amtMarginInput.value = amt_margin.toFixed(2);
                marginInput.value = margin.toFixed(2);
            }
        }

        // Events
        marginInput.addEventListener('input', updateFromMargin);
        amtMarginInput.addEventListener('input', updateFromAmtMargin);
        priceInput.addEventListener('input', updateFromPrice);
    });
</script>
<script>
    $('#is_batch_managed').on('change', function () {
        @if($batchMode)
        $('#stock_qty').prop('disabled', this.checked).val('');
        @endif
    });
</script>
<script>
    document.getElementById('generate_barcode_btn').addEventListener('click', function () {
        const randomBarcode = Math.floor(1000000000 + Math.random() * 9000000000).toString(); // 10-digit string
        document.getElementById('bar_code').value = randomBarcode;
    });
</script>
@endsection
