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
                    <li class="breadcrumb-item"><a href="{{route('company.index')}}">Company</a></li>
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
        <h3 class="card-title"><i class="far fa-building"></i> {{$page_title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('company.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
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
    <form id="EditCompany" method="post" action="{{ route('company.update') }}" enctype="multipart/form-data">
    @csrf
        <input type="hidden" id="id" name="id" value="{{ $id ?? '' }}">
        <!-- <input type="hidden" id="id" name="id" value="{{ !empty($id) ? $id : '' }}"> -->
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Company<sup>*</sup></label>
                        <input type="text" name="company" id="company" tabindex="1" class="form-control" value="{{ !empty($name) ? $name : '' }}">
                        @if ($errors->has('company'))
                          <span class="text-danger">{{ $errors->first('company') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Tag Line</label>
                        <input type="text" name="tags" id="tags" tabindex="2" class="form-control" value="{{ !empty($tags) ? $tags : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" id="phone" tabindex="3" class="form-control" value="{{ !empty($phone_number) ? $phone_number : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="email" tabindex="4" class="form-control" autocomplete="off" value="{{ !empty($email) ? $email : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" id="address" tabindex="5" class="form-control" rows="3">{{ !empty($address) ? $address : '' }}</textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>GST.No</label>
                        <input type="text" name="gst_no" id="gst_no" tabindex="6" class="form-control" value="{{ !empty($gst_number) ? $gst_number : '' }}">
                    </div>
                </div> 
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Lic.No</label>
                        <input type="text" name="licence_no" id="licence_no" tabindex="7" class="form-control" value="{{ !empty($license_number) ? $license_number : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Website</label>
                        <input type="text" name="website" id="website" tabindex="8" class="form-control" value="{{ !empty($website_url) ? $website_url : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Bank  Name</label>
                        <input type="text" name="bank_name" id="bank_name" tabindex="9" class="form-control" value="{{ !empty($bank_name) ? $bank_name : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Bank IFSC</label>
                        <input type="text" name="bank_ifsc" id="bank_ifsc" tabindex="10" class="form-control" value="{{ !empty($bank_ifsc) ? $bank_ifsc : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Bank Acno.</label>
                        <input type="text" name="bank_acno" id="bank_acno" tabindex="11" class="form-control" value="{{ !empty($bank_acno) ? $bank_acno : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Bank Branch</label>
                        <input type="text" name="bank_branch" id="bank_branch" tabindex="12" class="form-control" value="{{ !empty($bank_branch) ? $bank_branch : '' }}">
                    </div>
                </div>
                <div class="col-md-6">
					<div class="form-group">
						<label for="customFile">Image(200 X 50)</label>
                        <div class="input-group">
							<div class="custom-file">
                                <input type="file" class="custom-file-input" id="customFile" tabindex="13" name="logo" accept="image/png, image/jpeg, image/jpg, image/gif, image/webp">
                                <label class="custom-file-label" for="customFile">Choose file</label>
							</div>
                        </div>
						<div id="photo_preview" class="mt-2">
						    @if(!empty($logo))
						        <img src="{{tenant_asset('company_logos/'.$logo)}}" alt="Company Photo" style="width: 200px; height: 100px;">
						    @else
                                <img src="{{asset('uploads/demo_company_logo.png')}}" alt="Company Photo" style="width: 200px; height: 100px;">
                            @endif
                        </div><br>
					</div>
				</div>
				<input type="hidden" name="old_photo" value="{{ !empty($logo) ? $logo : '' }}">
                
                <div class="col-md-6">
					<div class="form-group">
						<label for="customFile">QR-Code(200 X 50)</label>
                        <div class="input-group">
							<div class="custom-file">
                                <input type="file" class="custom-file-input" id="customFile1" tabindex="14" name="qr_code" accept="image/png, image/jpeg, image/jpg, image/gif, image/webp">
                                <label class="custom-file-label" for="customFile1">Choose file</label>
							</div>
                        </div>
						<div id="qr_preview" class="mt-2">
						    @if(!empty($qr_code))
						        <img src="{{tenant_asset('company_qr_codes/'.$qr_code)}}" alt="Company QR Code" style="width: 100px; height: 100px;">
						    @else
                                <img src="{{asset('uploads/demo_company_qr.png')}}" alt="Company QR Code" style="width: 100px; height: 100px;">
                            @endif
                        </div><br>
					</div>
				</div>
				<input type="hidden" name="old_qr_code" value="{{ !empty($qr_code) ? $qr_code : '' }}">
            </div>

        </div>
        <div class="card-footer" align="center">
            <button type="submit" id="submitBtn" tabindex="15" class="btn btn-primary btn-flat"><i class="fas fa-save"></i> Save</button>
             <button type="reset" value="Reset" id="resetbtn" tabindex="16" class="btn btn-secondary  btn-flat"><i class="fas fa-undo-alt"></i> Reset</button>
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
    $('#customFile1').on('change', function() {
        var fileName1 = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass("selected").html(fileName1);
        
        var file = this.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#qr_preview').html('<img src="' + e.target.result + '" alt="Company QR Code" style="width: 150px; height: 150px;">');
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
