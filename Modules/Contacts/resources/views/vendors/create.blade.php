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
                    <li class="breadcrumb-item"><a href="{{route('vendors.index')}}">Vendor</a></li>
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
        <h3 class="card-title"><i class="fas fa-user-tie"></i> {{$page_title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('vendors.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
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
    <form id="EditCompany" method="post" action="{{ route('vendors.update') }}" enctype="multipart/form-data">
    @csrf
        <input type="hidden" id="id" name="id" value="{{ $vendor->id ?? '' }}">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Vendor Name<sup>*</sup></label>
                        <input type="text" name="cp_name" id="vendor_name" tabindex="1" class="form-control" value="{{ !empty($vendor->cp_name) ? $vendor->cp_name : '' }}">
                        @if ($errors->has('cp_name'))
                          <span class="text-danger">{{ $errors->first('cp_name') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Phone<sup>*</sup></label>
                        <input type="text" name="cp_phone" id="vendor_phone" tabindex="2" class="form-control" value="{{ !empty($vendor->cp_phone) ? $vendor->cp_phone : '' }}">
                        @if ($errors->has('cp_phone'))
                          <span class="text-danger">{{ $errors->first('cp_phone') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="cp_address" id="vendor_address" tabindex="3" class="form-control" rows="3">{{ !empty($vendor->cp_address) ? $vendor->cp_address : '' }}</textarea>
                    </div>
                </div> 
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="cp_email" id="vendor_email" tabindex="4" class="form-control" value="{{ !empty($vendor->cp_email) ? $vendor->cp_email : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>GST No.</label>
                        <input type="text" name="cp_gst_no" id="vendor_gst" tabindex="5" class="form-control" value="{{ !empty($vendor->cp_gst_no) ? $vendor->cp_gst_no : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Website</label>
                        <input type="text" name="cp_website" id="vendor_website" tabindex="6" class="form-control" value="{{ !empty($vendor->cp_website) ? $vendor->cp_website : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" name="cp_cname" id="vendor_cname" tabindex="7" class="form-control" value="{{ !empty($vendor->cp_cname) ? $vendor->cp_cname : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Contact Person Number</label>
                        <input type="text" name="cp_cphone" id="vendor_cphone" tabindex="8" class="form-control" value="{{ !empty($vendor->cp_cphone) ? $vendor->cp_cphone : '' }}">
                        @if ($errors->has('cp_cphone'))
                          <span class="text-danger">{{ $errors->first('cp_cphone') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Opening Balance</label>
                        <input type="text" name="op_balance" id="op_balance" tabindex="9" class="form-control" value="{{ !empty($vendor->op_balance) ? $vendor->op_balance : '0.00' }}" @if($hasLedger) readonly @endif>
                    </div>
                </div>
                <div class="col-md-6">
					<div class="form-group">
						<label for="customFile">Image(200 X 50)</label>
                        	<div class="input-group">
							<div class="custom-file">
                                <input type="file" class="custom-file-input" id="customFile" tabindex="10" name="cp_logo" accept="image/png, image/jpeg, image/jpg, image/gif, image/webp">
                                <label class="custom-file-label" for="customFile">Choose file</label>
							</div>
                        </div>
						<div id="photo_preview" class="mt-2">
						    @if(!empty($vendor->cp_logo))
						        <img src="{{tenant_asset('vendor_logos/'.$vendor->cp_logo)}}" alt="Suppier Photo" style="width: 200px; height: 50px;">
						    @else
                                <img src="{{asset('uploads/avatar.png')}}" alt="Suppier Photo" style="width: 200px; height: 50px;">
                            @endif
                        </div><br>
					</div>
				</div>
            </div>
        </div>
        <div class="card-footer" align="center">
            <button type="submit" id="submitBtn" tabindex="11" class="btn btn-primary btn-flat"><i class="fas fa-save"></i> Save</button>
             <button type="reset" value="Reset" id="resetbtn" tabindex="12" class="btn btn-secondary  btn-flat"><i class="fas fa-undo-alt"></i> Reset</button>
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
                $('#photo_preview').html('<img src="' + e.target.result + '" alt="Company Photo" style="width: 150px; height: 150px;">');
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