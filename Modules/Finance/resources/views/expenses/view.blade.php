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
                    <li class="breadcrumb-item"><a href="{{route('expenses.index')}}">Purchase</a></li>
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
        <h3 class="card-title"><i class="fas fa-receipt"></i> {{$page_title}}</h3>
        <div class="card-tools">
            <a class="btn btn-primary btn-sm btn-flat" href="{{route('expenses.create')}}"><i class="fa fa-plus-circle"></i> Create New</a>
            @if(!$is_cancelled)
                <a class="btn btn-info btn-sm btn-flat" href="{{route('expenses.edit', $expense->id)}}"><i class="fas fa-edit"></i> Edit</a>
            @endif
            <a class="btn btn-dark btn-sm btn-flat" href="{{route('expenses.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row" >
            <div class="col-md-3">
                <div class="table-responsive">
                    <table class="table table-bordered" style="margin-bottom: 10px;">
                        <tbody>
                            <tr>
                                <td>Date</td>
                                <td style="color:#f50303"><b>{{ \Carbon\Carbon::parse($expense->exdate)->format('d/m/Y') }}</b></td>
                            </tr>
                            <tr>
                                <td>Voucher No</td>
                                <td style="color:#1d91be"><b>{{ ($expense->exp_vno)}}</b></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-5"> </div>
            <div class="col-md-4 pull-right">
                <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td>Payment Mode</td>
                                    <td><b>{{ $expense->banking->bk_bank }}</b></td>
                                </tr>
                                <tr>
                                    <td>User</td>
                                    <td><b>{{ $expense->user->user_name }}</b></td>
                                </tr>
                            </tbody>
                        </table>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        <td rowspan="2">
                            <span>Expense Document:</span>
                            <div>
                                @if(!empty($expense->docum))
                                    @php
                                        $ext = pathinfo($expense->docum, PATHINFO_EXTENSION);
                                    @endphp

                                    @if(in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']))
                                        <img src="{{ tenant_asset('expense_logos/'.$expense->docum) }}" alt="Expense Image" style="width: 200px; height: 100px;">
                                    @elseif(strtolower($ext) === 'pdf')
                                        <a href="{{ tenant_asset('expense_logos/'.$expense->docum) }}" target="_blank" class="btn btn-sm btn-primary">View PDF</a>
                                    @else
                                        <p>No preview available</p>
                                    @endif
                                @else
                                    <img src="{{asset('uploads/avatar.png')}}" alt="Expense Photo" style="width: 150px; height: 150px;">
                                @endif
                            </div>
                        </td>
                        <td>
                            <span>Category :</span>
                            <label>{{ $expense->excategory->category }}</label>
                        </td>
                        <td>
                            <span>Amount :</span>
                            <label>Rs. {{ $expense->amount }}</label>
                        </td>
                        <td>
                            <span>Remarks :</span>
                            <label>{{ $expense->remarks }}</label>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
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
        // Check for the flash message and display the SweetAlert2 popup
        @if(session('success'))
            Toast.fire({
                icon: 'success',
                title: '{{ session('success') }}'
            });
        @endif
        @if(session('info'))
            Toast.fire({
                icon: 'info',
                title: '{{ session('info') }}'
            });
        @endif
    });
</script>
@endsection