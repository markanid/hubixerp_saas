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
    <form id="EditUser" method="post" action="{{ route('users.update', ['id' => $user->id]) }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="user_id" name="user_id" value="{{ !empty($user) ? $user->id : '' }}">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Name<sup>*</sup></label>
                        <input type="text" name="user_name" id="user_name" tabindex="1" class="form-control" value="{{ !empty($user)  ? $user->user_name : '' }}">
                        @if ($errors->has('user_name'))
                          <span class="text-danger">{{ $errors->first('user_name') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email ID<sup>*</sup></label>
                        <input type="email" name="email" id="email" tabindex="2" class="form-control" autocomplete="off" value="{{ !empty($user) ? $user->email : '' }}">
                        @if ($errors->has('email'))
                          <span class="text-danger">{{ $errors->first('email') }}</span>
                        @endif
                    </div>
                </div>
               
                <div class="col-md-6">
                    <div class="form-group">
                        <label>User Type<sup>*</sup></label>
                        <select name="user_role" id="user_role" tabindex="3" class="form-control">
                            <option value=""></option>
                            <option value="User" {{ !empty($user) && $user->user_role == 'User' ? 'selected' : '' }}>User</option>
                            <option value="Administrator" {{ !empty($user) && $user->user_role == 'Administrator' ? 'selected' : '' }}>Administrator</option>
                            <option value="Salesman" {{ !empty($user) && $user->user_role == 'Salesman' ? 'selected' : '' }}>Salesman</option>
                            <option value="Accountant" {{ !empty($user) && $user->user_role == 'Accountant' ? 'selected' : '' }}>Accountant</option>
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
								<input type="file" class="custom-file-input" id="customFile" tabindex="4" name="user_logo">
								<label class="custom-file-label" for="customFile">Choose file</label>
							</div>
							
							@if ($errors->has('user_logo'))
                              <span class="text-danger">{{ $errors->first('user_logo') }}</span>
                            @endif
						</div>
						<div id="photo_preview" class="mt-2">
						    @if(!empty($user) && !empty($user->user_logo))
						        <img src="{{tenant_asset('user_logos/'.$user->user_logo)}}" alt="User Photo" style="width: 150px; height: 150px;">
						    @else
                                <img src="{{asset('uploads/avatar.png')}}" alt="User Photo" style="width: 150px; height: 150px;">
                            @endif
                        </div>
					</div>
				</div>
				<input type="hidden" name="old_photo" value="{{ !empty($user) ? $user->user_logo : '' }}">
            </div>
        </div>
        <div class="card-footer" align="center">
            <button type="submit" id="submitBtn" tabindex="5" class="btn btn-primary btn-flat"><i class="fas fa-save"></i> Save</button>
             <button type="reset" value="Reset" id="resetbtn" tabindex="6" class="btn btn-secondary  btn-flat"><i class="fas fa-undo-alt"></i> Reset</button>
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
                $('#photo_preview').html('<img src="' + e.target.result + '" alt="Employee Photo" style="width: 150px; height: 150px;">');
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