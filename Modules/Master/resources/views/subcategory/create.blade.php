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
                    <li class="breadcrumb-item"><a href="{{route('subcategory.index')}}">Sub Category</a></li>
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
        <h3 class="card-title"><i class="fas fa-tags"></i> {{$page_title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('subcategory.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
        @if ($errors->any())
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validation Error');
                });
            </script>
        @endif
    </div>
    <form id="addPCat" method="post" action="{{ route('subcategory.update') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="id" name="id" value="{{ $subcategory->id ?? '' }}">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Category<sup>*</sup></label>
                        <select name="categoryid" id="category_id" tabindex="1" class="form-control">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ isset($subcategory->categoryid) && $subcategory->categoryid == $category->id ? 'selected' : '' }}>
                                    {{ $category->category }}
                                </option>
                            @endforeach
                        </select>
                        @if ($errors->has('categoryid'))
                            <span class="text-danger">{{ $errors->first('categoryid') }}</span>
                        @endif
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Sub Category<sup>*</sup></label>
                        <input type="text" name="subcategory" id="sub_category" tabindex="2" class="form-control" value="{{ !empty($subcategory->subcategory) ? $subcategory->subcategory : '' }}">
                        @if ($errors->has('subcategory'))
                          <span class="text-danger">{{ $errors->first('subcategory') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer" align="center">
            <button type="submit" id="submitBtn" tabindex="3" class="btn btn-primary  btn-flat"><i class="fas fa-save"></i> Save</button>
            <button type="reset" value="Reset" id="resetbtn" tabindex="4" class="btn btn-secondary  btn-flat"><i class="fas fa-undo-alt"></i> Reset</button>
            
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
  
    $('#addPCat').validate({
        rules: {
            category: {
                required: true,
            },
            sub_category: {
                required: true,
            },
        },
        messages: {
            sub_category: {
                required: "Please enter the sub category",
            },
            category: {
                required: "Please select the category",
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