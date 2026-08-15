<!DOCTYPE html>
<html>
<head>
  <title>Print Invoice</title>

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

    /* ✅ FIXED LOGO */
    .logo-box{
      border:0px solid #00000057;
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

    /* Remove inner horizontal borders ONLY for item rows */
    .items-table tbody tr.item-row td{
      border-top:none !important;
      border-bottom:none !important;
      border-left:1px solid #00000057;
      border-right:1px solid #00000057;
    }

    /* Keep borders for TOTAL rows */
    .items-table tbody tr.total-row td{
      border:1px solid #00000057 !important;
    }

    .footer-box{
      height:60px;
      border:1px solid #00000057;
      padding:8px;
      vertical-align:top;
    }

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
  $saleDetails = collect($sale->saleDetails ?? []);
  $total_before_discount = $saleDetails->sum(fn ($saleDetail) => (float) $saleDetail->sad_price);
  $discount_amount = $saleDetails->sum(fn ($saleDetail) => (float) $saleDetail->sad_adisc);
  $total_after_discount = $saleDetails->sum(fn ($saleDetail) => (float) $saleDetail->sad_total);
  $taxable_value = $saleDetails->sum(fn ($saleDetail) => (float) $saleDetail->sad_total - (float) $saleDetail->sad_gst);
  $gst_value = $saleDetails->sum(fn ($saleDetail) => (float) $saleDetail->sad_gst);
  $totalRows = $saleDetails->count();
  $taxType = strtolower((string) ($taxType ?? 'gst'));
  $isVat = $taxType === 'vat';
  $taxLabel = $taxLabel ?? ($isVat ? 'VAT' : 'GST');
  $taxNumberLabel = $isVat ? 'VAT No' : 'GSTIN';
  $showTax = (bool) ($collectTax ?? true) && (string) ($sale->sa_type ?? '1') !== '0';
  $invoiceTitle = ($isComposition ?? false) ? 'BILL OF SUPPLY' : ($showTax ? 'TAX INVOICE' : 'INVOICE');
  $showBatch = $saleDetails->contains(fn($detail) => $detail->batchMovements->isNotEmpty());
  $showMrpLot = $saleDetails->contains(fn($detail) => !empty($detail->mrp_stock_lot_id));
  $showInventoryLot = $showBatch || $showMrpLot;
@endphp

<div class="card card-outline card-primary">
<div class="card-body">
  <a class="btn-back dontprint" href="{{ (string) $sale->status === '0' ? route('sales.showCancelled', $sale->sa_id) : route('sales.show', $sale->sa_id) }}">Back</a>

  <!-- ✅ HEADER TABLE -->
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
        <div class="logo-box">
          <img src="{{ tenant_asset('company_logos/'.$company->company_logo) }}" alt="Company Logo">
        </div>
        <b>{{ $company->company }}</b><br>
        {{ $company->address }}<br>
        Phone: {{ $company->phone }}<br>
        E-Mail: {{ $company->email }}<br>
        {{ $taxNumberLabel }}: {{ $company->gst_no }}
      </td>

      <td>
        <b>Invoice Type: </b>
        {{ 
            $sale->sa_type == 1 ? 'B2C' : 
            ($sale->sa_type == 2 ? 'B2B' : 'Legacy No '.$taxLabel) 
        }}<br><br>
        <b>Invoice Date: </b>{{ \Carbon\Carbon::parse($sale->sa_date)->format('d/m/Y') }}<br><br>
        @php
            $state = $states->firstWhere('state_code', $sale->sa_state_code);
        @endphp
        @if(!$isVat && $showTax)
        <b>State: </b>{{ $state ? $state->state_name : '-' }}<br>
        <b>Code: </b>{{ $state ? $state->state_code : '-' }}
        @endif
      </td>

      <td>
        <b>Invoice No: </b>{{ $sale->sa_vno }}<br><br>
        <b>{{ $isVat ? 'E-Invoice' : 'E-way Bill No' }}: </b>{{ $sale->sa_eway_bill_no ?? '-' }}<br><br>
        <b>Vehicle No: </b>{{ $sale->sa_vehicle ?? '-' }}
      </td>
    </tr>

    <tr>
      <td colspan="2" style="height:36px;">
        <b>Remark: </b>{{ $sale->sa_remark ?? '-' }}
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
              {{ $sale->customer?->customer ?? '-' }}<br>
              {{ $sale->customer?->address ?? '-' }}<br>
              Phone : {{ $sale->customer?->phone ?? '-' }}<br>
              {{ $taxNumberLabel }} : {{ $sale->customer?->gstin_no ?? '-' }}
            </td>
            <td style="padding:8px;">
              <b>Ship To:</b><br>
              {{ $sale->sa_loc }}<br><br>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
  <!-- ✅ END HEADER TABLE -->

  <!-- ✅ ITEM TABLE -->
  <table class="bordered items-table" style="margin-top:5px;">
    <thead class="text-center">
      <tr>
        <th>#</th>
        <th>Product</th>
        <th>HSN</th>
        @if($saleSettings['manufacturing_date'] ?? false)
          <th>MFG DATE</th>
        @endif
        @if($showBatch)
          <th>Batch / Expiry</th>
        @endif
        @if($showMrpLot)
          <th>Purchase Lot / MRP</th>
        @endif
        <th>Unit Price</th>
        <th>Qty</th>
        <th>Before Disc</th>
        <th>Disc</th>
        <th>Total</th>
        @if($showTax)
          <th>Taxable</th>
          <th>{{ $taxLabel }}</th>
        @endif
      </tr>
    </thead>

    <tbody>
        @foreach($saleDetails as $saleDetail)
        <tr class="item-row">
            <td>{{ $loop->iteration }}</td>
            <td>{{ $saleDetail->product?->product ?? 'Removed item' }}</td>
            <td>{{ $saleDetail->sad_hsn }}</td>
            @if($saleSettings['manufacturing_date'] ?? false)
            <td>{{$saleDetail->manufacturing_date}}</td>
            @endif
            @if($showBatch)
            <td>
                @forelse($saleDetail->batchMovements as $movement)
                    {{ $movement->batch?->batch_no }} ({{ number_format($movement->quantity_out, 2) }})
                    @if($movement->batch?->expiry_date) - {{ $movement->batch->expiry_date->format('m/Y') }} @endif
                    @if(!$loop->last)<br>@endif
                @empty - @endforelse
            </td>
            @endif
            @if($showMrpLot)
            <td>
                {{ $saleDetail->mrpStockLot?->purchase_voucher ?: '-' }}
                @if($saleDetail->mrpStockLot?->purchase_date) - {{ $saleDetail->mrpStockLot->purchase_date->format('d/m/Y') }} @endif
                <br>MRP {{ number_format($saleDetail->stock_mrp ?? 0, 2) }}
            </td>
            @endif
            <td>{{ number_format($saleDetail->sad_uprice,2) }}</td>
            <td>{{ $saleDetail->sad_itemqty.' '.$saleDetail->sad_unit }}</td>
            <td>{{ number_format($saleDetail->sad_price,2) }}</td>
            <td>{{ $saleDetail->sad_adisc.' ('.$saleDetail->sad_pdisc.'%)' }}</td>
            <td>{{ number_format($saleDetail->sad_total,2) }}</td>
            @if($showTax)
            <td>{{ number_format($saleDetail->sad_total - $saleDetail->sad_gst,2) }}</td>
            <td>{{ number_format($saleDetail->sad_gst,2).' ('.($saleDetail->product?->gst ?? 0).'%)' }}</td>
            @endif
        </tr>
        @endforeach
        @for($i = $totalRows; $i < 15; $i++)
        <tr class="item-row">
            <td>&nbsp;</td>
            <td></td>
            <td></td>
            @if($saleSettings['manufacturing_date'] ?? false)
            <td></td>
            @endif
            @if($showBatch)
            <td></td>
            @endif
            @if($showMrpLot)
            <td></td>
            @endif
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
    <!-- TOTALS -->
        <tr class="total-row">
        <td colspan="{{ 5 + (($saleSettings['manufacturing_date'] ?? false) ? 1 : 0) + ($showInventoryLot ? 1 : 0) }}" class="text-right font-weight-bold">Total</td>
        <td class="text-right">{{ number_format($total_before_discount, 2) }}</td>
        <td class="text-right">{{ number_format($discount_amount, 2) }}</td>
        <td class="text-right">{{ number_format($total_after_discount, 2) }}</td>
        @if($showTax)
          <td class="text-right">{{ number_format($taxable_value, 2) }}</td>
          <td class="text-right">{{ number_format($gst_value, 2) }}</td>
        @endif
        </tr>
        @if($showTax && $isVat)
        <tr class="total-row">
        <td colspan="{{ 8 + (($saleSettings['manufacturing_date'] ?? false) ? 1 : 0) + ($showInventoryLot ? 1 : 0) }}"></td>
            <td colspan="2" class="text-right">
                {{ $taxLabel }}: {{ number_format($gst_value, 2) }}
            </td>
        </tr>
        @elseif($showTax && !$isVat)
        <tr class="total-row">
        <td colspan="{{ 8 + (($saleSettings['manufacturing_date'] ?? false) ? 1 : 0) + ($showInventoryLot ? 1 : 0) }}"></td>
        @if(($sale->sa_is_igst ?? 0) == 1)
            {{-- IGST (merge) --}}
            <td colspan="2" class="text-right">
                IGST: {{ number_format($gst_value, 2) }}
            </td>
        @else
            {{-- CGST + SGST --}}
            <td class="text-right">
                CGST: {{ number_format($gst_value/2, 2) }}
            </td>
            <td class="text-right">
                SGST: {{ number_format($gst_value/2, 2) }}
            </td>
        @endif
        </tr>
        @endif
        <tr class="total-row">
        <td colspan="{{ $showTax ? (7 + (($saleSettings['manufacturing_date'] ?? false) ? 1 : 0) + ($showInventoryLot ? 1 : 0)) : (5 + (($saleSettings['manufacturing_date'] ?? false) ? 1 : 0) + ($showInventoryLot ? 1 : 0)) }}">
            <b>Amount in Words:</b> INR {{ numberToWords($sale->sa_amount_payable) }}<br>
            <b>You Saved: </b>₹ {{ number_format($discount_amount, 2) }}/-
        </td>
        <td colspan="3" class="text-right">
            Round Off: ₹ {{ number_format($sale->sa_round,2) }}/-<br>
            <b>NET TOTAL: ₹ {{ number_format($sale->sa_amount_payable,2) }}/-</b>
        </td>
        </tr>
  </table>

  <!-- ✅ DECLARATION + BALANCE -->
  <table class="bordered" style="margin-top:5px;">
    <tr>
      <td width="65%">
        <b>Declaration:</b>
        We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.
      </td>
      <td width="35%" style="padding:0;">
        <table style="width:100%; border-collapse:collapse;">
          <tr>
            <td style="padding:8px; border-bottom:1px solid #00000057;">
              <b>Old Balance:</b>{{ number_format($customerOpening ?? 0, 2) }}
            </td>
          </tr>
          <tr>
            <td style="padding:8px;">
              <b>Total Payable:</b>{{ number_format($customerClosing ?? 0, 2) }}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- ✅ FOOTER -->
  <table style="margin-top:5px;">
    <tr>
      <td class="footer-box" width="33%">
        <b>COMPANY BANK DETAILS</b><br><br>
        <b>BANK: </b>{{ $company->bank_name }}<br>
        <b>BRANCH: </b>{{ $company->bank_branch }}<br>
        <b>ACCOUNT NO: </b>{{ $company->bank_acno }}<br>
        <b>IFSC: </b>{{ $company->bank_ifsc }}
      </td>
      <td class="footer-box" width="33%" style="text-align:center; vertical-align:top;">
        <div style="width:110px;height:110px;margin:0 auto;border:1px solid #00000057;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:4px;">
            <img src="{{ tenant_asset('company_qr_codes/'.$company->qr_code) }}" alt="Google Pay QR" style="max-width:100%; max-height:100%; object-fit:contain; display:block;">
        </div>
        @if(!empty($company->upi_id))
            <div style="margin-top:6px; font-size:12px;">
                <b>UPI:</b> {{ $company->upi_id }}
            </div>
        @endif
        <div style="font-weight:bold; margin-top:6px;">Google Pay / UPI</div>
    </td>
    <td class="footer-box" width="33%" align="right"> FOR <b>{{ $company->company }}</b><br><br><br><br><br> Authorised Signatory </td>
    </tr>
  </table>

  <p align="center">This is a Computer Generated Invoice<br>Thank You - Visit Again</p>

</div>
</div>

@include('partials.local-print-script', ['printSetting' => $printSetting ?? null, 'documentType' => 'sale', 'documentId' => $sale->sa_id])
</body>
</html>