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
                    <li class="breadcrumb-item"><a href="{{route('banks.index')}}">Bank</a></li>
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
        <h3 class="card-title"><i class="fas fa-landmark"></i> {{$page_title}}</h3>
        <div class="card-tools">
            <a class="btn btn-success btn-sm btn-flat" href="{{route('banks.edit', $bank->bk_id)}}"><i class="fas fa-edit"></i> Edit</a>
            <a class="btn btn-dark btn-sm btn-flat" href="{{route('banks.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
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
                                <td style="width: 150px;">Bank Name</td>
                                <td style="color: #007bff; font-weight: bold;">{{ $bank->bk_bank }}</td>
                            </tr>
                            <tr>
                                <td>Account No.</td>
                                <td>{{ $bank->bk_account }}</td>
                            </tr>
                            <tr>
                                <td>Branch Name</td>
                                <td>{{ $bank->bk_branch }}</td>
                            </tr>
                            <tr>
                                <td>IFSC Code</td>
                                <td>{{ $bank->bk_ifsc }}</td>
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
                <h3 class="card-title"><i class="fa-solid fa-sack-dollar"></i> Account Balance</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <td style="width: 150px;">Opening Balance</td>
                                <td style="color: #28a745; font-weight: bold;">Rs. {{ number_format((float)$openingBalance, 2) }}/-</td>
                            </tr>
                            <tr>
                                <td>Closing Balance</td>
                                <td style="color: #dc3545; font-weight: bold;">Rs. {{ number_format((float)$closingBalance, 2) }}/-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-12">
        <div class="card card-navy">
            <div class="card-header">
                <h3 class="card-title"><i class="far fa-file-alt"></i> Transaction Ledger</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="customer_table" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>SNo.</th>
                                <th>Date</th>
                                <th>Payee</th>
                                <th>Transaction Type</th>
                                <th>Ref No.</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Closing Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $totalDebit = 0;
                                $totalCredit = 0;
                                $runningBalance = $openingBalance;
                            @endphp
                            @foreach ($transactions as $index => $entry)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ \Carbon\Carbon::parse($entry->lb_date)->format('d-m-Y') }}</td>
                                    <td>
                                        @php
                                            $payee = '';

                                            // Customer-related types
                                            if (in_array($entry->lb_type, ['sp','svp','esp','srp'])) {
                                                $payee = $entry->customer->customer ?? 'N/A';

                                            // Vendor-related types  
                                            } elseif (in_array($entry->lb_type, ['pp','prp'])) {
                                                $payee = $entry->vendor->cp_name ?? 'N/A';

                                            } elseif ($entry->lb_type == 'loan') {
                                                $payee = $entry->vendor->cp_name ?? 'N/A';

                                            } elseif ($entry->lb_type == 'loan_asset') {
                                                $payee = $entry->customer->customer ?? 'N/A';

                                            // Expense category  
                                            } elseif ($entry->lb_type == 'exp') {
                                                $payee = $entry->excategory->category ?? 'N/A';

                                            // Bank transfer  
                                            } elseif ($entry->lb_type == 'tran') {
                                                // opposite bank (to_bank)
                                                $payee = $entry->banking->bk_bank ?? 'N/A';
                                            }
                                        @endphp

                                        {{ $payee }}
                                    </td>
                                    <td>
                                        @php
                                            $type = '';
                                            if (in_array($entry->lb_type, ['exp']) && $entry->lb_vid != 0) {
                                                $type = 'Expenses';
                                            } elseif (in_array($entry->lb_type, ['sp', 'svp', 'esp', 'prp'])) {
                                                $type = 'Receipt';
                                            } elseif (in_array($entry->lb_type, ['srp', 'pp'])) {
                                                $type = 'Voucher';
                                            } elseif ($entry->lb_type == 'loan') {
                                                $type = 'Loan';
                                            } elseif ($entry->lb_type == 'loan_asset') {
                                                $type = 'Loan Asset';
                                            } else {
                                                $type = 'Bank Transfer'; // fallback
                                            }
                                        @endphp
                                        {{ $type }}
                                    </td>
                                    <td>
                                        @php
                                            $link = '';
                                            if ($entry->lb_vid != 0) {
                                                switch ($entry->lb_type) {
                                                    case 'pp':
                                                        $link = route('purchases.show', $entry->lb_vid);
                                                        break;
                                                    case 'prp':
                                                        $link = route('purchase-returns.show', $entry->lb_vid);
                                                        break;
                                                    case 'sp':
                                                        $link = route('sales.show', $entry->lb_vid);
                                                        break;
                                                    case 'srp':
                                                        $link = route('sales-returns.show', $entry->lb_vid);
                                                        break;
                                                    case 'svp':
                                                        $link = route('services.show', $entry->lb_vid);
                                                        break;
                                                    case 'esp':
                                                        $link = route('estimations.show', $entry->lb_vid);
                                                        break;
                                                    case 'loan':
                                                    case 'loan_asset':
                                                        $link = route('loans.show', $entry->lb_vid);
                                                        break;
                                                }
                                            }
                                        @endphp
                                        @if ($link)
                                            <a href="{{ $link }}">{{ $entry->lb_vno }} 
                                            </a>
                                        @else
                                            {{ $entry->lb_vno }} 
                                        @endif
                                    </td>

                                    {{-- Debit Column --}}
                                    <td class="text-right">
                                        @php
                                            $debit = 0;
                                            if (in_array($entry->lb_type, ['pp', 'srp', 'exp']))
                                            {
                                                $debit = $entry->lb_amount;
                                            }
                                            // TRANSFER → debit when the bank RECEIVES money (payee = current bank)
                                            elseif ($entry->lb_type == 'tran' && $entry->lb_payee == $bank->bk_id) {
                                                $debit = $entry->lb_amount;
                                            }
                                            elseif ($entry->lb_type == 'loan_asset') {
                                                $debit = $entry->lb_amount;
                                            }
                                            
                                            if ($entry->status == '1') {
                                                $totalDebit += $debit;
                                            }
                                        @endphp
                                        {{ number_format($debit, 2) }}
                                    </td>
                                    
                                    {{-- Credit Column --}}
                                    <td class="text-right">
                                        @php
                                            $credit = 0;
                                            if (in_array($entry->lb_type, ['sp', 'svp', 'esp', 'prp']))
                                            {
                                                $credit = $entry->lb_amount;
                                            }
                                            // TRANSFER → credit when the bank SENDS money (paymode = current bank)
                                            elseif ($entry->lb_type == 'tran' && $entry->lb_paymode == $bank->bk_id) {
                                                $credit = $entry->lb_amount;
                                            }
                                            elseif ($entry->lb_type == 'loan') {
                                                $credit = $entry->lb_amount;
                                            }

                                            if ($entry->status == '1')
                                            {
                                            $totalCredit += $credit;
                                            }
                                        @endphp
                                        {{ number_format($credit, 2) }}
                                    </td>
                                    <!-- Running Closing Balance -->
                                    @php
                                        $runningBalance = $runningBalance + $credit - $debit;
                                    @endphp
                                    <td class="text-right">{{ number_format($runningBalance, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-right">Total</th>
                                <th class="text-right">{{ number_format($totalDebit, 2) }}</th>
                                <th class="text-right">{{ number_format($totalCredit, 2) }}</th>
                                <th class="text-right">{{ number_format($runningBalance, 2) }}</th>
                            </tr>
                        </tfoot>
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
