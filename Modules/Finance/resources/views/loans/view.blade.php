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
@php
    $loanLedgers = $loanLedgers ?? collect();
@endphp
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-hand-holding-usd"></i> {{$page_title}}</h3>
        <div class="card-tools">
            <a class="btn btn-primary btn-sm btn-flat" href="{{route('loans.create')}}"><i class="fa fa-plus-circle"></i> Create New</a>
            <a class="btn btn-dark btn-sm btn-flat" href="{{route('loans.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card card-navy">
            <div class="card-header">
                <h3 class="card-title"><i class="far fa-file-alt"></i> Basic Details</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <td style="width: 140px;height: 69px;">Loan No.</td>
                                <td style="color: #007bff;">{{ $loan->loan_vno }}</td>
                            </tr>
                            <tr>
                                <td>Date</td>
                                <td>{{ \Carbon\Carbon::parse($loan->loan_date)->format('d/m/Y') }}</td>
                            </tr>
                            <tr>
                                <td>Loan Type</td>
                                <td>{{ ucfirst($loan->loan_type ?? 'liability') }}</td>
                            </tr>
                            <tr>
                                <td>{{ ($loan->loan_type ?? 'liability') == 'asset' ? 'Customer' : 'Supplier' }}</td>
                                <td>{{ ($loan->loan_type ?? 'liability') == 'asset' ? ($loan->customer->customer ?? '') : ($loan->vendor->cp_name ?? '') }}</td>
                            </tr>
                            <tr>
                                <td>Phone</td>
                                <td>{{ ($loan->loan_type ?? 'liability') == 'asset' ? ($loan->customer->phone ?? '') : ($loan->vendor->cp_phone ?? '') }}</td>
                            </tr>
                            <tr>
                                <td>Bank</td>
                                <td>{{ $loan->banking->bk_bank ?? '' }}</td>
                            </tr>
                            <tr>
                                <td>Loan Amount</td>
                                <td class="text-success"><b>Rs. {{ number_format($loan->amount, 2) }}</b></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-navy">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-book"></i> Ledger</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Bank</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $balance = 0;
                            @endphp
                            @forelse($loanLedgers as $ledger)
                                @php
                                    $amount = $ledger->lb_amount ?: $ledger->lb_tramount;
                                    $balance += $amount;
                                @endphp
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($ledger->lb_date)->format('d/m/Y') }}</td>
                                    <td>{{ $ledger->banking->bk_bank ?? '' }}</td>
                                    <td class="text-right">{{ number_format($amount, 2) }}</td>
                                    <td class="text-right">{{ number_format($balance, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No ledger entries found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
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

        @if(session('success'))
            Toast.fire({
                icon: 'success',
                title: '{{ session('success') }}'
            });
        @endif
    });
</script>
@endsection
