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
                    <li class="breadcrumb-item"><a href="{{route('users.index')}}">Users</a></li>
                    <li class="breadcrumb-item active">{{$title}}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-user-cog"></i> {{$title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('users.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
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
    <form id="addUser" method="post" action="{{ route('users.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Name<sup>*</sup></label>
                        <input type="text" name="user_name" id="user_name" tabindex="1" class="form-control">
                        @if ($errors->has('user_name'))
                          <span class="text-danger">{{ $errors->first('user_name') }}</span>
                        @endif
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email ID<sup>*</sup></label>
                        <input type="email" name="email" id="email" tabindex="2" class="form-control" autocomplete="on">
                        @if ($errors->has('email'))
                          <span class="text-danger">{{ $errors->first('email') }}</span>
                        @endif
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Password<sup>*</sup></label>
                        <input type="password" name="password" id="password" tabindex="3" class="form-control" autocomplete="off">
                        @if ($errors->has('password'))
                          <span class="text-danger">{{ $errors->first('password') }}</span>
                        @endif
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Confirm Password<sup>*</sup></label>
                        <input type="password" name="password_confirmation" id="password_confirmation" tabindex="4" class="form-control" autocomplete="off">
                        @if ($errors->has('password_confirmation'))
                          <span class="text-danger">{{ $errors->first('password_confirmation') }}</span>
                        @endif
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label>User Type<sup>*</sup></label>
                        <select name="user_role" id="user_role" tabindex="5" class="form-control">
                            <option value=""></option>
                            <option value="User"> User</option>
                            <option value="Administrator">Administrator</option>
                            <option value="Salesman">Salesman</option>
                            <option value="Accountant">Accountant</option>
                        </select>
                        @if ($errors->has('user_role'))
                          <span class="text-danger">{{ $errors->first('user_role') }}</span>
                        @endif
                    </div>
                </div>

                <div class="col-md-6">
                	<div class="form-group">
    					<label for="customFile">User Photo(150x150)</label>
    				
    					<div class="input-group">
    						<div class="custom-file">
    							<input type="file" class="custom-file-input" id="customFile" tabindex="6" name="user_logo">
    							<label class="custom-file-label" for="customFile">Choose file</label>
    						</div>
    						
    						@if ($errors->has('user_logo'))
                              <span class="text-danger">{{ $errors->first('user_logo') }}</span>
                            @endif
    					</div>
    					<div id="photo_preview" class="mt-2">
                            <img src="{{asset('uploads/avatar.png')}}" alt="User Photo" style="width: 150px; height: 150px;">
                        </div>
    				</div>
                </div>
            </div>
        </div>
        <div class="card-footer" align="center">
            <button type="submit" id="submitBtn" tabindex="7" class="btn btn-primary  btn-flat"><i class="fas fa-save"></i> Save</button>
            <button type="reset" value="Reset" id="resetbtn" tabindex="8" class="btn btn-secondary  btn-flat"><i class="fas fa-undo-alt"></i> Reset</button>
            
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
$(function () {
    let today = new Date();
    let formattedDate = ("0" + today.getDate()).slice(-2) + '/' + ("0" + (today.getMonth() + 1)).slice(-2) + '/' + today.getFullYear();
    $('#date').val(formattedDate);
    
    bsCustomFileInput.init();
    $('#customFile').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass("selected").html(fileName);
        
        var file = this.files[0];
        if (file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#photo_preview').html('<img src="' + e.target.result + '" alt="User Photo" style="width: 150px; height: 150px;">');
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