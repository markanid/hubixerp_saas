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
                    <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{route('excategory.index')}}">Expense Category</a></li>
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
        <h3 class="card-title"><i class="fas fa-wallet"></i> {{$title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('excategory.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
    </div>
    <form id="editExcategory" method="post" action="{{ route('excategory.update') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="id" name="id" value="{{ !empty($ex_category) ? $ex_category->id : '' }}">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Category<sup>*</sup></label>
                        <input type="text" name="category" id="category" tabindex="2" class="form-control" value="{{ !empty($ex_category) ? $ex_category->category : '' }}">
                        @if ($errors->has('category'))
                          <span class="text-danger">{{ $errors->first('category') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer" align="center">
            <button type="submit" id="submitBtn" tabindex="8" class="btn btn-primary  btn-flat"><i class="fas fa-save"></i> Save</button>
            <button type="reset" value="Reset" id="resetbtn" tabindex="9" class="btn btn-secondary  btn-flat"><i class="fas fa-undo-alt"></i> Reset</button>
            
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
$(function () {
    $.validator.setDefaults({
        submitHandler: function (form) {
            $('#submitBtn').prop('disabled', true); // Disable the submit button
            form.submit();
        }
    });
  
    $('#editExcategory').validate({
        rules: {
            category: {
                required: true,
            },
        },
        messages: {
            category: {
                required: "Please enter the category",
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