@extends('layout')

@section('content-header')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">{{ $page_title }}</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item">
                        <a href="{{ route('profile.dashboard') }}">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">Reports</li>
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
        <h3 class="card-title">
            <i class="fas fa-file-invoice"></i> Daily Report
        </h3>

        <div class="card-tools">
            <a class="btn btn-success btn-sm btn-flat" href="{{ route('profile.dashboard') }}">
                <i class="fas fa-arrow-alt-circle-left"></i> Back
            </a>
        </div>
    </div>

    <div class="card-body">

        {{-- FILTER --}}
        <form id="searchReport" method="post" action="{{ route('dailyreports.search')}}" enctype="multipart/form-data">
            @csrf

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date</label>

                        <div class="input-group date" id="report_date" data-target-input="nearest">
                            <input type="text"
                                   name="fdate"
                                   class="form-control datetimepicker-input"
                                   data-target="#report_date"
                                   value="{{ date('d/m/Y', strtotime($fdate)) }}">

                            <div class="input-group-append"
                                 data-target="#report_date"
                                 data-toggle="datetimepicker">
                                <div class="input-group-text">
                                    <i class="fa fa-calendar"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>

                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <hr>

        {{-- TITLE --}}
        <div class="row mb-3">
            <div class="col-md-12 text-center">
                <h4>
                    <b>Daily Report</b> as on
                    <b>{{ date('d-m-Y', strtotime($fdate)) }}</b>
                </h4>
                @if(!empty($bankBalanceDate) && $bankBalanceDate != $fdate)
                    <small class="text-muted">
                        Bank balances shown from {{ date('d-m-Y', strtotime($bankBalanceDate)) }}
                        @if(!empty($bankBalanceFinancialYear) && $bankBalanceFinancialYear != session('financial_year'))
                            ({{ $bankBalanceFinancialYear }})
                        @endif
                    </small>
                @endif
            </div>
        </div>

        {{-- INCOME + EXPENSE --}}
        <div class="row">

            {{-- INCOME --}}
            <div class="col-md-6">
                <div class="card card-success card-outline">

                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-arrow-circle-down"></i> Income
                        </h3>
                    </div>

                    <div class="card-body p-0">

                        <div class="table-responsive">

                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">S/N</th>
                                        <th>Particular</th>
                                        <th>Receipt No.</th>
                                        <th>Account</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    @php
                                        $slno = 1;
                                        $incomeTotal = 0;
                                    @endphp

                                    @foreach($incomeTransactions as $row)

                                        @php

                                            $incomeTotal += $row->lb_amount;

                                            if($row->lb_type == 'sp'){
                                                $particular = 'Sale';
                                                $account = $row->banking->bk_bank ?? '';
                                            }

                                            elseif($row->lb_type == 'svp'){
                                                $particular = 'Service';
                                                $account = $row->banking->bk_bank ?? '';
                                            }

                                            elseif($row->lb_type == 'esp'){
                                                $particular = 'Estimation';
                                                $account = $row->banking->bk_bank ?? '';
                                            }

                                            elseif($row->lb_type == 'prp'){
                                                $particular = 'Purchase Return';
                                                $account = $row->banking->bk_bank ?? '';
                                            }

                                            elseif($row->lb_type == 'loan'){
                                                $particular = 'Loan';
                                                $account = $row->banking->bk_bank ?? '';
                                            }

                                        @endphp

                                        <tr>

                                            <td>{{ $slno++ }}</td>

                                            <td>{{ $particular }}</td>

                                            <td>
                                                @if($row->daily_report_url)
                                                    <a href="{{ $row->daily_report_url }}" class="font-weight-bold">
                                                        {{ $row->lb_vno }}
                                                    </a>
                                                @else
                                                    {{ $row->lb_vno }}
                                                @endif
                                            </td>

                                            <td>{{ $account }}</td>

                                            <td class="text-right">
                                                {{ number_format($row->lb_amount,2) }}
                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>
                            </table>

                        </div>

                    </div>
                </div>
            </div>

            {{-- EXPENSE --}}
            <div class="col-md-6">

                <div class="card card-danger card-outline">

                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-arrow-circle-up"></i> Expense
                        </h3>
                    </div>

                    <div class="card-body p-0">

                        <div class="table-responsive">

                            <table class="table table-bordered table-striped mb-0">

                                <thead>
                                    <tr>
                                        <th style="width: 60px;">S/N</th>
                                        <th>Particular</th>
                                        <th>Voucher No.</th>
                                        <th>Account</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    @php
                                        $slno1 = 1;
                                        $expenseTotal = 0;
                                    @endphp

                                    @foreach($expenseTransactions as $row)

                                        @php

                                            $expenseTotal += $row->lb_amount;

                                            if($row->lb_type == 'pp'){
                                                $particular = 'Purchase';
                                                $account = $row->banking->bk_bank ?? '';
                                            }

                                            elseif($row->lb_type == 'srp'){
                                                $particular = 'Sale Return';
                                                $account = $row->banking->bk_bank ?? '';
                                            }

                                            elseif($row->lb_type == 'exp'){
                                                $particular = 'Expense';
                                                $account = $row->banking->bk_bank ?? '';
                                            }

                                            elseif($row->lb_type == 'loan_asset'){
                                                $particular = 'Loan Asset';
                                                $account = $row->banking->bk_bank ?? '';
                                            }

                                        @endphp

                                        <tr>

                                            <td>{{ $slno1++ }}</td>

                                            <td>{{ $particular }}</td>

                                            <td>
                                                @if($row->daily_report_url)
                                                    <a href="{{ $row->daily_report_url }}" class="font-weight-bold">
                                                        {{ $row->lb_vno }}
                                                    </a>
                                                @else
                                                    {{ $row->lb_vno }}
                                                @endif
                                            </td>

                                            <td>{{ $account }}</td>

                                            <td class="text-right">
                                                {{ number_format($row->lb_amount,2) }}
                                            </td>

                                        </tr>

                                    @endforeach

                                    {{-- ADD OTHER LOOPS SAME WAY --}}

                                </tbody>
                            </table>

                        </div>

                    </div>
                </div>

            </div>

            {{-- Bank Transfers --}}
            <div class="col-md-12">

                <div class="card card-danger card-outline">

                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exchange-alt"></i> Bank Transfers
                        </h3>
                    </div>

                    <div class="card-body p-0">

                        <div class="table-responsive">

                            <table class="table table-bordered table-striped mb-0">

                                <thead>
                                    <tr>
                                        <th style="width: 60px;">S/N</th>
                                        <th>Transfer Details</th>
                                        <th>Voucher No.</th>
                                        <th>From Account</th>
                                        <th>To Account</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    @php
                                        $slnoTran  = 1;
                                        $transferTotal  = 0;
                                    @endphp

                                    @foreach($bankTransactions as $row)

                                        @php
                                            $transferTotal += $row->lb_amount;
                                        @endphp

                                        <tr>

                                            <td>{{ $slnoTran++ }}</td>

                                            <td>{{ $row->payeeBank->bk_bank ?? '' }} To {{ $row->banking->bk_bank ?? '' }}</td>

                                            <td>{{ $row->lb_vno }}</td>

                                            <td>{{ $row->payeeBank->bk_bank ?? '' }}</td>

                                            <td>{{ $row->banking->bk_bank ?? '' }}</td>

                                            <td class="text-right">
                                                {{ number_format($row->lb_amount,2) }}
                                            </td>

                                        </tr>

                                    @endforeach

                                    {{-- ADD OTHER LOOPS SAME WAY --}}

                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="5" class="text-right">Transfer Total</th>
                                        <th class="text-right">Rs. {{ number_format($transferTotal,2) }}</th>
                                    </tr>
                                </tfoot>
                            </table>

                        </div>

                    </div>
                </div>

            </div>

        </div>

        {{-- SUMMARY --}}
        <div class="row mt-3">

            <div class="col-md-5">

                <div class="card card-navy">

                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i> Summary
                        </h3>
                    </div>

                    <div class="card-body p-0">

                        <table class="table table-bordered mb-0">

                            <tr>
                                <th>Opening Balance</th>
                                <td class="text-success font-weight-bold">
                                    Rs. {{ number_format($opening_balance,2) }}
                                </td>
                            </tr>

                            <tr>
                                <th>Total Income</th>

                                <td>
                                    Rs.
                                    {{ number_format($incomeTotal,2) }}
                                </td>
                            </tr>

                            <tr>
                                <th>Total Expense</th>

                                <td>
                                    Rs.
                                    {{ number_format($expenseTotal,2) }}
                                </td>
                            </tr>

                            <tr>
                                <th>Bank Transfers</th>

                                <td>
                                    Rs.
                                    {{ number_format($transferTotal,2) }}
                                    <small class="text-muted">(internal movement)</small>
                                </td>
                            </tr>

                            <tr>
                                <th>Expected Closing Balance</th>

                                <td class="text-danger font-weight-bold">

                                    Rs.

                                    {{ number_format($closingBalance ?? ($opening_balance + $incomeTotal - $expenseTotal),2) }}

                                </td>
                            </tr>

                            <tr>
                                <th>Snapshot Closing Balance</th>
                                <td class="font-weight-bold">
                                    Rs. {{ number_format($snapshotClosing, 2) }}
                                </td>
                            </tr>

                            <tr>
                                <th>Reconciliation Difference</th>
                                <td class="font-weight-bold {{ abs($reconciliationDifference) < 0.005 ? 'text-success' : 'text-danger' }}">
                                    Rs. {{ number_format($reconciliationDifference, 2) }}
                                </td>
                            </tr>

                        </table>

                    </div>

                </div>

            </div>

            {{-- BANK BALANCE --}}
            <div class="col-md-7">

                <div class="card card-info">

                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-university"></i> Bank Balances
                            @if(!empty($bankBalanceDate))
                                <small>as on {{ date('d-m-Y', strtotime($bankBalanceDate)) }}</small>
                            @endif
                        </h3>
                    </div>

                    <div class="card-body p-0">

                        <table class="table table-bordered table-striped mb-0">

                            <thead>
                                <tr>
                                    <th>S/N</th>
                                    <th>Bank Name</th>
                                    <th>Snapshot Date</th>
                                    <th class="text-right">Balance</th>
                                </tr>
                            </thead>

                            <tbody>

                                @php
                                    $slno2 = 1;
                                    $banksum = 0;
                                @endphp

                                @foreach($banks as $bank)

                                    @php
                                        $banksum += $bank->db_amount;
                                    @endphp

                                    <tr>
                                        <td>{{ $slno2++ }}</td>

                                        <td>{{ $bank->banking->bk_bank ?? '' }}</td>

                                        <td>{{ \Carbon\Carbon::parse($bank->db_date)->format('d-m-Y') }}</td>

                                        <td class="text-right">
                                            Rs. {{ number_format($bank->db_amount,2) }}
                                        </td>
                                    </tr>

                                @endforeach

                            </tbody>

                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-right">
                                        Bank Total
                                    </th>

                                    <th class="text-right text-danger">
                                        Rs. {{ number_format($banksum,2) }}
                                    </th>
                                </tr>
                            </tfoot>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

    {{-- FOOTER --}}
    <div class="card-footer text-center">

        <button onclick="window.print()" class="btn btn-warning btn-flat">
            <i class="fas fa-print"></i> Print
        </button>

    </div>

</div>

@endsection

@section('scripts')

<script>
    $(function () {

        $('#report_date').datetimepicker({
            format: 'DD/MM/YYYY'
        });

    });
</script>

@endsection