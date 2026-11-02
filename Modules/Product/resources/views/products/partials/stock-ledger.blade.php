<div class="card card-navy card-outline" id="stock-ledger">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-book"></i> Stock Ledger</h3>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('products.show', $product->id) }}#stock-ledger">
            <div class="form-row align-items-end">
                <div class="form-group col-sm-4 col-lg-3">
                    <label for="ledger_from">From date</label>
                    <input type="date" id="ledger_from" name="ledger_from" class="form-control @error('ledger_from') is-invalid @enderror"
                        value="{{ old('ledger_from', $stockLedger['from']) }}" required>
                    @error('ledger_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-group col-sm-4 col-lg-3">
                    <label for="ledger_to">To date</label>
                    <input type="date" id="ledger_to" name="ledger_to" class="form-control @error('ledger_to') is-invalid @enderror"
                        value="{{ old('ledger_to', $stockLedger['to']) }}" required>
                    @error('ledger_to') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-group col-sm-4 col-lg-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('products.show', $product->id) }}#stock-ledger" class="btn btn-default">Reset</a>
                </div>
            </div>
        </form>
        <p class="text-muted mb-3">
            Quantities are in base units (No.s).
            @if((float) $product->uqty > 1)
                1 {{ $product->unit }} = {{ number_format((float) $product->uqty, 2) }} No.s.
            @endif
            Opening stock includes all movements before the from date.
        </p>
        <div class="row">
            @foreach(['opening' => 'Opening Stock', 'total_in' => 'Quantity In', 'total_out' => 'Quantity Out', 'closing' => 'Closing Stock'] as $key => $label)
                <div class="col-6 col-lg-3">
                    <div class="small-box {{ $key === 'closing' ? 'bg-navy' : 'bg-light' }}">
                        <div class="inner">
                            <h4>{{ number_format($stockLedger[$key], 2) }}</h4>
                            <p class="mb-0">{{ $label }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
            <table class="table table-bordered table-hover table-sm mb-0">
                <thead class="bg-light">
                    <tr>
                        <th scope="col">Transaction Date</th>
                        <th scope="col">Transaction</th>
                        <th scope="col">Voucher Number</th>
                        <th scope="col" class="text-right">Opening Stock</th>
                        <th scope="col" class="text-right">Qty In</th>
                        <th scope="col" class="text-right">Qty Out</th>
                        <th scope="col" class="text-right">Closing Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-light">
                        <td>{{ \Carbon\Carbon::parse($stockLedger['from'])->format('d/m/Y') }}</td>
                        <th scope="row" colspan="2">Opening stock</th>
                        <td class="text-right">{{ number_format($stockLedger['opening'], 2) }}</td>
                        <td class="text-right">—</td>
                        <td class="text-right">—</td>
                        <td class="text-right">{{ number_format($stockLedger['opening'], 2) }}</td>
                    </tr>
                    @forelse($stockLedger['rows'] as $movement)
                        <tr>
                            <td class="text-nowrap">{{ \Carbon\Carbon::parse($movement['date'])->format('d/m/Y') }}</td>
                            <td>{{ $movement['transaction'] }}</td>
                            <td class="text-nowrap">
                                @if($movement['voucher_url'])
                                    <a href="{{ $movement['voucher_url'] }}">{{ $movement['voucher'] }}</a>
                                @else
                                    <span class="text-muted">{{ $movement['voucher'] }}</span>
                                @endif
                            </td>
                            <td class="text-right">{{ number_format($movement['opening'], 2) }}</td>
                            <td class="text-right">{{ number_format($movement['in'], 2) }}</td>
                            <td class="text-right">{{ number_format($movement['out'], 2) }}</td>
                            <td class="text-right {{ $movement['closing'] < 0 ? 'text-danger' : '' }}">{{ number_format($movement['closing'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No stock movements in this date range.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-light">
                    <tr>
                        <th colspan="3" scope="row">Period totals / Closing stock</th>
                        <th class="text-right">{{ number_format($stockLedger['opening'], 2) }}</th>
                        <th class="text-right">{{ number_format($stockLedger['total_in'], 2) }}</th>
                        <th class="text-right">{{ number_format($stockLedger['total_out'], 2) }}</th>
                        <th class="text-right">{{ number_format($stockLedger['closing'], 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
