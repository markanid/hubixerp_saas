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
                    <li class="breadcrumb-item"><a href="{{route('loans.index')}}">Loan</a></li>
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
        <h3 class="card-title"><i class="fas fa-hand-holding-usd"></i> {{$page_title}}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('loans.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
    </div>
    @if ($errors->any())
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validation Error');
            });
        </script>
    @endif
    <form id="addLoan" method="post" action="{{ route('loans.store') }}">
        @csrf
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Date<sup>*</sup></label>
                        <div class="input-group date" id="loan_date_picker" data-target-input="nearest">
                            <input type="text" name="loan_date" id="loan_date" tabindex="1" class="form-control datetimepicker-input" data-target="#loan_date_picker" value="{{ old('loan_date', now()->format('d/m/Y')) }}" />
                            <div class="input-group-append" data-target="#loan_date_picker" data-toggle="datetimepicker">
                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Loan No.<sup>*</sup></label>
                        <input type="text" name="loan_vno" class="form-control" value="{{ old('loan_vno', $voucher_no) }}" readonly tabindex="-1">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Loan Type<sup>*</sup></label>
                        <select name="loan_type" id="loan_type" tabindex="2" class="form-control" required>
                            <option value="liability" {{ old('loan_type', 'liability') == 'liability' ? 'selected' : '' }}>Liability</option>
                            <option value="asset" {{ old('loan_type') == 'asset' ? 'selected' : '' }}>Asset</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6" id="vendor_group">
                    <div class="form-group">
                        <label>Financier/Beneficiary<sup>*</sup></label>
                        <select name="vendor_id" id="vendor_id" tabindex="3" class="form-control">
                            <option value="">--Select Financier/Beneficiary--</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}" {{ old('vendor_id') == $vendor->id ? 'selected' : '' }}>{{ $vendor->cp_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6" id="customer_group" style="display: none;">
                    <div class="form-group">
                        <label>Financier/Beneficiary<sup>*</sup></label>
                        <select name="customer_id" id="customer_id" tabindex="3" class="form-control">
                            <option value="">--Select Financier/Beneficiary--</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->customer }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Bank<sup>*</sup></label>
                        <select name="bank_id" tabindex="4" class="form-control" required>
                            <option value="">--Select Bank--</option>
                            @foreach ($banks as $bank)
                                <option value="{{ $bank->bk_id }}" {{ old('bank_id') == $bank->bk_id ? 'selected' : '' }}>{{ $bank->bk_bank }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Amount<sup>*</sup></label>
                        <input type="text" name="amount" tabindex="5" class="form-control" value="{{ old('amount') }}" required>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer" align="center">
            <button type="submit" id="saveLoan" tabindex="6" class="btn btn-primary btn-flat"><i class="fas fa-save"></i> Save</button>
            <button type="reset" tabindex="7" class="btn btn-secondary btn-flat"><i class="fas fa-undo"></i> Reset</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
$(function () {
    $('#loan_date').focus();
    $('#loan_date_picker').datetimepicker({
        format: 'DD/MM/YYYY'
    });

    function togglePartyField() {
        if ($('#loan_type').val() === 'asset') {
            $('#vendor_group').hide();
            $('#vendor_id').prop('required', false).val('');
            $('#customer_group').show();
            $('#customer_id').prop('required', true);
        } else {
            $('#customer_group').hide();
            $('#customer_id').prop('required', false).val('');
            $('#vendor_group').show();
            $('#vendor_id').prop('required', true);
        }
    }

    togglePartyField();
    $('#loan_type').on('change', togglePartyField);
    $('#addLoan').on('reset', function () {
        setTimeout(togglePartyField, 0);
    });

    $.validator.setDefaults({
        submitHandler: function (form) {
            $('#saveLoan').prop('disabled', true);
            form.submit();
        }
    });
});
</script>
@endsection
