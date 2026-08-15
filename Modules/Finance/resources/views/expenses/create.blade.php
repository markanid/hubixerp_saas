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
                    <li class="breadcrumb-item"><a href="{{ route('profile.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">Expenses</a></li>
                    <li class="breadcrumb-item active">{{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-receipt"></i> {{ $page_title }}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{ route('expenses.index') }}">
            <i class="fas fa-arrow-alt-circle-left"></i> Back
        </a>
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
    <form id="addExpense" method="post" action="{{ route('expenses.update') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="user_id" name="exp_user" value="{{ session()->get('id') }}">
        @php
            $isEdit = isset($expense);
            $selectedPaymode = old('ex_paymode', $expense->ex_paymode ?? $defaultPaymode ?? '');
        @endphp

        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Expense No.<sup>*</sup></label>
                        <input type="text" name="exp_vno" id="voucher_no" tabindex="1" class="form-control" value="{{ old('exp_vno', $expense->exp_vno ?? $voucher_no) }}" readonly>
                        @if ($errors->has('exp_vno'))
                            <span class="text-danger">{{ $errors->first('exp_vno') }}</span>
                        @endif
                        <input type="hidden" id="expense_id" name="id" value="{{ $expense->id ?? '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Date<sup>*</sup></label>
                        <div class="input-group date" id="reservationdate" data-target-input="nearest">
                            <input type="text" name="exdate" id="expense_date" tabindex="2" class="form-control datetimepicker-input" data-target="#reservationdate" value="{{ old('exdate', isset($expense) ? \Carbon\Carbon::parse($expense->exdate)->format('d/m/Y') : now()->format('d/m/Y')) }}">
                            <div class="input-group-append" data-target="#reservationdate" data-toggle="datetimepicker">
                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                            </div>
                            @if ($errors->has('exdate'))
                                <span class="text-danger">{{ $errors->first('exdate') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Category<sup>*</sup></label>
                        <select name="categoryid" tabindex="3" class="form-control select2" style="width:100%">
                            <option value="">-- Select Category --</option>
                            @foreach ($excategories as $excategory)
                                <option value="{{ $excategory->id }}" {{ (string) old('categoryid', $expense->categoryid ?? '') === (string) $excategory->id ? 'selected' : '' }}>
                                    {{ $excategory->category }}
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
                        <label>Remarks</label>
                        <input type="text" name="remarks" id="remarks" tabindex="6" class="form-control" value="{{ old('remarks', $expense->remarks ?? '') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Amount<sup>*</sup></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa-solid fa-money-bill-1-wave"></i></span>
                            </div>
                            <input type="text" name="amount" tabindex="4" class="form-control" value="{{ old('amount', $expense->amount ?? '') }}">
                        </div>
                        @if ($errors->has('amount'))
                            <span class="text-danger">{{ $errors->first('amount') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Payment Mode<sup>*</sup></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa-solid fa-building-columns"></i></span>
                            </div>
                            <select name="ex_paymode" id="ex_paymode" class="form-control" tabindex="5">
                                <option value="">-- Select Payment Mode --</option>
                                @foreach ($banks as $bank)
                                    <option value="{{ $bank->bk_id }}" {{ (string) $selectedPaymode === (string) $bank->bk_id ? 'selected' : '' }}>
                                        {{ $bank->bk_bank }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @if ($errors->has('ex_paymode'))
                            <span class="text-danger">{{ $errors->first('ex_paymode') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="customFile">Attachment</label>
                        <div class="input-group">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="customFile" tabindex="7" name="docum" accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,.pdf">
                                <label class="custom-file-label" for="customFile">Choose file</label>
                            </div>
                        </div>
                        <div id="photo_preview" class="mt-2">
                            @if(!empty($expense->docum))
                                @php($ext = pathinfo($expense->docum, PATHINFO_EXTENSION))
                                @if(in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']))
                                    <img src="{{ tenant_asset('expense_logos/'.$expense->docum) }}" alt="Expense Attachment" style="width: 180px; height: 120px; object-fit: contain;">
                                @elseif(strtolower($ext) === 'pdf')
                                    <a href="{{ tenant_asset('expense_logos/'.$expense->docum) }}" target="_blank" class="btn btn-sm btn-primary btn-flat">
                                        <i class="fas fa-file-pdf"></i> View PDF
                                    </a>
                                @else
                                    <span class="text-muted">No preview available</span>
                                @endif
                            @else
                                <span class="text-muted">No attachment selected</span>
                            @endif
                        </div>
                        @if ($errors->has('docum'))
                            <span class="text-danger">{{ $errors->first('docum') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer" align="center">
            <button type="submit" id="saveExpense" tabindex="8" class="btn btn-primary btn-flat">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </form>
</div>
@endsection

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('scripts')
<script>
$(function () {
    $('.select2').select2({ width: '100%' });

    $('#reservationdate').datetimepicker({
        format: 'DD/MM/YYYY'
    });

    bsCustomFileInput.init();

    $('#customFile').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass('selected').html(fileName);

        const file = this.files[0];
        if (!file) {
            return;
        }

        if (file.type === 'application/pdf') {
            $('#photo_preview').html('<span class="btn btn-sm btn-primary btn-flat"><i class="fas fa-file-pdf"></i> PDF selected</span>');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            $('#photo_preview').html('<img src="' + e.target.result + '" alt="Expense Attachment" style="width: 180px; height: 120px; object-fit: contain;">');
        };
        reader.readAsDataURL(file);
    });

    $('#addExpense').on('submit', function () {
        $('#saveExpense').prop('disabled', true);
    });
});
</script>
@endsection
