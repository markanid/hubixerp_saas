<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $sale->sa_vno }}</title>
    <style>
        * { box-sizing: border-box; }
        body { width: 80mm; margin: 0 auto; font-family: "Courier New", monospace; font-size: 12px; color: #000; }
        .center { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 0; }
        .right { text-align: right; }
        .total { font-weight: bold; font-size: 14px; }
        @media print {
            body { width: 80mm; }
            button { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="center">
        <strong>{{ $company?->company ?? config('app.name') }}</strong><br>
        {{ $company?->address }}<br>
        {{ $company?->phone }}<br>
        @if($company?->gst_no) GST: {{ $company->gst_no }}<br>@endif
    </div>
    <div class="line"></div>
    <div>
        Bill: {{ $sale->sa_vno }}<br>
        Date: {{ \Carbon\Carbon::parse($sale->sa_date)->format('d/m/Y') }}<br>
        Customer: {{ $sale->customer?->customer ?? 'Walk-in' }}
    </div>
    <div class="line"></div>
    <table>
        <thead>
        <tr><th align="left">Item</th><th class="right">Qty</th><th class="right">Amt</th></tr>
        </thead>
        <tbody>
        @foreach($sale->saleDetails as $detail)
            <tr>
                <td>{{ $detail->product?->product ?? $detail->sad_itemid }}</td>
                <td class="right">{{ rtrim(rtrim(number_format($detail->sad_itemqty, 2), '0'), '.') }}</td>
                <td class="right">{{ number_format($detail->sad_total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="line"></div>
    <table>
        <tr><td>Subtotal</td><td class="right">{{ number_format($sale->sa_amount, 2) }}</td></tr>
        <tr><td>Discount</td><td class="right">{{ number_format($sale->sa_discount, 2) }}</td></tr>
        <tr><td>GST</td><td class="right">{{ number_format($sale->sa_gst, 2) }}</td></tr>
        <tr><td>Round</td><td class="right">{{ number_format($sale->sa_round, 2) }}</td></tr>
        <tr class="total"><td>Total</td><td class="right">{{ number_format($sale->sa_amount_payable, 2) }}</td></tr>
        <tr><td>Paid</td><td class="right">{{ number_format($sale->sa_amount_paid, 2) }}</td></tr>
        <tr><td>Balance</td><td class="right">{{ number_format($sale->sa_balance, 2) }}</td></tr>
    </table>
    <div class="line"></div>
    <div class="center">Thank you</div>
    <p class="center"><button onclick="window.print()">Print</button></p>
</body>
</html>
