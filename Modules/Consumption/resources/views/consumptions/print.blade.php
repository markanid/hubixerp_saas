<!DOCTYPE html>
<html>
<head>
    <title>Print Consumption</title>
    <style>
        @media print {
            .card { border: none; box-shadow: none; }
            .dontprint { display: none !important; }
            body { margin: 0; }
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            margin: 20px;
            color: #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .document-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .document-table td {
            border: 1px solid #00000057;
            vertical-align: top;
            padding: 8px;
            font-size: 14px;
        }

        .logo-box {
            height: 80px;
            width: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .logo-box img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
            display: block;
        }

        .bordered td,
        .bordered th {
            border: 1px solid #00000057;
            padding: 6px;
            vertical-align: top;
        }

        .items-table tbody tr.item-row td {
            border-top: none !important;
            border-bottom: none !important;
            border-left: 1px solid #00000057;
            border-right: 1px solid #00000057;
        }

        .items-table tbody tr.total-row td {
            border: 1px solid #00000057 !important;
        }

        .footer-box {
            height: 80px;
            border: 1px solid #00000057;
            padding: 8px;
            vertical-align: top;
        }

        .btn-back {
            background: #343a40;
            color: #fff !important;
            padding: 6px 14px;
            border-radius: 4px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 8px;
        }
    </style>
    @include('partials.print-settings-style', ['printSetting' => $printSetting ?? null])
</head>
<body @if(!($printAgentRender ?? false) && ($printSetting->auto_print ?? true) && ($printSetting->print_method ?? 'browser') === 'browser') onload="window.print();" @endif>
@php
    $consumptionDetails = collect($consumption->consumptionDetails ?? []);
    $totalAmount = $consumptionDetails->sum(fn ($detail) => (float) $detail->cond_total);
    $totalRows = $consumptionDetails->count();
    $showBatch = $consumptionDetails->contains(fn ($detail) => $detail->batchMovements->isNotEmpty() || !empty($detail->batch_no));
@endphp

<div class="card card-outline card-primary">
    <div class="card-body">
        <a class="btn-back dontprint" href="{{ (string) $consumption->status === '0' ? route('consumptions.showCancelled', $consumption->con_id) : route('consumptions.show', $consumption->con_id) }}">Back</a>

        <table class="document-table">
            <colgroup>
                <col style="width: 33.33%;">
                <col style="width: 33.33%;">
                <col style="width: 33.33%;">
            </colgroup>
            <tr>
                <td colspan="3" style="text-align:center;font-weight:bold;font-size:18px;padding:5px;">
                    CONSUMPTION VOUCHER
                </td>
            </tr>
            <tr>
                <td rowspan="2">
                    @if(!empty($company->company_logo))
                        <div class="logo-box">
                            <img src="{{ tenant_asset('company_logos/'.$company->company_logo) }}" alt="Company Logo">
                        </div>
                    @endif
                    <b>{{ $company->company ?? '' }}</b><br>
                    {{ $company->address ?? '' }}<br>
                    Phone: {{ $company->phone ?? '' }}<br>
                    E-Mail: {{ $company->email ?? '' }}
                </td>
                <td>
                    <b>Voucher Date: </b>{{ \Carbon\Carbon::parse($consumption->con_date)->format('d/m/Y') }}<br><br>
                    <b>User: </b>{{ $consumption->user?->user_name ?? '-' }}
                </td>
                <td>
                    <b>Voucher No: </b>{{ $consumption->con_vno }}<br><br>
                    <b>Status: </b>{{ (string) $consumption->status === '0' ? 'Cancelled' : 'Active' }}
                </td>
            </tr>
            <tr>
                <td colspan="2" style="height:36px;">
                    <b>Document Type: </b>Internal stock consumption
                </td>
            </tr>
        </table>

        <table class="bordered items-table" style="margin-top:5px;">
            <thead class="text-center">
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>HSN</th>
                    @if($showBatch)
                        <th>Batch / Expiry</th>
                    @endif
                    <th>Unit Price</th>
                    <th>Qty</th>
                    <th>Total</th>
                    <th>Remark</th>
                </tr>
            </thead>
            <tbody>
                @foreach($consumptionDetails as $detail)
                    <tr class="item-row">
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $detail->product?->product ?? 'Removed item' }}</td>
                        <td>{{ $detail->cond_hsn }}</td>
                        @if($showBatch)
                            <td>
                                @forelse($detail->batchMovements as $movement)
                                    {{ $movement->batch?->batch_no }} ({{ number_format($movement->quantity_out, 2) }})
                                    @if($movement->batch?->expiry_date) - {{ $movement->batch->expiry_date->format('m/Y') }} @endif
                                    @if(!$loop->last)<br>@endif
                                @empty
                                    {{ $detail->batch_no ?? '-' }}
                                @endforelse
                            </td>
                        @endif
                        <td>{{ number_format((float) $detail->cond_uprice, 2) }}</td>
                        <td>{{ number_format((float) $detail->cond_qty, 2).' '.$detail->cond_unit }}</td>
                        <td>{{ number_format((float) $detail->cond_total, 2) }}</td>
                        <td>{{ $detail->cond_remark ?: '-' }}</td>
                    </tr>
                @endforeach
                @for($i = $totalRows; $i < 15; $i++)
                    <tr class="item-row">
                        <td>&nbsp;</td>
                        <td></td>
                        <td></td>
                        @if($showBatch)<td></td>@endif
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                @endfor
                <tr class="total-row">
                    <td colspan="{{ 5 + ($showBatch ? 1 : 0) }}" class="text-right"><b>Total</b></td>
                    <td class="text-right"><b>{{ number_format($totalAmount, 2) }}</b></td>
                    <td></td>
                </tr>
                <tr class="total-row">
                    <td colspan="{{ 4 + ($showBatch ? 1 : 0) }}">
                        <b>Amount in Words:</b> INR {{ numberToWords($totalAmount) }}
                    </td>
                    <td colspan="3" class="text-right">
                        <b>NET TOTAL: Rs. {{ number_format($totalAmount, 2) }}/-</b>
                    </td>
                </tr>
            </tbody>
        </table>

        <table style="margin-top:5px;">
            <tr>
                <td class="footer-box" width="50%">
                    <b>Notes</b><br><br>
                    Stock consumed internally through this voucher.
                </td>
                <td class="footer-box" width="50%" align="right">
                    FOR <b>{{ $company->company ?? '' }}</b><br><br><br><br>
                    Authorised Signatory
                </td>
            </tr>
        </table>

        <p align="center">This is a Computer Generated Voucher</p>
    </div>
</div>
@include('partials.local-print-script', ['printSetting' => $printSetting ?? null, 'documentType' => 'consumption', 'documentId' => $consumption->con_id])
</body>
</html>