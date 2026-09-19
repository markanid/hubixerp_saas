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
                    <li class="breadcrumb-item"><a href="{{route('sales.index')}}">Sale</a></li>
                    <li class="breadcrumb-item active"> {{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="card card-primary card-outline">
    <div class="card-header">
        <a class="btn btn-dark btn-sm btn-flat float-right" href="{{route('sales.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
    </div>  
    <form method="POST" action="{{ route('salesettings.update') }}">
        @csrf
        <div class="card-body">
            <h5>Additional Sale Fields</h5>
            <hr>

            <div class="form-check">
                <input type="checkbox"
                       class="form-check-input"
                       id="manufacturing_date"
                       name="fields[]"
                       value="manufacturing_date"
                       @checked((bool) ($settings['manufacturing_date'] ?? false))>
                <label class="form-check-label" for="manufacturing_date">
                    Manufacturing Date (Item-wise)
                </label>
            </div>

            <h5 class="mt-4">Inventory Controls</h5>
            <hr>

            <div class="form-check">
                <input type="checkbox"
                       class="form-check-input"
                       id="allow_out_of_stock_sale"
                       name="fields[]"
                       value="allow_out_of_stock_sale"
                       @checked((bool) ($settings['allow_out_of_stock_sale'] ?? false))>
                <label class="form-check-label" for="allow_out_of_stock_sale">
                    Allow out-of-stock products to proceed in Sale
                </label>
                <small class="form-text text-muted">
                    If disabled, products cannot be added or saved when requested quantity exceeds stock. MRP slots and batch-managed stock always require sufficient tracked quantity.
                </small>
            </div>

            <h5 class="mt-4">Pricing Defaults</h5>
            <hr>

            <div class="form-check">
                <input type="checkbox"
                       class="form-check-input"
                       id="mrp_pricing_mode"
                       name="fields[]"
                       value="mrp_pricing_mode"
                       @checked((bool) ($settings['mrp_pricing_mode'] ?? false))>
                <label class="form-check-label" for="mrp_pricing_mode">
                    Use MRP as Sale unit price
                </label>
                <small class="form-text text-muted">
                                        If enabled, the selected stock lot's MRP is used as unit price and its margin is applied as the default discount.
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
