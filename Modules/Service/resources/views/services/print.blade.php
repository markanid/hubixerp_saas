<!DOCTYPE html>
<html>
   <head>
      <title>Service Invoice</title>
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
         .bordered td,.bordered th{
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
         $taxType = strtolower((string) ($taxType ?? 'gst'));
          $isVat = $taxType === 'vat';
          $taxLabel = $taxLabel ?? ($isVat ? 'VAT' : 'GST');
          $taxNumberLabel = $isVat ? 'VAT No' : 'GSTIN';
          $showTax = (bool) ($collectTax ?? true) && (string) ($service->sv_type ?? '1') !== '0';
          $invoiceTitle = ($isComposition ?? false) ? 'BILL OF SUPPLY' : ($showTax ? 'TAX INVOICE' : 'INVOICE');
          $invoiceType = (string) ($service->sv_type ?? '1') === '2' ? 'B2B' : ((string) ($service->sv_type ?? '1') === '0' ? 'Legacy No '.$taxLabel : 'B2C');
         $serviceItems = collect($serviceItems ?? []);
         $saleItems = collect($saleItems ?? []);
         $hasServiceItems = $serviceItems->isNotEmpty();
         $hasSaleItems = $saleItems->isNotEmpty();
         $showManufacturingDate = $saleItems->contains(fn ($row) => !empty($row->manufacturing_date));
         $saleLeadingColspan = 5 + ($showManufacturingDate ? 1 : 0);
         $service_total = $serviceItems->sum(fn ($row) => (float) $row->svd_total);
         $service_discount_amount = $serviceItems->sum(fn ($row) => (float) $row->svd_adisc);
         $service_taxable = $serviceItems->sum(fn ($row) => (float) $row->svd_total - (float) $row->svd_gst);
         $service_gst = $serviceItems->sum(fn ($row) => (float) $row->svd_gst);
         $total_before_discount = $saleItems->sum(fn ($row) => (float) $row->svd_price);
         $discount_amount = $saleItems->sum(fn ($row) => (float) $row->svd_adisc);
         $total_after_discount = $saleItems->sum(fn ($row) => (float) $row->svd_total);
         $taxable_value = $saleItems->sum(fn ($row) => (float) $row->svd_total - (float) $row->svd_gst);
         $gst_value = $saleItems->sum(fn ($row) => (float) $row->svd_gst);
         $grand_total = $total_after_discount + $service_total;
         $total_gst = $gst_value + $service_gst;
         $total_discount = $discount_amount + $service_discount_amount;
         $saleTaxSummaryColspan = 8 + ($showManufacturingDate ? 1 : 0);
         $saleAmountWordsColspan = $showTax ? (8 + ($showManufacturingDate ? 1 : 0)) : (5 + ($showManufacturingDate ? 1 : 0));
         $saleNetTotalColspan = $showTax ? 2 : 3;
      @endphp
      <div class="card card-outline card-primary">
         <div class="card-body">
            <a class="btn-back dontprint" href="{{ (string) $service->status === '0' ? route('services.showCancelled', $service->sv_id) : route('services.show', $service->sv_id) }}">Back</a>

            <!-- HEADER -->
            <table class="invoice-table">
               <tr>
                  <td colspan="3" style="text-align:center;font-weight:bold;font-size:18px;">
                     {{ $invoiceTitle }}
                  </td>
               </tr>
               <tr>
                  <td rowspan="2">
                     <div class="logo-box">
                        <img src="{{ tenant_asset('company_logos/'.$company->company_logo) }}">
                     </div>
                     <b>{{ $company->company }}</b><br>
                     {{ $company->address }}<br>
                     Phone : {{ $company->phone }}<br>
                     Email : {{ $company->email }}<br>
                     {{ $taxNumberLabel }} : {{ $company->gst_no }}
                  </td>
                  <td>
                     <b>Invoice Type :</b> {{ $invoiceType }}<br><br>
                     <b>Invoice Date :</b>
                     {{ \Carbon\Carbon::parse($service->sv_date)->format('d/m/Y') }}<br><br>
                    @php
                        $state = $states->firstWhere('state_code', $service->sv_state_code);
                    @endphp
                    @if(!$isVat && $showTax)
                    <b>State: </b>{{ $state ? $state->state_name : '-' }}<br>
                    <b>Code: </b>{{ $state ? $state->state_code : '-' }}
                    @endif
                  </td>
                  <td>
                    <b>Invoice No :</b>{{ $service->sv_vno }}<br><br>
                    <b>{{ $isVat ? 'E-Invoice' : 'E-way Bill No' }}: </b>{{ $service->sv_eway_bill_no ?? '-' }}<br><br>
                    <b>Vehicle :</b>{{ $service->sv_vehicle }}
                  </td>
               </tr>
               <tr>
                  <td colspan="2">
                     <b>Remark :</b>
                     {{ $service->sv_remark ?? '-' }}
                  </td>
               </tr>
               <tr>
                  <td colspan="3" style="padding:0;">
                     <table style="width:100%; border-collapse:collapse;">
                        <tr>
                           <td style="padding:8px;border-right:1px solid #00000057;">
                              <b>Bill To :</b><br>
                              {{ $service->customer?->customer ?? '-' }}<br>
                              {{ $service->customer?->address ?? '-' }}<br>
                                Phone : {{ $service->customer?->phone ?? '-' }}
                                {{ $taxNumberLabel }} : {{ $service->customer?->gstin_no ?? '-' }}
                           </td>
                           <td style="padding:8px;">
                              <b>Ship To :</b><br>
                              {{ $service->sv_loc }}
                           </td>
                        </tr>
                     </table>
                  </td>
               </tr>
            </table>
            @if($hasServiceItems)
            <!-- SERVICE TABLE -->
            <h4 style="margin-top:8px;">Service Details</h4>
            <table class="bordered items-table">
               <thead>
                  <tr>
                     <th>#</th>
                     <th>Service</th>
                     <th>Remark</th>
                     <th>Price</th>
                     <th>Total</th>
                     @if($showTax)
                     <th>Taxable</th>
                     <th>{{ $taxLabel }}</th>
                     @endif
                  </tr>
               </thead>
               <tbody>
                  @foreach($serviceItems as $row)
                  <tr class="item-row">
                     <td>{{ $loop->iteration }}</td>
                     <td>{{ $row->product?->product ?? 'Removed item' }}</td>
                     <td>{{ $row->svd_remark }}</td>
                     <td>{{ number_format($row->svd_price,2) }}</td>
                     <td>{{ number_format($row->svd_total,2) }}</td>
                     @if($showTax)
                     <td>{{ number_format($row->svd_total - $row->svd_gst,2) }}</td>
                     <td>{{ number_format($row->svd_gst,2) }}</td>
                     @endif
                  </tr>
                  @endforeach
               </tbody>
               <tr class="total-row">
                    <td colspan="3" class="text-right"><b>Total</b></td>
                    <td><b>{{ number_format($service_total,2) }}</b></td>
                    <td><b>{{ number_format($service_total,2) }}</b></td>
                    @if($showTax)
                    <td><b>{{ number_format($service_taxable,2) }}</b></td>
                    <td><b>{{ number_format($service_gst,2) }}</b></td>
                    @endif
                </tr>
            </table>
            @endif

            @if($hasSaleItems)
            <!-- SALE TABLE -->
            <h4 style="margin-top:8px;">Sale Details</h4>
            <table class="bordered items-table">
               <thead>
                  <tr>
                     <th>#</th>
                     <th>Product</th>
                     <th>HSN</th>
                     @if($showManufacturingDate)
                     <th>MFG Date</th>
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
                  @foreach($saleItems as $row)
                  <tr class="item-row">
                     <td>{{ $loop->iteration }}</td>
                     <td>{{ $row->product?->product ?? 'Removed item' }}</td>
                     <td>{{ $row->svd_hsn }}</td>
                     @if($showManufacturingDate)
                     <td>{{ $row->manufacturing_date ?? '-' }}</td>
                     @endif
                     <td>{{ number_format($row->svd_uprice,2) }}</td>
                     <td>{{ $row->svd_itemqty }} {{ $row->svd_unit }}</td>
                     <td>{{ number_format($row->svd_price,2) }}</td>
                     <td>{{ $row->svd_adisc }} ({{ $row->svd_pdisc }}%)</td>
                     <td>{{ number_format($row->svd_total,2) }}</td>
                     @if($showTax)
                     <td>{{ number_format($row->svd_total - $row->svd_gst,2) }}</td>
                     <td>{{ number_format($row->svd_gst,2) }} ({{ $row->product?->gst ?? 0 }}%)</td>
                     @endif
                  </tr>
                  @endforeach
               </tbody>
               <tr class="total-row">
                  <td colspan="{{ $saleLeadingColspan }}"><b>Total</b></td>
                  <td><b>{{ number_format($total_before_discount,2) }}</b></td>
                  <td><b>{{ number_format($discount_amount,2) }}</b></td>
                  <td><b>{{ number_format($total_after_discount,2) }}</b></td>
                  @if($showTax)
                  <td><b>{{ number_format($taxable_value,2) }}</b></td>
                  <td><b>{{ number_format($gst_value,2) }}</b></td>
                  @endif
               </tr>
               @if($showTax)
               <tr class="total-row">
                  <td colspan="{{ $saleTaxSummaryColspan }}"></td>
                  @if($isVat)
                     <td colspan="2" class="text-right">
                        {{ $taxLabel }}: {{ number_format($total_gst, 2) }}
                     </td>
                  @elseif(($service->sv_is_igst ?? 0) == 1)
                     <td colspan="2" class="text-right">
                        IGST: {{ number_format($total_gst, 2) }}
                     </td>
                  @else
                     <td class="text-right">
                        CGST: {{ number_format($total_gst / 2, 2) }}
                     </td>
                     <td class="text-right">
                        SGST: {{ number_format($total_gst / 2, 2) }}
                     </td>
                  @endif
               </tr>
               @endif
               <tr class="total-row">
                  <td colspan="{{ $saleAmountWordsColspan }}">
                     <b>Amount in Words :</b>
                     ₹ {{ numberToWords($grand_total) }}<br>
                     <b>You Saved :</b>
                     ₹ {{ number_format($discount_amount,2) }}
                  </td>
                  <td colspan="{{ $saleNetTotalColspan }}">
                     Round Off :
                     ₹ {{ number_format($service->sv_round,2) }}
                     <br>
                     <b>NET TOTAL :
                     ₹ {{ number_format($service->sv_amount_payable,2) }}
                     </b>
                  </td>
               </tr>
            </table>
            @endif

            @if(!$hasSaleItems)
            <!-- TOTAL SUMMARY -->
            <table class="bordered" style="margin-top:8px;">
               @if($showTax)
               <tr>
                  <td colspan="2"></td>
                  @if($isVat)
                     <td class="text-right"><b>{{ $taxLabel }}</b></td>
                     <td class="text-right">{{ number_format($total_gst, 2) }}</td>
                  @elseif(($service->sv_is_igst ?? 0) == 1)
                     <td class="text-right"><b>IGST</b></td>
                     <td class="text-right">{{ number_format($total_gst, 2) }}</td>
                  @else
                     <td class="text-right"><b>CGST</b>: {{ number_format($total_gst / 2, 2) }}</td>
                     <td class="text-right"><b>SGST</b>: {{ number_format($total_gst / 2, 2) }}</td>
                  @endif
               </tr>
               @endif
               <tr>
                  <td colspan="2">
                     <b>Amount in Words :</b>
                     Rs. {{ numberToWords($grand_total) }}<br>
                     <b>You Saved :</b>
                     Rs. {{ number_format($total_discount, 2) }}
                  </td>
                  <td colspan="2">
                     Round Off :
                     Rs. {{ number_format($service->sv_round, 2) }}
                     <br>
                     <b>NET TOTAL :
                     Rs. {{ number_format($service->sv_amount_payable, 2) }}
                     </b>
                  </td>
               </tr>
               <tr>
                  <td><b>Payment Mode</b></td>
                  <td>{{ $service->banking?->bk_bank ?? '-' }}</td>
                  <td><b>Amount Paid</b></td>
                  <td>Rs. {{ number_format((float) $service->sv_amount_paid, 2) }}</td>
               </tr>
               <tr>
                  <td><b>Balance</b></td>
                  <td>Rs. {{ number_format((float) $service->sv_balance, 2) }}</td>
                  <td><b>Amount Payable</b></td>
                  <td>Rs. {{ number_format((float) $service->sv_amount_payable, 2) }}</td>
               </tr>
            </table>
            @endif

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

            <!-- FOOTER -->
            <table style="margin-top:5px;">
               <tr>
                  <td class="footer-box" width="33%">
                     <b>COMPANY BANK DETAILS</b><br><br>
                     <b>BANK :</b> {{ $company->bank_name }}<br>
                     <b>BRANCH :</b> {{ $company->bank_branch }}<br>
                     <b>ACCOUNT NO :</b> {{ $company->bank_acno }}<br>
                     <b>IFSC :</b> {{ $company->bank_ifsc }}
                  </td>
                  <td class="footer-box" width="33%" style="text-align:center;">
                     <div style="width:110px;height:110px;margin:auto;border:1px solid #00000057;display:flex;align-items:center;justify-content:center;">
                        <img src="{{ tenant_asset('company_qr_codes/'.$company->qr_code) }}" style="max-width:100%;max-height:100%;">
                     </div>
                     @if(!empty($company->upi_id))
                     <b>UPI :</b> {{ $company->upi_id }}
                     @endif
                     <div style="font-weight:bold;margin-top:6px;">
                        Google Pay / UPI
                     </div>
                  </td>
                  <td class="footer-box" width="33%" align="right">
                     FOR <b>{{ $company->company }}</b>
                     <br><br><br><br><br>
                     Authorised Signatory
                  </td>
               </tr>
            </table>
            <p align="center">
               This is a Computer Generated Invoice<br>
               Thank You - Visit Again
            </p>
         </div>
      </div>
      @include('partials.local-print-script', ['printSetting' => $printSetting ?? null, 'documentType' => 'service', 'documentId' => $service->sv_id])
   </body>
</html>