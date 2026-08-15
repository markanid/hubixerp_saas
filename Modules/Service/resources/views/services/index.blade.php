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
            <h3 class="card-title"><i class="fas fa-wrench"></i> {{$page_title}}</h3>
            <div class="card-tools d-flex gap-2">
                @can('service.create')
                <a class="btn btn-primary btn-sm btn-flat" href="{{ route('services.create') }}">
                    <i class="fas fa-plus-circle"></i> Create
                </a>
                @endcan
            </div>
        </div>
        
        <div class="card-body">
            <form method="GET" @if(!$is_cancelled) action="{{ route('services.index') }}" @else action="{{ route('services.cancelled') }}" @endif class="form-inline mb-3">
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
                                <input type="text" name="voucher" class="form-control" placeholder="Bill No" value="{{ request('voucher') }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                </div>
                                <input type="text" name="customer" class="form-control" placeholder="Customer Name" value="{{ request('customer') }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                </div>
                                <input type="text" name="phone" class="form-control" placeholder="Customer Phone" value="{{ request('phone') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer" align="center">
                <button type="submit" class="btn btn-primary btn-flat btn-sm">
                    <i class="fa-solid fa-magnifying-glass"></i> Search
                </button>
                <a href="{{ route('services.index') }}" class="btn btn-secondary btn-flat btn-sm ml-2">
                    <i class="fas fa-undo-alt"></i> Reset
                </a>

                @if(!$is_cancelled)
                    <a class="btn btn-danger btn-sm btn-flat ml-2" href="{{ route('services.cancelled') }}">
                        <i class="fas fa-ban"></i> Cancelled
                    </a>
                @else
                    <a class="btn btn-success btn-sm btn-flat ml-2" href="{{ route('services.index') }}">
                        <i class="fas fa-list"></i> Show List
                    </a>
                @endif
            </div>
        </form>
    </div>
    <!-- Listing Card -->
    <div class="card card-navy">
        <div class="card-header">
            @if($from_date || $to_date)
                <h3 class="card-title">
                    Showing @if($is_cancelled) cancelled @endif services from: <strong>{{ $from_date ?: 'start' }}</strong> to <strong>{{ $to_date ?: 'end' }}</strong>
                </h3>
            @else
                <h3 class="card-title">
                    Showing @if($is_cancelled) cancelled @endif services for: <strong>{{ \Carbon\Carbon::now()->format('F Y') }}</strong>
                </h3>
            @endif
        </div>
        <div class="card-body">
            <table id="service_table" class="table table-hover text-nowrap">
                <thead>
                    <tr>
                        <th>SNo</th>
                        <th>Date</th>
                        <th>Bill No.</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Options</th> 
                    </tr>
                </thead>
                <tbody>
                    @forelse($services as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $row->sv_date }}</td>
                            <td>
                                <a href="{{ $is_cancelled ? route('services.showCancelled', $row->sv_id) : route('services.show', $row->sv_id) }}">
                                    {{ $row->sv_vno }}
                                </a>
                            </td>
                            <td>
                                @if($row->customer)
                                    <a href="{{ route('customers.show', $row->sv_customer) }}">{{ $row->customer->customer }}</a>
                                @else
                                    <span class="text-muted">No Customer</span>
                                @endif
                            </td>
                            <td>{{$row->sv_amount_payable}}</td>
                            
                            <td>
                                @if(!$is_cancelled)
                                    @can('service.update')
                                    <a class="btn btn-app" href="{{route('services.edit', $row->sv_id)}}"><i class="fas fa-edit"></i> </a>
                                    @endcan
                                    @can('service.cancel')
                                    <button type="button"
                                            class="btn btn-app-delete delete-btn"
                                            data-form-id="cancel-service-{{ $row->sv_id }}">
                                        <i class="far fa-trash-alt"></i>
                                    </button>
                                    <form id="cancel-service-{{ $row->sv_id }}"
                                          method="POST"
                                          action="{{ route('services.destroy', $row->sv_id) }}"
                                          class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    @endcan
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
    $fy = session('financial_year');
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
        modalTitle.textContent = 'Confirm Service Cancellation';
    }

    if (modalMessage) {
        modalMessage.textContent = 'Are you sure you want to cancel this service bill? Stock and financial entries will be reversed.';
    }

    confirmButton.textContent = 'Cancel Service';

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

    confirmButton.addEventListener('click', function (event) {
        event.preventDefault();

        if (!cancellationForm) {
            return;
        }

        confirmButton.classList.add('disabled');
        confirmButton.setAttribute('aria-disabled', 'true');
        cancellationForm.submit();
    });

    $('#delete-confirmation-modal').on('hidden.bs.modal', function () {
        cancellationForm = null;
        confirmButton.classList.remove('disabled');
        confirmButton.removeAttribute('aria-disabled');
    });
});
</script>
@include('partials.common-index-script', ['tableId' => 'service_table'])
@endsection
