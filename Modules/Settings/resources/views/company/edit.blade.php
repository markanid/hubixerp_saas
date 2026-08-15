@extends('admin.layout')

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
                    <li class="breadcrumb-item"><a href="{{route('company.view')}}">Company</a></li>
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
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('company.view')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
    </div>
    <form id="EditCompany" method="post" action="{{ route('company.update') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="id" name="id" value="{{ !empty($id) ? $id : '' }}">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Company<sup>*</sup></label>
                        <input type="text" name="name" id="name" tabindex="2" class="form-control" value="{{ !empty($name) ? $name : '' }}">
                        @if ($errors->has('name'))
                          <span class="text-danger">{{ $errors->first('name') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Tag Line</label>
                        <input type="text" name="tags" id="tags" tabindex="5" class="form-control" value="{{ !empty($tags) ? $tags : '' }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" id="phone" tabindex="5" class="form-control" value="{{ !empty($phone) ? $phone : '' }}">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="email" tabindex="4" class="form-control" autocomplete="off" value="{{ !empty($email) ? $email : '' }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>GST.No</label>
                        <input type="text" name="gst_no" id="gst_no" tabindex="2" class="form-control" value="{{ !empty($gst_no) ? $gst_no : '' }}">
                    </div>
                </div> 
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Lic.No</label>
                        <input type="text" name="licence_no" id="licence_no" tabindex="5" class="form-control" value="{{ !empty($licence_no) ? $licence_no : '' }}">
                    </div>
                </div>
            </div>

            <div class="row">
                
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" id="address" tabindex="2" class="form-control" value="{{ !empty($address) ? $address : '' }}">
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Website</label>
                        <input type="text" name="website" id="website" tabindex="5" class="form-control" value="{{ !empty($website) ? $website : '' }}">
                    </div>
                </div>

            
                
                
            
                <div class="col-md-4">
					<div class="form-group">
						<label for="customFile">Image(200 X 50)</label>
					
						
                        	<div class="input-group">
							<div class="custom-file">
								<input type="file" class="custom-file-input" id="customFile" tabindex="7" name="image">
								<label class="custom-file-label" for="customFile">Choose file</label>
							</div>
							
							@if ($errors->has('image'))
                              <span class="text-danger">{{ $errors->first('image') }}</span>
                            @endif
						</div>
						<div id="photo_preview" class="mt-2">
						    @if(!empty($image))
						        <img src="{{asset('uploads/company/'.$image)}}" alt="Company Photo" style="width: 200px; height: 50px;">
						    @else
                                <img src="{{asset('uploads/users/avatar.png')}}" alt="Company Photo" style="width: 200px; height: 50px;">
                            @endif
                        </div><br>
					</div>
				</div>
				<input type="hidden" name="old_photo" value="{{ !empty($image) ? $image : '' }}">
            </div>

        </div>
        <div class="card-footer" align="center">
            <button type="submit" id="submitBtn" tabindex="8" class="btn btn-primary btn-flat"><i class="fas fa-save"></i> Save</button>
             <button type="reset" value="Reset" id="resetbtn" tabindex="p" class="btn btn-secondary  btn-flat"><i class="fas fa-undo-alt"></i> Reset</button>
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
  
    $('#EditCompany').validate({
        rules: {
            name: {
                required: true,
            },
            email: {
                email: true,
            },
        },
        messages: {
            name: {
                required: "Please enter the Company Name",
            },
            email: {
                email: "Please enter a valid email address"
            },
        },
        errorElement: 'span',
        errorPlacement: function (error, element) {
            error.addClass('invalid-feedback');
            element.closest('.form-group').append(error);
        },
        highlight: function (element, errorClass, validClass) {
            $(element).addClass('is-invalid');
        },
        unhighlight: function (element, errorClass, validClass) {
            $(element).removeClass('is-invalid');
        }
    });
});
</script>
@endsection