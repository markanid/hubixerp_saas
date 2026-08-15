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
            <h3 class="card-title"><i class="fas fa-undo-alt"></i> {{ $page_title }}</h3>
            <div class="card-tools float-right">
                <a class="btn btn-primary btn-sm btn-flat float-right" href="{{ route('purchase-returns.create') }}">
                    <i class="fas fa-plus-circle"></i> Create
                </a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ $is_cancelled ? route('purchase-returns.cancelled') : route('purchase-returns.index') }}" class="form-inline mb-3">
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
                                    <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                </div>
                                <input type="text" name="vendor" class="form-control" placeholder="Supplier Name" value="{{ request('vendor') }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                </div>
                                <input type="text" name="phone" class="form-control" placeholder="Supplier Phone" value="{{ request('phone') }}">
                            </div>
                        </div>
                    </div>
                </div>
        </div>
        <div class="card-footer" align="center">
            <button type="submit" class="btn btn-primary btn-flat btn-sm">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
            <a href="{{ route('purchase-returns.index') }}" class="btn btn-secondary btn-flat btn-sm ml-2">
                <i class="fas fa-undo-alt"></i> Reset
            </a>

            @if(!$is_cancelled)
                <a class="btn btn-danger btn-sm btn-flat ml-2" href="{{ route('purchase-returns.cancelled') }}">
                    <i class="fas fa-ban"></i> Cancelled
                </a>
            @else
                <a class="btn btn-success btn-sm btn-flat ml-2" href="{{ route('purchase-returns.index') }}">
                    <i class="fas fa-list"></i> Show List
                </a>
            @endif
        </div>
            </form>
    </div>

    <div class="card card-navy">
        <div class="card-header">
            @if($from_date || $to_date)
                <h3 class="card-title">
                    Showing @if($is_cancelled) cancelled @endif purchase-returns from:
                    <strong>{{ $from_date ?: 'start' }}</strong> to <strong>{{ $to_date ?: 'end' }}</strong>
                </h3>
            @else
                <h3 class="card-title">
                    Showing @if($is_cancelled) cancelled @endif purchase-returns for:
                    <strong>{{ \Carbon\Carbon::now()->format('F Y') }}</strong>
                </h3>
            @endif
        </div>
        <div class="card-body">
            <table id="return_table" class="table table-hover text-nowrap">
                <thead>
                    <tr>
                        <th>SNo</th>
                        <th>Date</th>
                        <th>Return No.</th>
                        <th>Bill No.</th>
                        <th>Supplier</th>
                        <th>Total Amount</th>
                        <th>Options</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returns as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $row->pr_date }}</td>
                            <td>
                                <a href="{{ $is_cancelled ? route('purchase-returns.showCancelled', $row->pr_id) : route('purchase-returns.show', $row->pr_id) }}">
                                    {{ $row->pr_vno }}
                                </a>
                            </td>
                            <td>{{ $row->pr_pvno }}</td>
                            <td>{{ $row->vendor?->cp_name ?? 'No Supplier' }}</td>
                            <td>{{ $row->pr_amount_payable }}</td>
                            <td>
                                @if(!$is_cancelled)
                                    <a class="btn btn-app" href="{{ route('purchase-returns.edit', $row->pr_id) }}">
                                        <i class="far fa-edit"></i>
                                    </a>
                                    <button type="button"
                                            class="btn btn-app-delete delete-btn"
                                            data-form-id="cancel-purchase-return-{{ $row->pr_id }}">
                                        <i class="far fa-trash-alt"></i>
                                    </button>
                                    <form id="cancel-purchase-return-{{ $row->pr_id }}"
                                          method="POST"
                                          action="{{ route('purchase-returns.delete', $row->pr_id) }}"
                                          class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
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
    $fy = session('financial_year') ?: \Modules\Returns\app\Models\Returns::getFinancialYear(now());
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
    let cancellationForm = null;
    const modal = document.getElementById('delete-confirmation-modal');
    const modalTitle = modal?.querySelector('.modal-title');
    const modalMessage = modal?.querySelector('.modal-body p');
    const confirmButton = document.getElementById('confirm-delete-btn');

    if (!modal || !confirmButton) {
        return;
    }

    if (modalTitle) {
        modalTitle.textContent = 'Confirm Purchase Return Cancellation';
    }

    if (modalMessage) {
        modalMessage.textContent = 'Are you sure you want to cancel this purchase return? Stock and financial entries will be reversed.';
    }

    confirmButton.textContent = 'Cancel Purchase Return';

    document.addEventListener('click', function (event) {
        const deleteButton = event.target.closest('.delete-btn');

        if (!deleteButton) {
            return;
        }

        event.preventDefault();
        cancellationForm = document.getElementById(deleteButton.dataset.formId);

        if (cancellationForm) {
            $('#delete-confirmation-modal').modal('show');
        }
    });

    confirmButton.addEventListener('click', function () {
        if (!cancellationForm) {
            return;
        }

        confirmButton.disabled = true;
        confirmButton.textContent = 'Cancelling...';
        cancellationForm.submit();
    });

    $('#delete-confirmation-modal').on('hidden.bs.modal', function () {
        cancellationForm = null;
        confirmButton.disabled = false;
        confirmButton.textContent = 'Cancel Purchase Return';
    });
});
</script>
@include('partials.common-index-script', ['tableId' => 'return_table'])

@endsection
