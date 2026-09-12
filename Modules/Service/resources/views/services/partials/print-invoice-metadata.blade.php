@if($compactServiceHeader)
    <td>
        <b>Invoice Date :</b>
        {{ \Carbon\Carbon::parse($service->sv_date)->format('d/m/Y') }}
    </td>
    <td>
        <b>Invoice No :</b>{{ $service->sv_vno }}
    </td>
@else
    <td>
        <b>Invoice Type :</b> {{ $invoiceType }}<br><br>
        <b>Invoice Date :</b>
        {{ \Carbon\Carbon::parse($service->sv_date)->format('d/m/Y') }}<br><br>
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
@endif
