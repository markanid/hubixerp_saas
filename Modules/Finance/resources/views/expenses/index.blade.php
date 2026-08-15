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
                    <li class="breadcrumb-item">Dashboard</li>
                    <li class="breadcrumb-item active">{{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="container-fluid">
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-receipt"></i> {{ $page_title }}</h3>
            <div class="card-tools float-right">
                <a class="btn btn-primary btn-sm btn-flat" href="{{ route('expenses.create') }}">
                    <i class="fas fa-plus-circle"></i> Create
                </a>
            </div>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ $is_cancelled ? route('expenses.cancelled') : route('expenses.index') }}" class="form-inline mb-3">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                </div>
                                <input type="text" name="fromtodates" class="form-control float-right" id="reservation" placeholder="Date Range" value="{{ request('fromtodates') ?: '' }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                                </div>
                                <input type="text" name="voucher" class="form-control" placeholder="Voucher No" value="{{ request('voucher') }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-tags"></i></span>
                                </div>
                                <select name="category" class="form-control">
                                    <option value="">Category</option>
                                    @foreach ($excategories as $excategory)
                                        <option value="{{ $excategory->id }}" {{ (string) request('category') === (string) $excategory->id ? 'selected' : '' }}>
                                            {{ $excategory->category }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa-solid fa-building-columns"></i></span>
                                </div>
                                <select name="paymode" class="form-control">
                                    <option value="">Payment Mode</option>
                                    @foreach ($banks as $bank)
                                        <option value="{{ $bank->bk_id }}" {{ (string) request('paymode') === (string) $bank->bk_id ? 'selected' : '' }}>
                                            {{ $bank->bk_bank }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer" align="center">
                <button type="submit" class="btn btn-primary btn-flat btn-sm">
                    <i class="fa-solid fa-magnifying-glass"></i> Search
                </button>
                <a href="{{ route('expenses.index') }}" class="btn btn-secondary btn-flat btn-sm ml-2">
                    <i class="fas fa-undo-alt"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <div class="card card-navy">
        <div class="card-header">
            @if($from_date || $to_date)
                <h3 class="card-title">
                    Showing @if($is_cancelled) cancelled @endif expenses from:
                    <strong>{{ $from_date ?: 'start' }}</strong> to <strong>{{ $to_date ?: 'end' }}</strong>
                </h3>
            @else
                <h3 class="card-title">
                    Showing @if($is_cancelled) cancelled @endif expenses for:
                    <strong>{{ \Carbon\Carbon::now()->format('F Y') }}</strong>
                </h3>
            @endif
        </div>
        <div class="card-body">
            <table id="expense_table" class="table table-hover text-nowrap">
                <thead>
                    <tr>
                        <th>SNo</th>
                        <th>Date</th>
                        <th>Voucher No.</th>
                        <th>Category</th>
                        <th>Payment Mode</th>
                        <th>Total Amount</th>
                        <th>Options</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->exdate)->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ $is_cancelled ? route('expenses.showCancelled', $row->id) : route('expenses.show', $row->id) }}">
                                    {{ $row->exp_vno }}
                                </a>
                            </td>
                            <td>{{ $row->excategory?->category ?? '-' }}</td>
                            <td>{{ $row->banking?->bk_bank ?? '-' }}</td>
                            <td>{{ number_format((float) $row->amount, 2) }}</td>
                            <td>
                                @if(!$is_cancelled)
                                    <a class="btn btn-app" href="{{ route('expenses.edit', $row->id) }}"><i class="far fa-edit"></i></a>
                                    <a href="#" class="btn btn-app-delete delete-btn" data-url="{{ route('expenses.delete', ['id' => $row->id]) }}">
                                        <i class="far fa-trash-alt"></i>
                                    </a>
                                @else
                                    <span class="text-muted">No Actions</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@include('partials.delete-modal')

@php
    $fy = $financialYear ?? session('financial_year') ?? \Modules\Finance\app\Models\Expense::getFinancialYear(now());
    [$startYear, $endYear] = explode('-', $fy);
    $fyStart = \Carbon\Carbon::createFromDate($startYear, 4, 1)->format('Y-m-d');
    $fyEnd = \Carbon\Carbon::createFromDate($endYear, 3, 31)->format('Y-m-d');
@endphp

@section('scripts')
<script>
    const fyStart = "{{ $fyStart }}";
    const fyEnd = "{{ $fyEnd }}";
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById('delete-confirmation-modal');
    const modalTitle = modal?.querySelector('.modal-title');
    const modalMessage = modal?.querySelector('.modal-body p');
    const confirmButton = document.getElementById('confirm-delete-btn');

    if (modalTitle) {
        modalTitle.textContent = 'Confirm Expense Deletion';
    }

    if (modalMessage) {
        modalMessage.textContent = 'Are you sure you want to permanently delete this expense? Financial entries will be reversed and this cannot be undone.';
    }

    if (confirmButton) {
        confirmButton.textContent = 'Delete Expense';
    }
});
</script>
@include('partials.delete-modal-script')
@include('partials.common-index-script', ['tableId' => 'expense_table'])
@endsection
