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
                    <li class="breadcrumb-item"><a href="{{route('banks.index')}}">Banks</a></li>
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
        <h3 class="card-title"><i class="fas fa-landmark"></i> {{$page_title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('banks.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
        @if ($errors->any())
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validation Error');
                });
            </script>
        @endif
    </div>  
    <form id="addProduct" method="post" action="{{ route('banks.update') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="id" name="bk_id" value="{{ $bank->bk_id ?? '' }}">
        <div class="card-body">
            @if(isset($bank->bk_id) && $hasLedger)
                <div class="alert alert-warning">
                    <strong>Note:</strong> This paymode already has transactions. 
                    Opening balance cannot be changed.
                </div>
            @endif
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Bank Name<sup>*</sup></label>
                        <input type="text" name="bk_bank" id="bank_name" tabindex="1" class="form-control" value="{{ old('bk_bank', $bank->bk_bank ?? '') }}">
                        @if ($errors->has('bk_bank'))
                          <span class="text-danger">{{ $errors->first('bk_bank') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Account No.<sup>*</sup></label>
                        <input type="text" name="bk_account" id="account_number" tabindex="2" class="form-control" value="{{ old('bk_account', $bank->bk_account ?? '') }}">
                        @if ($errors->has('bk_account'))
                          <span class="text-danger">{{ $errors->first('bk_account') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Branch Name<sup>*</sup></label>
                        <input type="text" name="bk_branch" id="branch_name" tabindex="3" class="form-control" value="{{ old('bk_branch', $bank->bk_branch ?? '') }}">
                        @if ($errors->has('bk_branch'))
                          <span class="text-danger">{{ $errors->first('bk_branch') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>IFSC Code<sup>*</sup></label>
                        <input type="text" name="bk_ifsc" id="branch_ifsc" tabindex="5" class="form-control" value="{{ old('bk_ifsc', $bank->bk_ifsc ?? '') }}">
                        @if ($errors->has('bk_ifsc'))
                          <span class="text-danger">{{ $errors->first('bk_ifsc') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Opening Balance<sup>*</sup></label>
                        <input type="text" name="bk_opbalance" id="opening_balance" tabindex="6" class="form-control" value="{{ $hasLedger ? $bank->bk_opbalance : old('bk_opbalance', $bank->bk_opbalance ?? '') }}" @if($hasLedger) readonly @endif>
                        @if ($errors->has('bk_opbalance'))
                          <span class="text-danger">{{ $errors->first('bk_opbalance') }}</span>
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
});
</script>
@endsection
