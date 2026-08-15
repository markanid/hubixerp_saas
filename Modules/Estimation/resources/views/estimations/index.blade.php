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
            <h3 class="card-title"><i class="fa-solid fa-receipt"></i> {{$page_title}}</h3>
            
            <div class="card-tools d-flex gap-2">
                <a class="btn btn-primary btn-sm btn-flat" href="{{ route('estimations.create') }}">
                    <i class="fas fa-plus-circle"></i> Create
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <form method="GET" @if(!$is_cancelled) action="{{ route('estimations.index') }}" @else action="{{ route('estimations.cancelled') }}" @endif class="form-inline mb-3">
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
                <a href="{{ route('estimations.index') }}" class="btn btn-secondary btn-flat btn-sm ml-2">
                    <i class="fas fa-undo-alt"></i> Reset
                </a>

                @if(!$is_cancelled)
                    <a class="btn btn-danger btn-sm btn-flat ml-2" href="{{ route('estimations.cancelled') }}">
                        <i class="fas fa-ban"></i> Cancelled
                    </a>
                @else
                    <a class="btn btn-success btn-sm btn-flat ml-2" href="{{ route('estimations.index') }}">
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
                    Showing @if($is_cancelled) cancelled @endif estimations from: <strong>{{ $from_date ?: 'start' }}</strong> to <strong>{{ $to_date ?: 'end' }}</strong>
                </h3>
            @else
                <h3 class="card-title">
                    Showing @if($is_cancelled) cancelled @endif estimations for: <strong>{{ \Carbon\Carbon::now()->format('F Y') }}</strong>
                </h3>
            @endif
        </div>
        <div class="card-body">
            <table id="estimation_table" class="table table-hover text-nowrap">
                <thead>
                    <tr>
                        <th>SNo</th>
                        <th>Date</th>
                        <th>Bill No.</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Account</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Options</th> 
                    </tr>
                </thead>
                <tbody>
                    @forelse($estimations as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $row->es_date }}</td>
                            <td>
                                <a href="{{ $is_cancelled ? route('estimations.showCancelled', $row->es_id) : route('estimations.show', $row->es_id) }}">
                                    {{ $row->es_vno }}
                                </a>
                            </td>
                            <td>
                                @if($row->customer)
                                    <a href="{{ route('customers.show', $row->es_customer) }}">{{ $row->customer->customer }}</a>
                                @else
                                    <span class="text-muted">No Customer</span>
                                @endif
                            </td>
                            <td>{{ number_format((float) $row->es_amount_payable, 2) }}</td>
                            <td>
                                @if($row->es_account_effect)
                                    <span class="badge badge-success">Affected</span>
                                @else
                                    <span class="badge badge-secondary">No Effect</span>
                                @endif
                            </td>
                            <td>{{ number_format((float) ($row->es_amount_paid ?? 0), 2) }}</td>
                            <td>{{ number_format((float) ($row->es_balance ?? 0), 2) }}</td>
                            
                            <td>
                                @if(!$is_cancelled)
                                    <a class="btn btn-app" href="{{route('estimations.edit', $row->es_id)}}"><i class="far fa-edit"></i> </a>
                                    <a href="#" class="btn btn-app-delete delete-btn" data-url="{{ route('estimations.delete', ['id' => $row->es_id ]) }}"><i class="far fa-trash-alt"></i></a>
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
@include('partials.delete-modal-script')
@include('partials.common-index-script', ['tableId' => 'estimation_table'])
@endsection
