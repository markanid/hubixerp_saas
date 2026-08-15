<!DOCTYPE html>
<html>
<head>
  <title>Print Estimation</title>

  <style>
    @media print{
      .card{ border:none; box-shadow:none; }
      .dontprint{ display:none !important; }
      body{ margin:0; }
    }

    body{
      font-family: Arial, Helvetica, sans-serif;
      font-size:13px;
      margin:20px;
      color:#000;
    }

    table{ width:100%; border-collapse:collapse; }

    .invoice-table{
      width:100%;
      border-collapse:collapse;
      table-layout:fixed;
    }

    .invoice-table td{
      border:1px solid #00000057;
      vertical-align:top;
      padding:8px;
      font-size:14px;
    }

    .logo-box{
      border:0;
      height:80px;
      width:150px;
      display:flex;
      align-items:center;
      justify-content:center;
      overflow:hidden;
      margin-bottom:10px;
    }

    .logo-box img{
      max-height:100%;
      max-width:100%;
      object-fit:contain;
      display:block;
    }

    .bordered td, .bordered th{
      border:1px solid #00000057;
      padding:6px;
      vertical-align:top;
    }

    .items-table tbody tr.item-row td{
      border-top:none !important;
      border-bottom:none !important;
      border-left:1px solid #00000057;
      border-right:1px solid #00000057;
    }

    .items-table tbody tr.total-row td{
      border:1px solid #00000057 !important;
    }

    .footer-box{
      height:60px;
      border:1px solid #00000057;
      padding:8px;
      vertical-align:top;
    }

    .text-right{ text-align:right; }
    .text-center{ text-align:center; }
    .font-weight-bold{ font-weight:bold; }

    .btn-back{
      background:#343a40;
      color:#fff !important;
      padding:6px 14px;
      border-radius:4px;
      font-weight:600;
      text-decoration:none;
      display:inline-block;
      margin-bottom:8px;
    }
  </style>
  @include('partials.print-settings-style', ['printSetting' => $printSetting ?? null])
</head>

<body @if(!($printAgentRender ?? false) && ($printSetting->auto_print ?? true) && ($printSetting->print_method ?? 'browser') === 'browser') onload="window.print();" @endif>

@php
  $estimationDetails = collect($estimation->estimationDetails ?? []);
  $total_before_discount = $estimationDetails->sum(fn ($estimationDetail) => (float) $estimationDetail->esd_price);
  $discount_amount = $estimationDetails->sum(fn ($estimationDetail) => (float) $estimationDetail->esd_adisc);
  $total_after_discount = $estimationDetails->sum(fn ($estimationDetail) => (float) $estimationDetail->esd_total);
  $totalRows = $estimationDetails->count();
@endphp

