<!DOCTYPE html>
<html>
<head>
  <title>Print Sale Return</title>

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
  $returnDetails = collect($return->returnDetails ?? []);
  $total_after_discount = $returnDetails->sum(fn ($returnDetail) => (float) $returnDetail->prd_total);
  $taxable_value = $returnDetails->sum(fn ($returnDetail) => (float) $returnDetail->prd_total - (float) $returnDetail->prd_gst);
  $gst_value = $returnDetails->sum(fn ($returnDetail) => (float) $returnDetail->prd_gst);
  $totalRows = $returnDetails->count();
  $taxType = strtolower((string) ($taxType ?? 'gst'));
  $isVat = $taxType === 'vat';
  $taxLabel = $taxLabel ?? ($isVat ? 'VAT' : 'GST');
  $taxNumberLabel = $isVat ? 'VAT No' : 'GSTIN';
  $showTax = round((float) $gst_value, 2) > 0 || round((float) ($return->pr_gst ?? 0), 2) > 0;
  $invoiceTitle = $showTax ? 'SALE RETURN TAX INVOICE' : 'SALE RETURN INVOICE';
  $state = isset($states) ? $states->firstWhere('state_code', $return->pr_state_code) : null;
@endphp

<div class="card card-outline card-primary">
<div class="card-body">

  <a class="btn-back dontprint" href="{{ (string) $return->status === '0' ? route('sales-returns.showCancelled', $return->pr_id) : route('sales-returns.show', $return->pr_id) }}">Back</a>

  <table class="invoice-table">
    <colgroup>
      <col style="width:33.33%;">
      <col style="width:33.33%;">
      <col style="width:33.33%;">
    </colgroup>

    <tr>
      <td colspan="3" style="text-align:center;font-weight:bold;font-size:18px;padding:5px;">
        {{ $invoiceTitle }}
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
        {{ $taxNumberLabel }}: {{ $company->gst_no ?? '-' }}
      </td>

      <td>
        <b>Return Date: </b>{{ \Carbon\Carbon::parse($return->pr_date)->format('d/m/Y') }}<br><br>
        @if(!$isVat && $showTax)
          <b>State: </b>{{ $state ? $state->state_name : '-' }}<br>
          <b>Code: </b>{{ $state ? $state->state_code : '-' }}
        @else
          <b>Payment Mode: </b>{{ $return->banking?->bk_bank ?? '-' }}
        @endif
      </td>

      <td>
        <b>Return No: </b>{{ $return->pr_vno }}<br><br>
        <b>Sale Bill No: </b>{{ $return->pr_pvno ?? '-' }}<br><br>
        <b>User: </b>{{ $return->user?->user_name ?? '-' }}
      </td>
    </tr>

    <tr>
      <td colspan="2" style="height:36px;">
        <b>Status: </b>{{ (string) $return->status === '0' ? 'Cancelled' : 'Active' }}
      </td>
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
              <b>Bill To:</b><br>
              {{ $return->customer?->customer ?? '-' }}<br>
              {{ $return->customer?->address ?? '-' }}<br>
              Phone: {{ $return->customer?->phone ?? '-' }}<br>
              {{ $taxNumberLabel }}: {{ $return->customer?->gstin_no ?? '-' }}
            </td>
            <td style="padding:8px;">
              <b>Return Against:</b><br>
              {{ $return->pr_pvno ?? '-' }}<br><br>
              <b>Payment Mode:</b> {{ $return->banking?->bk_bank ?? '-' }}
            </td>
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
        <th>HSN</th>
        <th>Unit Price</th>
        <th>Qty</th>
        <th>Total</th>
        @if($showTax)
          <th>Taxable</th>
          <th>{{ $taxLabel }}</th>
        @endif
      </tr>
    </thead>

    <tbody>
      @foreach($returnDetails as $returnDetail)
      <tr class="item-row">
        <td>{{ $loop->iteration }}</td>
        <td>{{ $returnDetail->product?->product ?? 'Removed item' }}</td>
        <td>{{ $returnDetail->prd_hsn }}</td>
        <td>{{ number_format((float) $returnDetail->prd_uprice, 2) }}</td>
        <td>{{ $returnDetail->prd_itemqty.' '.$returnDetail->prd_unit }}</td>
        <td>{{ number_format((float) $returnDetail->prd_total, 2) }}</td>
        @if($showTax)
          <td>{{ number_format((float) $returnDetail->prd_total - (float) $returnDetail->prd_gst, 2) }}</td>
          <td>{{ number_format((float) $returnDetail->prd_gst, 2).' ('.($returnDetail->product?->gst ?? 0).'%)' }}</td>
        @endif
      </tr>
      @endforeach

      @for($i = $totalRows; $i < 15; $i++)
      <tr class="item-row">
        <td>&nbsp;</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        @if($showTax)
          <td></td>
          <td></td>
        @endif
      </tr>
      @endfor
    </tbody>

    <tr class="total-row">
      <td colspan="5" class="text-right font-weight-bold">Total</td>
      <td class="text-right">{{ number_format($total_after_discount, 2) }}</td>
      @if($showTax)
        <td class="text-right">{{ number_format($taxable_value, 2) }}</td>
        <td class="text-right">{{ number_format($gst_value, 2) }}</td>
      @endif
    </tr>

    @if($showTax && $isVat)
      <tr class="total-row">
        <td colspan="6"></td>
        <td colspan="2" class="text-right">
          {{ $taxLabel }}: {{ number_format($gst_value, 2) }}
        </td>
      </tr>
    @elseif($showTax)
      <tr class="total-row">
        <td colspan="6"></td>
        @if(($return->pr_is_igst ?? 0) == 1)
          <td colspan="2" class="text-right">
            IGST: {{ number_format($gst_value, 2) }}
          </td>
        @else
          <td class="text-right">
            CGST: {{ number_format($gst_value / 2, 2) }}
          </td>
          <td class="text-right">
            SGST: {{ number_format($gst_value / 2, 2) }}
          </td>
        @endif
      </tr>
    @endif
  </table>

  <table class="bordered" style="margin-top:5px;">
    <tr>
      <td width="65%">
        <b>Amount in Words:</b> INR {{ numberToWords($return->pr_amount_payable) }}<br>
        <b>Total Returned:</b> Rs. {{ number_format($total_after_discount, 2) }}/-
      </td>
      <td width="35%" class="text-right">
        Round Off: Rs. {{ number_format((float) $return->pr_round, 2) }}/-<br>
        <b>NET TOTAL: Rs. {{ number_format((float) $return->pr_amount_payable, 2) }}/-</b><br>
        Paid: Rs. {{ number_format((float) $return->pr_amount_paid, 2) }}/-<br>
        Balance: Rs. {{ number_format((float) $return->pr_balance, 2) }}/-
      </td>
    </tr>
  </table>

  <table class="bordered" style="margin-top:5px;">
    <tr>
      <td width="65%">
        <b>Declaration:</b>
        We declare that this sale return records goods returned by the customer and all particulars are true and correct.
      </td>
      <td width="35%" style="padding:0;">
        <table style="width:100%; border-collapse:collapse;">
          <tr>
            <td style="padding:8px; border-bottom:1px solid #00000057;">
              <b>Old Balance:</b> {{ number_format($customerOpening ?? 0, 2) }}
            </td>
          </tr>
          <tr>
            <td style="padding:8px;">
              <b>Total Payable:</b> {{ number_format($customerClosing ?? 0, 2) }}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <table style="margin-top:5px;">
    <tr>
      <td class="footer-box" width="33%">
        <b>COMPANY BANK DETAILS</b><br><br>
        <b>BANK: </b>{{ $company->bank_name ?? '-' }}<br>
        <b>BRANCH: </b>{{ $company->bank_branch ?? '-' }}<br>
        <b>ACCOUNT NO: </b>{{ $company->bank_acno ?? '-' }}<br>
        <b>IFSC: </b>{{ $company->bank_ifsc ?? '-' }}
      </td>
      <td class="footer-box" width="33%" style="text-align:center; vertical-align:top;">
        @if(!empty($company->qr_code))
          <div style="width:110px;height:110px;margin:0 auto;border:1px solid #00000057;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:4px;">
            <img src="{{ tenant_asset('company_qr_codes/'.$company->qr_code) }}" alt="Google Pay QR" style="max-width:100%; max-height:100%; object-fit:contain; display:block;">
          </div>
        @endif
        @if(!empty($company->upi_id))
          <div style="margin-top:6px; font-size:12px;">
            <b>UPI:</b> {{ $company->upi_id }}
          </div>
        @endif
        <div style="font-weight:bold; margin-top:6px;">Google Pay / UPI</div>
      </td>
      <td class="footer-box" width="33%" align="right">
        FOR <b>{{ $company->company ?? '-' }}</b><br><br><br><br><br>
        Authorised Signatory
      </td>
    </tr>
  </table>

  <p align="center">This is a Computer Generated Sale Return Invoice<br>Thank You - Visit Again</p>

</div>
</div>

@include('partials.local-print-script', ['printSetting' => $printSetting ?? null, 'documentType' => 'sale_return', 'documentId' => $return->pr_id])
</body>
</html>