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
            <h3 class="card-title"><i class="fas fa-box-open"></i> {{ $page_title }}</h3>
            <div class="card-tools d-flex gap-2">
                <a class="btn btn-primary btn-sm btn-flat" href="{{ route('consumptions.create') }}">
                    <i class="fas fa-plus-circle"></i> Create
                </a>
            </div>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ $is_cancelled ? route('consumptions.cancelled') : route('consumptions.index') }}" class="form-inline mb-3">
                <div class="row w-100">
                    <div class="col-md-4">
                        <div class="form-group w-100">
                            <div class="input-group w-100">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                </div>
                                <input type="text" name="fromtodates" class="form-control float-right" id="reservation" placeholder="Date Range" value="{{ request('fromtodates') ?: '' }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group w-100">
                            <div class="input-group w-100">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa-solid fa-file-invoice"></i></span>
                                </div>
                                <input type="text" name="voucher" class="form-control" placeholder="Voucher No" value="{{ request('voucher') }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group w-100">
                            <div class="input-group w-100">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                </div>
                                <input type="text" class="form-control" value="{{ $is_cancelled ? 'Cancelled Consumptions' : 'Active Consumptions' }}" readonly>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
        <div class="card-footer" align="center">
            <button type="submit" class="btn btn-primary btn-flat btn-sm">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
            <a href="{{ $is_cancelled ? route('consumptions.cancelled') : route('consumptions.index') }}" class="btn btn-secondary btn-flat btn-sm ml-2">
                <i class="fas fa-undo-alt"></i> Reset
            </a>

            @if(!$is_cancelled)
                <a class="btn btn-danger btn-sm btn-flat ml-2" href="{{ route('consumptions.cancelled') }}">
                    <i class="fas fa-ban"></i> Cancelled
                </a>
            @else
                <a class="btn btn-success btn-sm btn-flat ml-2" href="{{ route('consumptions.index') }}">
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
                    Showing @if($is_cancelled) cancelled @endif consumptions from: <strong>{{ $from_date ?: 'start' }}</strong> to <strong>{{ $to_date ?: 'end' }}</strong>
                </h3>
            @else
                <h3 class="card-title">
                    Showing @if($is_cancelled) cancelled @endif consumptions for: <strong>{{ \Carbon\Carbon::now()->format('F Y') }}</strong>
                </h3>
            @endif
        </div>
        <div class="card-body">
            <table id="consumption_table" class="table table-hover text-nowrap">
                <thead>
                    <tr>
                        <th>SNo</th>
                        <th>Date</th>
                        <th>Voucher No</th>
                        <th>User</th>
                        <th>Total</th>
                        <th>Options</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($consumptions as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->con_date)->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ $is_cancelled ? route('consumptions.showCancelled', $row->con_id) : route('consumptions.show', $row->con_id) }}">
                                    {{ $row->con_vno }}
                                </a>
                            </td>
                            <td>{{ $row->user?->user_name ?? '-' }}</td>
                            <td>Rs. {{ number_format((float) $row->con_amount, 2) }}</td>
                            <td>
                                @if(!$is_cancelled)
                                    <a class="btn btn-app" href="{{ route('consumptions.edit', $row->con_id) }}">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" class="btn btn-app-delete delete-btn" data-url="{{ route('consumptions.delete', ['id' => $row->con_id]) }}">
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
@include('partials.common-index-script', ['tableId' => 'consumption_table'])
@endsection
