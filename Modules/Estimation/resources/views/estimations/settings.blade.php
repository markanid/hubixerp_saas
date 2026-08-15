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
                    <li class="breadcrumb-item"><a href="{{ route('estimations.index') }}">Estimation</a></li>
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
        <h3 class="card-title"><i class="fas fa-cogs"></i> {{ $page_title }}</h3>
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{ route('estimations.index') }}">
            <i class="fas fa-arrow-alt-circle-left"></i> Back
        </a>
    </div>

    <form method="POST" action="{{ route('estimationsettings.update') }}">
        @csrf
        <div class="card-body">
            <h5>Accounting Controls</h5>
            <hr>

            <div class="form-check">
                <input type="checkbox"
                       class="form-check-input"
                       id="affect_customer_accounts"
                       name="fields[]"
                       value="affect_customer_accounts"
                       @checked((bool) ($settings['affect_customer_accounts'] ?? false))>
                <label class="form-check-label" for="affect_customer_accounts">
                    Affect customer accounts and stock like a no-tax sale
                </label>
                <small class="form-text text-muted">
                    When enabled, new/edited estimations create customer ledger, payment, stock and batch movements without GST.
                </small>
            </div>
        </div>

        <div class="card-footer text-center">
            <button class="btn btn-primary btn-flat">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function(){
        var Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        @if(session('success'))
            Toast.fire({
                icon: 'success',
                title: @json(session('success'))
            });
        @endif
    });
</script>
@endsection
