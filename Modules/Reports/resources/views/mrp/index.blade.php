@extends('layout')

@section('content')
<div class="container-fluid">
    <div class="card card-outline card-primary">
        <div class="card-header"><h3 class="card-title">{{ $page_title }}</h3></div>
        <div class="card-body">
            <form method="GET" class="row mb-3">
                <div class="col-md-3 form-group">
                    <label>Product</label>
                    <select name="product_id" class="form-control select2">
                        <option value="">All products</option>
                        @foreach($products as $product)
                            <option value="{{ $product->product_code }}" @selected(($filters['product_id'] ?? '') === $product->product_code)>{{ $product->product }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group"><label>MRP</label><input type="number" step="0.01" name="mrp" class="form-control" value="{{ $filters['mrp'] ?? '' }}"></div>
                <div class="col-md-2 form-group"><label>From</label><input type="date" name="from_date" class="form-control" value="{{ $filters['from_date'] ?? '' }}"></div>
                <div class="col-md-2 form-group"><label>To</label><input type="date" name="to_date" class="form-control" value="{{ $filters['to_date'] ?? '' }}"></div>
                <div class="col-md-3 form-group d-flex align-items-end"><button class="btn btn-primary mr-2">Filter</button><a href="{{ route('mrp-reports.index', $type) }}" class="btn btn-default">Clear</a></div>
            </form>

            <div class="table-responsive">
                @if($type === 'stock')
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Product</th><th>Purchase</th><th>Date</th><th>Purchase Rate</th><th>MRP</th><th>Sale Price</th><th>Margin</th><th>Received</th><th>Available</th><th>Cost Value</th></tr></thead>
                    <tbody>@forelse($rows as $row)<tr>
                        <td>{{ $row->product?->product }}</td><td>{{ $row->purchase_voucher }}</td><td>{{ $row->purchase_date?->format('d/m/Y') }}</td>
                        @php($unitQty = max((float) ($row->product?->uqty ?: 1), 1))
                        <td>{{ number_format($row->purchase_rate * $unitQty, 2) }}</td><td>{{ number_format($row->mrp, 2) }}</td><td>{{ number_format($row->sale_price, 2) }}</td>
                        <td>{{ number_format($row->margin_amount, 2) }} ({{ number_format($row->margin_percentage, 2) }}%)</td><td>{{ number_format($row->quantity / $unitQty, 2) }}</td>
                        <td>{{ number_format($row->available_quantity / $unitQty, 2) }}</td><td>{{ number_format($row->available_quantity * $row->purchase_rate, 2) }}</td>
                    </tr>@empty<tr><td colspan="10" class="text-center">No stock lots found.</td></tr>@endforelse</tbody>
                </table>
                @elseif($type === 'profitability')
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Product</th><th>Purchase Lot</th><th>MRP</th><th>Qty Sold</th><th>Revenue</th><th>Cost</th><th>Gross Profit</th></tr></thead>
                    <tbody>@forelse($rows as $row)<tr>
                        <td>{{ $row->lot?->product?->product }}</td><td>{{ $row->lot?->purchase_voucher }}</td><td>{{ number_format($row->mrp, 2) }}</td>
                        <td>{{ number_format($row->quantity_sold / max((float) ($row->lot?->product?->uqty ?: 1), 1), 2) }}</td><td>{{ number_format($row->revenue, 2) }}</td><td>{{ number_format($row->cost, 2) }}</td>
                        <td>{{ number_format($row->revenue - $row->cost, 2) }}</td>
                    </tr>@empty<tr><td colspan="7" class="text-center">No movements found.</td></tr>@endforelse</tbody>
                </table>
                @else
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Date</th><th>Product</th><th>Purchase Lot</th><th>MRP</th><th>Type</th><th>Reference</th><th>In</th><th>Out</th><th>Balance</th></tr></thead>
                    <tbody>@forelse($rows as $row)<tr>
                        <td>{{ $row->movement_date?->format('d/m/Y') }}</td><td>{{ $row->lot?->product?->product }}</td><td>{{ $row->lot?->purchase_voucher }}</td>
                        <td>{{ number_format($row->mrp, 2) }}</td><td>{{ $row->movement_type }}</td><td>{{ $row->reference_type }} #{{ $row->reference_id }}</td>
                        @php($unitQty = max((float) ($row->lot?->product?->uqty ?: 1), 1))
                        <td>{{ number_format($row->quantity_in / $unitQty, 2) }}</td><td>{{ number_format($row->quantity_out / $unitQty, 2) }}</td><td>{{ number_format($row->balance_after / $unitQty, 2) }}</td>
                    </tr>@empty<tr><td colspan="9" class="text-center">No movements found.</td></tr>@endforelse</tbody>
                </table>
                @endif
            </div>
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection
