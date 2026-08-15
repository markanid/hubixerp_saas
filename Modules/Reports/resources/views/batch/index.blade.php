@extends('layout')

@section('content-header')
<div class="content-header"><div class="container-fluid"><h1>{{ $page_title }}</h1></div></div>
@endsection

@section('body')
<div class="card card-primary card-outline">
    <div class="card-body">
        @if($reportType === 'near-expiry')
            <div class="alert alert-info py-2">Showing stock expiring within {{ $expiryAlertDays }} days.</div>
        @endif
        <form method="get" class="row">
            <div class="col-md-3 form-group">
                <label>Product</label>
                <select name="product_id" class="form-control select2">
                    <option value="">All products</option>
                    @foreach($products as $product)
                        <option value="{{ $product->product_code }}" {{ ($filters['product_id'] ?? '') === $product->product_code ? 'selected' : '' }}>{{ $product->product }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 form-group"><label>Batch</label><input name="batch_no" class="form-control" value="{{ $filters['batch_no'] ?? '' }}"></div>
            <div class="col-md-2 form-group"><label>From</label><input type="date" name="from_date" class="form-control" value="{{ $filters['from_date'] ?? '' }}"></div>
            <div class="col-md-2 form-group"><label>To</label><input type="date" name="to_date" class="form-control" value="{{ $filters['to_date'] ?? '' }}"></div>
            <div class="col-md-3 form-group d-flex align-items-end"><button class="btn btn-primary mr-2">Filter</button><a href="{{ url()->current() }}" class="btn btn-default">Reset</a></div>
        </form>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                @if(in_array($reportType, ['stock','near-expiry','expired']))
                <thead><tr><th>Product</th><th>Batch</th><th>Expiry</th><th>Purchase Rate</th><th>MRP</th><th>Received</th><th>Available</th><th>Value</th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                    <tr class="{{ $row->expiry_date && $row->expiry_date->isPast() ? 'table-danger' : '' }}">
                        <td>{{ $row->product?->product }}</td><td>{{ $row->batch_no }}</td>
                        <td>{{ $row->expiry_date?->format('d/m/Y') ?? 'N/A' }}</td>
                        <td class="text-right">{{ number_format($row->purchase_rate, 2) }}</td><td class="text-right">{{ number_format($row->mrp, 2) }}</td>
                        <td class="text-right">{{ number_format($row->quantity, 2) }}</td><td class="text-right">{{ number_format($row->available_quantity, 2) }}</td>
                        <td class="text-right">{{ number_format($row->available_quantity * $row->purchase_rate, 2) }}</td>
                    </tr>
                @empty <tr><td colspan="8" class="text-center">No records found.</td></tr> @endforelse
                </tbody>
                @elseif($reportType === 'profitability')
                <thead><tr><th>Product</th><th>Batch</th><th>Qty Sold</th><th>Revenue</th><th>Cost</th><th>Gross Profit</th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                    <tr><td>{{ $row->batch?->product?->product }}</td><td>{{ $row->batch?->batch_no }}</td>
                        <td class="text-right">{{ number_format($row->quantity_sold, 2) }}</td><td class="text-right">{{ number_format($row->revenue, 2) }}</td>
                        <td class="text-right">{{ number_format($row->cost, 2) }}</td><td class="text-right">{{ number_format($row->revenue - $row->cost, 2) }}</td></tr>
                @empty <tr><td colspan="6" class="text-center">No records found.</td></tr> @endforelse
                </tbody>
                @else
                <thead><tr><th>Date</th><th>Product</th><th>Batch</th><th>Type</th><th>Reference</th><th>In</th><th>Out</th><th>Balance</th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                    <tr><td>{{ $row->movement_date?->format('d/m/Y') }}</td><td>{{ $row->batch?->product?->product }}</td><td>{{ $row->batch?->batch_no }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($row->movement_type)) }}</td><td>{{ $row->reference_type }} #{{ $row->reference_id }}</td>
                        <td class="text-right">{{ number_format($row->quantity_in, 2) }}</td><td class="text-right">{{ number_format($row->quantity_out, 2) }}</td>
                        <td class="text-right">{{ number_format($row->balance_after, 2) }}</td></tr>
                @empty <tr><td colspan="8" class="text-center">No records found.</td></tr> @endforelse
                </tbody>
                @endif
            </table>
        </div>
        {{ $rows->links() }}
    </div>
</div>
@endsection
