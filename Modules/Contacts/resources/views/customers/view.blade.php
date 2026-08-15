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
        <h3 class="card-title"><i class="fas fa-user-tag"></i> {{$page_title}}</h3>
        <div class="card-tools">
            <a class="btn btn-success btn-sm btn-flat" href="{{route('customers.edit', $customer->id)}}"><i class="fas fa-edit"></i> Edit</a>
            <a class="btn btn-dark btn-sm btn-flat" href="{{route('customers.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
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
                                <td style="width: 126px;height: 203px;">Customer Image</td>
                                <td>@if(!empty($customer->customer_image))
                                        <p><img src="{{tenant_asset('customer_logos/'.$customer->customer_image)}}" alt="Customer Photo" style="width: 150px; height: 150px;"></p>
                                    @else
                                        <p><img src="{{asset('uploads/default_company_logo.png')}}" alt="Customer Photo" style="width: 150px; height: 150px;"></p>
                                    @endif</td>
                            </tr>
                            <tr>
                                <td style="height: 69px;">Customer Name</td>
                                <td style="color: #007bff;">{{ $customer->customer }}</td>
                            </tr>
                            <tr>
                                <td>Phone</td>
                                <td>{{ $customer->phone }}</td>
                            </tr>
                            <tr>
                                <td style="height: 103px;">Address</td>
                                <td>{{ $customer->address }}</td>
                            </tr>
                            <tr>
                                <td>Email</td>
                                <td>{{ $customer->email }}</td>
                            </tr>
                            
                            <tr>
                                <td>GST</td>
                                <td>{{ $customer->gstin_no }}</td>
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
                <h3 class="card-title"><i class="fa-solid fa-sack-dollar"></i> Payment Info</h3>
            </div>
            <div class="card-body">
                <form id="post_form" method="POST" action="{{ route('customers.payment') }}">
                    @csrf
                    <input type="hidden" name="cust_id" value="{{ $customer->id }}">
                    <input type="hidden" id="total_balance" value="{{ $totalClosing }}">

                    <div class="row text-center">
                        <div class="col-md-6 mb-3">
                            <div class="p-3 border rounded bg-light">
                                <h6 class="text-muted">Total Amount Payable</h6>
                                <h5 class="text-primary fw-bold">Rs. {{ number_format($totalClosing, 2) }}/-</h5>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="p-3 border rounded bg-light">
                                <h6 class="text-muted" id="balance_caption">Balance to Pay</h6>
                                <h5 class="text-success fw-bold">Rs. <span id="tot_balance1">{{ number_format($totalClosing, 2) }}</span>/-</h5>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label fw-semibold">Date <sup class="text-danger">*</sup></label>
                        <div class="input-group" id="reservationdate" data-target-input="nearest">
                            <input type="text" name="pay_date" id="estimation_date" tabindex="1" class="form-control datetimepicker-input" data-target="#reservationdate" value="{{ now()->format('d/m/Y') }}" />
                            <span class="input-group-text" data-target="#reservationdate" data-toggle="datetimepicker"><i class="fa fa-calendar"></i></span>
                        </div>
                        @error('pay_date')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="form-group">
                         <label for="paytype" class="form-label fw-semibold">Payment Type <sup class="text-danger">*</sup></label>
                         <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa-solid fa-arrows-turn-to-dots"></i></span>
                            </div>
                            <select name="paytype" tabindex="2" id="paytype" class="form-control form-select" required>
                                <option value="r">Cash In (Received from Customer)</option>
                                <option value="s">Cash Out (Refund to Customer)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                         <label for="paymode" class="form-label fw-semibold">Payment Mode <sup class="text-danger">*</sup></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa-solid fa-building-columns"></i></span>
                            </div>
                            <select name="paymode" tabindex="3" id="paymode" class="form-control form-select" required>
                                @foreach($payments as $payment)
                                    <option value="{{ $payment->bk_id }}">{{ $payment->bk_bank }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="total_amount_paid" class="form-label fw-semibold">Enter Amount <sup class="text-danger">*</sup></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa-solid fa-money-bill-1"></i></span>
                            </div>
                            <input type="text" tabindex="4" name="total_amount_paid" id="total_amount_paid" class="form-control" placeholder="e.g. 1500.00" required>
                        </div>
                    </div>
                    @if($allocationEnabled)
                        @error('allocations')
                            <small class="text-danger d-block mb-2">{{ $message }}</small>
                        @enderror
                        <div class="form-group allocation-section" data-paytype="r">
                            <label class="form-label fw-semibold">Apply To Sales / Services / Loans</label>
                            <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                                <table class="table table-sm table-bordered mb-1">
                                    <thead>
                                        <tr>
                                            <th style="width: 34px;"></th>
                                            <th>Transaction</th>
                                            <th class="text-right">Total Sale Amount</th>
                                            <th class="text-right">Balance</th>
                                            <th style="width: 120px;">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($receivableTransactions as $source)
                                            <tr>
                                                <td class="text-center">
                                                    <input type="checkbox" class="allocation-check" data-ledger="{{ $source->lb_id }}">
                                                </td>
                                                <td>
                                                    <strong>{{ $source->allocation_label }}</strong><br>
                                                    <small>{{ $source->lb_vno }} | {{ \Carbon\Carbon::parse($source->lb_date)->format('d-m-Y') }}</small>
                                                </td>
                                                <td class="text-right">
                                                    {{ number_format($source->allocation_amount, 2) }}
                                                </td>
                                                <td class="text-right allocation-balance" data-ledger="{{ $source->lb_id }}" data-balance="{{ $source->allocation_balance }}">
                                                    {{ number_format($source->allocation_balance, 2) }}
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="{{ $source->allocation_balance }}" name="allocations[{{ $source->lb_id }}]" class="form-control form-control-sm allocation-amount" data-ledger="{{ $source->lb_id }}" disabled>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No pending sales, services, or customer loans.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="form-group allocation-section d-none" data-paytype="s">
                            <label class="form-label fw-semibold">Apply To Sales Return</label>
                            <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                                <table class="table table-sm table-bordered mb-1">
                                    <thead>
                                        <tr>
                                            <th style="width: 34px;"></th>
                                            <th>Transaction</th>
                                            <th class="text-right">Total Sale Amount</th>
                                            <th class="text-right">Balance</th>
                                            <th style="width: 120px;">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($refundTransactions as $source)
                                            <tr>
                                                <td class="text-center">
                                                    <input type="checkbox" class="allocation-check" data-ledger="{{ $source->lb_id }}">
                                                </td>
                                                <td>
                                                    <strong>{{ $source->allocation_label }}</strong><br>
                                                    <small>{{ $source->lb_vno }} | {{ \Carbon\Carbon::parse($source->lb_date)->format('d-m-Y') }}</small>
                                                </td>
                                                <td class="text-right">
                                                    {{ number_format($source->allocation_amount, 2) }}
                                                </td>
                                                <td class="text-right allocation-balance" data-ledger="{{ $source->lb_id }}" data-balance="{{ $source->allocation_balance }}">
                                                    {{ number_format($source->allocation_balance, 2) }}
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" max="{{ $source->allocation_balance }}" name="allocations[{{ $source->lb_id }}]" class="form-control form-control-sm allocation-amount" data-ledger="{{ $source->lb_id }}" disabled>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No pending sales returns.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="text-right mb-2">
                            <small class="text-muted">Balance: Rs. <span id="allocation_balance">0.00</span>/-</small>
                        </div>
                    @endif
                    <div class="card-footer" align="center">
                        <button type="submit" id="submitPaymentBtn" tabindex="5" class="btn btn-primary btn-flat">
                        <i class="fa fa-check-circle me-1"></i> Submit Payment</button>
                    </div>
                </form>
            </div>
        </div>
        @if($allocationEnabled && $advancePayments->isNotEmpty())
            <div class="card card-navy">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-wallet"></i> Available Advance</h3>
                </div>
                <div class="card-body">
                    @if($receivableTransactions->isNotEmpty())
                        <form method="POST" action="{{ route('customers.allocate-advance') }}">
                            @csrf
                            <input type="hidden" name="cust_id" value="{{ $customer->id }}">
                            <div class="form-group">
                                <label class="form-label fw-semibold">Advance Receipt</label>
                                <select name="advance_ledger_id" id="advance_ledger_id" class="form-control form-select" required>
                                    @foreach($advancePayments as $advance)
                                        <option value="{{ $advance->lb_id }}" data-balance="{{ $advance->advance_balance }}">
                                            {{ $advance->lb_vno }} | {{ \Carbon\Carbon::parse($advance->lb_date)->format('d-m-Y') }} | Rs. {{ number_format($advance->advance_balance, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold">Apply To Transaction</label>
                                <select name="source_ledger_id" id="advance_source_ledger_id" class="form-control form-select" required>
                                    @foreach($receivableTransactions as $source)
                                        <option value="{{ $source->lb_id }}" data-balance="{{ $source->allocation_balance }}">
                                            {{ $source->allocation_label }} - {{ $source->lb_vno }} | Balance Rs. {{ number_format($source->allocation_balance, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold">Amount</label>
                                <input type="number" step="0.01" min="0.01" name="amount" id="advance_apply_amount" class="form-control" required>
                            </div>
                            <div class="text-center">
                                <button type="submit" id="applyAdvanceBtn" class="btn btn-primary btn-flat">
                                    <i class="fa fa-check-circle me-1"></i> Apply Advance
                                </button>
                            </div>
                        </form>
                    @else
                        <p class="text-muted mb-0">Advance is available. It can be applied when a pending sale, service, or customer loan exists.</p>
                    @endif
                </div>
            </div>
        @endif
    </div>
    <div class="col-md-12">
        <div class="card card-navy">
            <div class="card-header">
                <h3 class="card-title"><i class="far fa-file-alt"></i> Customer Ledger</h3>
                <div class="card-tools">
                    <button id="fixLedgerBtn" class="btn btn-danger btn-sm">
                        <i class="fas fa-wrench"></i> Auto Fix Ledger
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="customer_table" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>SNo.</th>
                                <th>Date</th>
                                <th>Voucher No</th>
                                <th>Type</th>
                                <th>Due Date</th>
                                <th>Bank</th>
                                <th>Opening Balance</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Closing Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $totalDebit = 0;
                                $totalCredit = 0;
                                $totalClosing = 0;
                            @endphp
                            @foreach ($transactions as $index => $entry)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ \Carbon\Carbon::parse($entry->lb_date)->format('d-m-Y') }}</td>
                                    <td>
                                        @php
                                            $link = '';
                                            if ($entry->lb_vid != 0) {
                                                switch ($entry->lb_type) {
                                                    case 's':
                                                    case 'sp':
                                                        $link = route('sales.show', $entry->lb_vid);
                                                        break;
                                                    case 'sv':
                                                    case 'svp':
                                                        $link = route('services.show', $entry->lb_vid);
                                                        break;
                                                    case 'es':
                                                    case 'esp':
                                                        $link = route('estimations.show', $entry->lb_vid);
                                                        break;
                                                    case 'sr':
                                                    case 'srp':
                                                        $link = route('sales-returns.show', $entry->lb_vid);
                                                        break;
                                                    case 'loan_asset':
                                                        $link = route('loans.show', $entry->lb_vid);
                                                        break;
                                                }
                                            }
                                        @endphp
                                        @if ($link)
                                            <a href="{{ $link }}">{{ $entry->lb_vno }} 
                                                @if ($entry->status == '0')
                                                    <span class="badge badge-danger">Cancelled</span>
                                                @endif
                                            </a>
                                        @else
                                            {{ $entry->lb_vno }} @if ($entry->status == '0')<span class="badge badge-danger">Cancelled</span> @endif
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $type = '';
                                            if (in_array($entry->lb_type, ['s']) && $entry->lb_vid != 0) {
                                                $type = 'Local Sales';
                                            } elseif (in_array($entry->lb_type, ['sv']) && $entry->lb_vid != 0) {
                                                $type = 'Local Service';
                                            } elseif (in_array($entry->lb_type, ['es']) && $entry->lb_vid != 0) {
                                                $type = 'Estimation';
                                            } elseif (in_array($entry->lb_type, ['sr']) && $entry->lb_vid != 0) {
                                                $type = 'Sales Return';
                                            } elseif (in_array($entry->lb_type, ['sp', 'svp', 'esp'])) {
                                                $type = 'Receipt';
                                            } elseif ($entry->lb_type == 'srp') {
                                                $type = 'Voucher';
                                            } elseif ($entry->lb_type == 'loan_asset') {
                                                $type = 'Loan Asset';
                                            } else {
                                                $type = ucfirst($entry->lb_type); // fallback
                                            }
                                        @endphp
                                        {{ $type }}
                                    </td>
                                    <td>
                                        @if($entry->lb_type === 's' && $entry->sale && $entry->sale->sa_due_date)
                                            @php
                                                $dueBalance = $entry->sale->dueBalance();
                                                $isOverdue = $dueBalance > 0 && \Carbon\Carbon::parse($entry->sale->sa_due_date)->lte(now());
                                            @endphp
                                            {{ \Carbon\Carbon::parse($entry->sale->sa_due_date)->format('d-m-Y') }}
                                            @if($isOverdue)
                                                <span class="badge badge-warning">Due</span>
                                            @endif
                                        @elseif($entry->lb_type === 'es' && $entry->estimation && $entry->estimation->es_due_date)
                                            @php
                                                $dueBalance = (float) $entry->estimation->es_balance;
                                                $isOverdue = $dueBalance > 0 && \Carbon\Carbon::parse($entry->estimation->es_due_date)->lte(now());
                                            @endphp
                                            {{ \Carbon\Carbon::parse($entry->estimation->es_due_date)->format('d-m-Y') }}
                                            @if($isOverdue)
                                                <span class="badge badge-warning">Due</span>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $entry->banking->bk_bank ?? '-' }}</td>
                                    <td class="text-right">{{ number_format($entry->calc_opbalance, 2) }}</td>

                                    {{-- Debit Column --}}
                                    <td class="text-right">
                                        @php
                                            $debit = 0;
                                            if (in_array($entry->lb_type, ['s', 'sv', 'es', 'sp', 'svp', 'esp', 'loan_asset']))
                                            {
                                                $debit = $entry->lb_tramount;
                                                if ($entry->status == '1') {
                                                    $totalDebit += $debit;
                                                }
                                            } elseif (in_array($entry->lb_type, ['sr', 'srp'])){
                                                $debit = $entry->lb_amount;
                                                $totalDebit += $debit;
                                            }
                                        @endphp
                                        {{ number_format($debit, 2) }}
                                    </td>
                                    
                                    {{-- Credit Column --}}
                                    <td class="text-right">
                                        @php
                                            $credit = 0;
                                            if (in_array($entry->lb_type, ['s', 'sv', 'es', 'sp', 'svp', 'esp']))
                                            {
                                                $credit = $entry->lb_amount;
                                            } elseif(in_array($entry->lb_type, ['sr', 'srp'])) {
                                                $credit = $entry->lb_tramount;
                                            }

                                            if ($entry->status == '1')
                                            {
                                            $totalCredit += $credit;
                                            }
                                        @endphp
                                        {{ number_format($credit, 2) }}
                                    </td>

                                    {{-- Closing Balance --}}
                                    <td class="text-right">{{ number_format($entry->calc_clbalance, 2) }}</td>
                                </tr>
                                @if($allocationEnabled && in_array($entry->lb_type, ['sp', 'svp', 'esp', 'srp']) && $entry->customerPaymentAllocations->isNotEmpty())
                                    <tr>
                                        <td></td>
                                        <td colspan="9">
                                            <small class="text-muted">Applied to:</small>
                                            @foreach($entry->customerPaymentAllocations as $allocation)
                                                @php
                                                    $source = $allocation->sourceLedger;
                                                    $sourceLink = '';
                                                    if ($source) {
                                                        switch ($source->lb_type) {
                                                            case 's':
                                                                $sourceLink = route('sales.show', $source->lb_vid);
                                                                break;
                                                            case 'sv':
                                                                $sourceLink = route('services.show', $source->lb_vid);
                                                                break;
                                                            case 'es':
                                                                $sourceLink = route('estimations.show', $source->lb_vid);
                                                                break;
                                                            case 'sr':
                                                                $sourceLink = route('sales-returns.show', $source->lb_vid);
                                                                break;
                                                            case 'loan_asset':
                                                                $sourceLink = route('loans.show', $source->lb_vid);
                                                                break;
                                                        }
                                                    }
                                                    $sourceType = match ($allocation->source_type) {
                                                        's' => 'Sale',
                                                        'sv' => 'Service',
                                                        'es' => 'Estimation',
                                                        'sr' => 'Sales Return',
                                                        'loan_asset' => 'Loan Asset',
                                                        default => ucfirst($allocation->source_type),
                                                    };
                                                @endphp
                                                <span class="badge badge-light border mr-1">
                                                    {{ $sourceType }}
                                                    @if($source && $sourceLink)
                                                        <a href="{{ $sourceLink }}">{{ $source->lb_vno }}</a>
                                                    @elseif($source)
                                                        {{ $source->lb_vno }}
                                                    @else
                                                        #{{ $allocation->source_id }}
                                                    @endif
                                                    - Rs. {{ number_format($allocation->amount, 2) }}
                                                </span>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif
                                @php
                                    $totalClosing = $entry->calc_clbalance;
                                @endphp
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="7" class="text-right">Total</th>
                                <th class="text-right">{{ number_format($totalDebit, 2) }}</th>
                                <th class="text-right">{{ number_format($totalCredit, 2) }}</th>
                                <th class="text-right">{{ number_format($totalClosing, 2) }}</th>
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
document.addEventListener('DOMContentLoaded', function () {
    const totalPaidInput = document.getElementById('total_amount_paid');
    const totalBalance = parseFloat(document.getElementById('total_balance').value) || 0;
    const balanceDisplay = document.getElementById('tot_balance1');
    const balanceCaption = document.getElementById('balance_caption');
    const paytypeSelect = document.getElementById('paytype');
    const allocationBalance = document.getElementById('allocation_balance');
    const allocationSections = document.querySelectorAll('.allocation-section');
    const allocationChecks = document.querySelectorAll('.allocation-check');
    const allocationAmounts = document.querySelectorAll('.allocation-amount');
    const advanceLedgerSelect = document.getElementById('advance_ledger_id');
    const advanceSourceSelect = document.getElementById('advance_source_ledger_id');
    const advanceAmountInput = document.getElementById('advance_apply_amount');
    const paymentForm = document.getElementById('post_form');
    const submitPaymentBtn = document.getElementById('submitPaymentBtn');
    const applyAdvanceBtn = document.getElementById('applyAdvanceBtn');
    
    function updateBalance() {
        let paidAmount = parseFloat(totalPaidInput.value) || 0;
        let paytype = paytypeSelect.value;
        let newBalance = 0;
        if (paytype === 'r') {
            // Cash In (Received from customer) -> Reduce balance
            newBalance = totalBalance - paidAmount;
        } else if (paytype === 's') {
            // Cash Out (Refund to customer) -> Increase balance
            newBalance = totalBalance + paidAmount;
        }

        // if (newBalance < 0) newBalance = 0;
        if (newBalance < 0) {
            balanceCaption.textContent = 'Advance Amount';
            balanceDisplay.textContent = Math.abs(newBalance).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else {
            balanceCaption.textContent = 'Balance to Pay';
            balanceDisplay.textContent = newBalance.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }

    function updateAllocationBalance() {
        if (!allocationBalance) {
            return;
        }

        let total = 0;
        document.querySelectorAll('.allocation-section:not(.d-none) .allocation-amount:not(:disabled)').forEach(function(input) {
            total += parseFloat(input.value) || 0;
        });
        const paymentAmount = parseFloat(totalPaidInput.value) || 0;
        const remaining = paymentAmount - total;
        allocationBalance.textContent = remaining.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        allocationBalance.classList.toggle('text-danger', remaining < 0);
    }

    function formatMoney(amount) {
        return amount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateTransactionBalance(input) {
        const ledgerId = input.dataset.ledger;
        const balanceCell = document.querySelector('.allocation-balance[data-ledger="' + ledgerId + '"]');
        const originalBalance = parseFloat(balanceCell.dataset.balance) || 0;
        const enteredAmount = input.disabled ? 0 : (parseFloat(input.value) || 0);
        const remainingBalance = originalBalance - enteredAmount;

        balanceCell.textContent = formatMoney(remainingBalance);
        balanceCell.classList.toggle('text-danger', remainingBalance < 0);
    }

    function updateAllTransactionBalances() {
        allocationAmounts.forEach(updateTransactionBalance);
    }

    function selectedAmountTotal(exceptInput) {
        let total = 0;
        document.querySelectorAll('.allocation-section:not(.d-none) .allocation-amount:not(:disabled)').forEach(function(input) {
            if (exceptInput && input === exceptInput) {
                return;
            }
            total += parseFloat(input.value) || 0;
        });
        return total;
    }

    function resetHiddenAllocations() {
        allocationSections.forEach(function(section) {
            if (!section.classList.contains('d-none')) {
                return;
            }

            section.querySelectorAll('.allocation-check').forEach(function(check) {
                check.checked = false;
            });
            section.querySelectorAll('.allocation-amount').forEach(function(input) {
                input.value = '';
                input.disabled = true;
                updateTransactionBalance(input);
            });
        });
    }

    function toggleAllocationSections() {
        allocationSections.forEach(function(section) {
            section.classList.toggle('d-none', section.dataset.paytype !== paytypeSelect.value);
        });
        resetHiddenAllocations();
        updateAllocationBalance();
    }

    allocationChecks.forEach(function(check) {
        check.addEventListener('change', function() {
            const ledgerId = check.dataset.ledger;
            const input = document.querySelector('.allocation-amount[data-ledger="' + ledgerId + '"]');
            const balanceCell = document.querySelector('.allocation-balance[data-ledger="' + ledgerId + '"]');
            const balance = parseFloat(balanceCell.dataset.balance) || 0;

            if (check.checked) {
                input.disabled = false;
                const paymentAmount = parseFloat(totalPaidInput.value) || 0;
                const remaining = paymentAmount - selectedAmountTotal(input);
                const amount = Math.min(balance, Math.max(remaining, 0));
                input.value = amount > 0 ? amount.toFixed(2) : '';
            } else {
                input.value = '';
                input.disabled = true;
            }
            updateTransactionBalance(input);
            updateAllocationBalance();
        });
    });

    allocationAmounts.forEach(function(input) {
        input.addEventListener('input', function() {
            updateTransactionBalance(input);
            updateAllocationBalance();
        });
    });

    function updateAdvanceApplyLimit() {
        if (!advanceLedgerSelect || !advanceSourceSelect || !advanceAmountInput) {
            return;
        }

        const advanceBalance = parseFloat(advanceLedgerSelect.selectedOptions[0]?.dataset.balance) || 0;
        const sourceBalance = parseFloat(advanceSourceSelect.selectedOptions[0]?.dataset.balance) || 0;
        const maxAmount = Math.min(advanceBalance, sourceBalance);

        advanceAmountInput.max = maxAmount.toFixed(2);
        if (!advanceAmountInput.value || parseFloat(advanceAmountInput.value) > maxAmount) {
            advanceAmountInput.value = maxAmount > 0 ? maxAmount.toFixed(2) : '';
        }
    }

    if (advanceLedgerSelect && advanceSourceSelect && advanceAmountInput) {
        advanceLedgerSelect.addEventListener('change', updateAdvanceApplyLimit);
        advanceSourceSelect.addEventListener('change', updateAdvanceApplyLimit);
        updateAdvanceApplyLimit();
    }

    if (paymentForm && submitPaymentBtn) {
        paymentForm.addEventListener('submit', function() {
            submitPaymentBtn.disabled = true;
        });
    }

    if (applyAdvanceBtn) {
        applyAdvanceBtn.closest('form').addEventListener('submit', function() {
            applyAdvanceBtn.disabled = true;
        });
    }

    totalPaidInput.addEventListener('input', function() {
        updateBalance();
        updateAllTransactionBalances();
        updateAllocationBalance();
    });

    paytypeSelect.addEventListener('change', updateBalance);
    paytypeSelect.addEventListener('change', toggleAllocationSections);
    toggleAllocationSections();
    updateAllTransactionBalances();
});
</script>
<script>
    $(document).ready(function(){
        var Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        var table = $("#customer_table").DataTable({
            "responsive": true, "lengthChange": false, "autoWidth": false,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#customer_table_wrapper .col-md-6:eq(0)');
        
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
        
        $('#reservationdate').datetimepicker({
            format: 'DD/MM/YYYY'
        });
    });

    $('#fixLedgerBtn').click(function () {

        if (!confirm("Fix ledger for current financial year?")) {
            return;
        }
    
        // 🔄 Loading message
        toastr.info('Fixing ledger... Please wait');
    
        $.ajax({
            url: "{{ route('customers.fix-ledger') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                cust_id: "{{ $customer->id }}"
            },
            success: function (res) {
    
                toastr.success(res.message);
    
                setTimeout(function () {
                    location.reload();
                }, 1500);
            },
            error: function () {
                toastr.error('Something went wrong!');
            }
        });
    });
</script>
@endsection
