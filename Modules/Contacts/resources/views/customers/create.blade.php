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
                    <li class="breadcrumb-item"><a href="{{route('customers.index')}}">Customer</a></li>
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
        <h3 class="card-title"><i class="fas fa-user-tag"></i> {{$page_title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('customers.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
        @if ($errors->any())
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validation Error');
                });
            </script>
        @endif
    </div>
</div>
<div class="card card-navy">
    <div class="card-header">
        <h3 class="card-title"><i class="far fa-file-alt"></i> Basic Details</h3>
    </div>
    <form id="EditCustomer" method="post" action="{{ route('customers.update') }}" enctype="multipart/form-data">
    @csrf
        <input type="hidden" id="id" name="id" value="{{ $customer->id ?? '' }}">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Customer Name<sup>*</sup></label>
                        <input type="text" name="customer" id="customer_name" tabindex="1" class="form-control" value="{{ !empty($customer->customer) ? $customer->customer : '' }}">
                        @if ($errors->has('customer'))
                          <span class="text-danger">{{ $errors->first('customer') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Phone<sup>*</sup></label>
                        <input type="text" name="phone" id="customer_phone" tabindex="2" class="form-control" value="{{ !empty($customer->phone) ? $customer->phone : '' }}">
                        @if ($errors->has('phone'))
                          <span class="text-danger">{{ $errors->first('phone') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" id="customer_address" tabindex="3" class="form-control" rows="3">{{ !empty($customer->address) ? $customer->address : '' }}</textarea>
                    </div>
                </div> 
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="customer_email" tabindex="4" class="form-control" value="{{ !empty($customer->email) ? $customer->email : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>GST No.</label>
                        <input type="text" name="gstin_no" id="customer_gst" tabindex="5" class="form-control" value="{{ !empty($customer->gstin_no) ? $customer->gstin_no : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Opening Balance</label>
                        <input type="text" name="op_balance" id="op_balance" tabindex="6" class="form-control" value="{{ !empty($customer->op_balance) ? $customer->op_balance : '0.00' }}" @if($hasLedger) readonly @endif>
                    </div>
                </div>
                <div class="col-md-6">
					<div class="form-group">
						<label for="customFile">Image(200 X 50)</label>
                        	<div class="input-group">
							<div class="custom-file">
                                <input type="file" class="custom-file-input" id="customFile" tabindex="7" name="customer_image" accept="image/png, image/jpeg, image/jpg, image/gif, image/webp">
                                <label class="custom-file-label" for="customFile">Choose file</label>
							</div>
                        </div>
						<div id="photo_preview" class="mt-2">
						    @if(!empty($customer->customer_image))
						        <img src="{{tenant_asset('customer_logos/'.$customer->customer_image)}}" alt="Customer Photo" style="width: 200px; height: 100px;">
						    @else
                                <img src="{{asset('uploads/avatar.png')}}" alt="Customer Photo" style="width: 200px; height: 50px;">
                            @endif
                        </div><br>
					</div>
				</div>
            </div>
        </div>
        <div class="card-footer" align="center">
            <button type="submit" id="submitBtn" tabindex="8" class="btn btn-primary btn-flat"><i class="fas fa-save"></i> Save</button>
             <button type="reset" value="Reset" id="resetbtn" tabindex="9" class="btn btn-secondary  btn-flat"><i class="fas fa-undo-alt"></i> Reset</button>
        </div>
    </form>
</div>
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
                $('#photo_preview').html('<img src="' + e.target.result + '" alt="Customer Photo" style="width: 150px; height: 150px;">');
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
@endsection