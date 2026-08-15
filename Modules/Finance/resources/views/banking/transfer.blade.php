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
    </div>  
    <form id="addProduct" method="post" action="{{ route('banks.transfer') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="id" name="bk_id" value="{{ $bank->bk_id ?? '' }}">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>From Bank:</label>
                        <select name="from_bank" id="from_bank" required tabindex="1" class="form-control">
                            <option value="">--Select Bank--</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->bk_id }}" data-balance="{{ $bank->bk_clbalance }}">
                                    {{ $bank->bk_bank }}
                                </option>
                            @endforeach
                        </select>
                        <small id="from_balance_text" class="form-text text-muted">
                            Balance: 0.00
                        </small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>To Bank:</label>
                        <select name="to_bank" required tabindex="2" class="form-control">
                            @foreach($banks as $bank)
                                <option value="{{ $bank->bk_id }}">{{ $bank->bk_bank }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Amount:</label>
                        <input type="number" id="amount" name="amount" min="0.01" step="0.01" required tabindex="3" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Remarks:</label>
                        <textarea name="remarks" tabindex="4" class="form-control"></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer" align="center">
            <button type="submit" id="submitBtn" tabindex="5" class="btn btn-primary  btn-flat"><i class="fas fa-save"></i> Save</button>
            <button type="reset" value="Reset" id="resetbtn" tabindex="6" class="btn btn-secondary  btn-flat"><i class="fas fa-undo-alt"></i> Reset</button>
            
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
$(function () {
    function formatAmount(amount) {
        return amount.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function updateProjectedBalance() {
        let selectedOption = $('#from_bank option:selected');
        let balance = parseFloat(selectedOption.data('balance')) || 0;
        let amount = parseFloat($('#amount').val()) || 0;
        let projectedBalance = balance - amount;

        $('#from_balance_text')
            .text('Balance: ' + formatAmount(balance) + ' | After transfer: ' + formatAmount(projectedBalance))
            .toggleClass('text-danger', projectedBalance < 0);
    }

    function updateBalance() {
        $('#amount').removeAttr('max');
        updateProjectedBalance();
    }

    // When From Bank changes
    $('#from_bank').on('change', updateBalance);
    updateBalance(); // call on page load

    $('#amount').on('input', updateProjectedBalance);

    // jQuery validate submit handler
    $.validator.setDefaults({
        submitHandler: function (form) {
            $('#submitBtn').prop('disabled', true); // Disable the submit button
            form.submit();
        }
    });
});
</script>
@endsection