<div class="card card-outline card-primary">
<div class="card-body">

  <a class="btn-back dontprint" href="{{ (string) $estimation->status === '0' ? route('estimations.showCancelled', $estimation->es_id) : route('estimations.show', $estimation->es_id) }}">Back</a>

  <table class="invoice-table">
    <colgroup>
      <col style="width:33.33%;">
      <col style="width:33.33%;">
      <col style="width:33.33%;">
    </colgroup>

    <tr>
      <td colspan="3" style="text-align:center;font-weight:bold;font-size:18px;padding:5px;">
        ESTIMATION
      </td>
    </tr>

    <tr>
      <td rowspan="2">
        @if(!empty($company->company_logo))
          <div class="logo-box">
            <img src="{{ tenant_asset('company_logos/'.$company->company_logo) }}" alt="Company Logo">
          </div>
        @endif
        <b>{{ $company->company ?? '-' }}</b><br>
        {{ $company->address ?? '-' }}<br>
        Phone: {{ $company->phone ?? '-' }}<br>
        E-Mail: {{ $company->email ?? '-' }}<br>
        GST/VAT No: {{ $company->gst_no ?? '-' }}
      </td>

      <td>
        <b>Estimation Date: </b>{{ \Carbon\Carbon::parse($estimation->es_date)->format('d/m/Y') }}<br><br>
        <b>Document Type: </b>Estimation<br><br>
        <b>Account Effect: </b>{{ $estimation->es_account_effect ? 'Yes' : 'No' }}
      </td>

      <td>
        <b>Estimation No: </b>{{ $estimation->es_vno }}<br><br>
        <b>User: </b>{{ $estimation->user?->user_name ?? '-' }}
      </td>
    </tr>

    <tr>
      <td colspan="2" style="height:36px;"></td>
    </tr>

    <tr>
      <td colspan="3" style="padding:0;">
        <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
          <colgroup>
            <col style="width:50%;">
            <col style="width:50%;">
          </colgroup>
          <tr>
            <td style="padding:8px; border-right:1px solid #00000057;">
              <b>Customer:</b><br>
              {{ $estimation->customer?->customer ?? '-' }}<br>
              {{ $estimation->customer?->address ?? '-' }}<br>
              Phone: {{ $estimation->customer?->phone ?? '-' }}<br>
              GST/VAT No: {{ $estimation->customer?->gstin_no ?? '-' }}
            </td>
            <td style="padding:8px;"></td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <table class="bordered items-table" style="margin-top:5px;">
    <thead class="text-center">
      <tr>
        <th>#</th>
        <th>Product</th>
        <th>Unit Price</th>
        <th>Qty</th>
        <th>Before Disc</th>
        <th>Disc</th>
        <th>Total</th>
      </tr>
    </thead>

    <tbody>
      @foreach($estimationDetails as $estimationDetail)
      <tr class="item-row">
        <td>{{ $loop->iteration }}</td>
        <td>{{ $estimationDetail->product?->product ?? 'Removed item' }}</td>
        <td>{{ number_format((float) $estimationDetail->esd_uprice, 2) }}</td>
        <td>{{ $estimationDetail->esd_itemqty.' '.$estimationDetail->esd_unit }}</td>
        <td>{{ number_format((float) $estimationDetail->esd_price, 2) }}</td>
        <td>{{ number_format((float) $estimationDetail->esd_adisc, 2).' ('.$estimationDetail->esd_pdisc.'%)' }}</td>
        <td>{{ number_format((float) $estimationDetail->esd_total, 2) }}</td>
      </tr>
      @endforeach

      @for($i = $totalRows; $i < 12; $i++)
      <tr class="item-row">
        <td>&nbsp;</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
      </tr>
      @endfor
    </tbody>

    <tr class="total-row">
      <td colspan="4" class="text-right font-weight-bold">Total</td>
      <td class="text-right">{{ number_format($total_before_discount, 2) }}</td>
      <td class="text-right">{{ number_format($discount_amount, 2) }}</td>
      <td class="text-right">{{ number_format($total_after_discount, 2) }}</td>
    </tr>
  </table>

  <table class="bordered" style="margin-top:5px;">
    <tr>
      <td width="65%">
        <b>Amount in Words:</b> INR {{ numberToWords($estimation->es_amount_payable) }}<br>
        <b>Discount:</b> Rs. {{ number_format($discount_amount, 2) }}/-
      </td>
      <td width="35%" class="text-right">
        Round Off: Rs. {{ number_format((float) $estimation->es_round, 2) }}/-<br>
        <b>NET TOTAL: Rs. {{ number_format((float) $estimation->es_amount_payable, 2) }}/-</b>
        @if($estimation->es_account_effect)
          <br>Payment Mode: {{ $estimation->banking->bk_bank ?? '-' }}
          <br>Amount Paid: Rs. {{ number_format((float) $estimation->es_amount_paid, 2) }}/-
          <br>Balance: Rs. {{ number_format((float) $estimation->es_balance, 2) }}/-
          <br>Due Date: {{ $estimation->es_due_date ? \Carbon\Carbon::parse($estimation->es_due_date)->format('d/m/Y') : '-' }}
        @endif
      </td>
    </tr>
  </table>

  <table class="bordered" style="margin-top:5px;">
    <tr>
      <td width="65%">
        <b>Terms:</b>
        This estimate is prepared for reference and confirmation. Final invoice values may vary at billing time.
      </td>
      <td width="35%" align="right">
        FOR <b>{{ $company->company ?? '-' }}</b><br><br><br><br>
        Authorised Signatory
      </td>
    </tr>
  </table>

  <p align="center">This is a Computer Generated Estimation<br>Thank You</p>

</div>
</div>

@include('partials.local-print-script', ['printSetting' => $printSetting ?? null, 'documentType' => 'estimation', 'documentId' => $estimation->es_id])
</body>
</html>