@extends('layout')

@section('content-header')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">{{$page_title}}</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{route('profile.dashboard')}}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{route('company.index')}}">Company</a></li>
                    <li class="breadcrumb-item active">{{$page_title}}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body p-0">
                <div class="nav flex-column nav-pills company-settings-nav" id="company-settings-tabs" role="tablist" aria-orientation="vertical">
                    <a class="nav-link active" id="regional-tab" data-toggle="pill" href="#regional-settings" role="tab" aria-controls="regional-settings" aria-selected="true">
                        <i class="fas fa-globe-asia mr-2"></i> Regional Settings
                    </a>
                    <a class="nav-link" id="inventory-tab" data-toggle="pill" href="#inventory-controls" role="tab" aria-controls="inventory-controls" aria-selected="false">
                        <i class="fas fa-boxes mr-2"></i> Inventory Controls
                    </a>
                    <a class="nav-link" id="sale-tab" data-toggle="pill" href="#sale-settings" role="tab" aria-controls="sale-settings" aria-selected="false">
                        <i class="fas fa-cash-register mr-2"></i> Sale Settings
                    </a>
                    <a class="nav-link" id="estimation-tab" data-toggle="pill" href="#estimation-settings" role="tab" aria-controls="estimation-settings" aria-selected="false">
                        <i class="fa-solid fa-receipt mr-2"></i> Estimation Settings
                    </a>
                    <a class="nav-link" id="print-tab" data-toggle="pill" href="#print-settings" role="tab" aria-controls="print-settings" aria-selected="false">
                        <i class="fas fa-print mr-2"></i> Print Settings
                    </a>
                    <a class="nav-link" id="barcode-tab" data-toggle="pill" href="#barcode-settings" role="tab" aria-controls="barcode-settings" aria-selected="false">
                        <i class="fas fa-barcode mr-2"></i> Barcode &amp; QR Settings
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-9">
        <form method="POST" action="{{ route('company.settings.update') }}">
            @csrf
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" id="settings-panel-title">Regional Settings</h3>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <script>
                            document.addEventListener("DOMContentLoaded", function () {
                                toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validation Error');
                            });
                        </script>
                    @endif

                    <div class="tab-content" id="company-settings-content">
                        <div class="tab-pane fade show active" id="regional-settings" role="tabpanel" aria-labelledby="regional-tab">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Currency</label>
                                        <select name="currency_code" class="form-control">
                                            @foreach($currencies as $code => $currency)
                                                <option value="{{ $code }}" {{ old('currency_code', $currency_code ?? 'INR') === $code ? 'selected' : '' }}>
                                                    {{ $code }} - {{ $currency['symbol'] }} {{ $currency['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">The selected code and symbol will be used as the company currency.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Tax Type</label>
                                        <select name="tax_type" id="tax_type" class="form-control">
                                            @foreach($taxTypes as $value => $label)
                                                <option value="{{ $value }}" {{ old('tax_type', $tax_type ?? 'gst') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Choose whether tax labels should use GST or VAT.</small>
                                    </div>
                                </div>
                                <div class="col-md-6" id="gst_scheme_group">
                                    <div class="form-group">
                                        <label>GST Scheme</label>
                                        <select name="gst_scheme" class="form-control">
                                            @foreach($gstSchemes as $value => $label)
                                                <option value="{{ $value }}" {{ old('gst_scheme', $gst_scheme ?? 'regular') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Composition scheme removes GST breakup from Sale, Purchase and Service bills.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="inventory-controls" role="tabpanel" aria-labelledby="inventory-tab">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Inventory Mode</label>
                                        <select name="inventory_mode" class="form-control">
                                            <option value="standard" {{ old('inventory_mode', $inventory_mode ?? 'standard') === 'standard' ? 'selected' : '' }}>Standard inventory</option>
                                            <option value="mrp" {{ old('inventory_mode', $inventory_mode ?? 'standard') === 'mrp' ? 'selected' : '' }}>MRP-wise inventory</option>
                                            <option value="batch" {{ old('inventory_mode', $inventory_mode ?? 'standard') === 'batch' ? 'selected' : '' }}>Batch-wise inventory</option>
                                        </select>
                                        <small class="text-muted">MRP-wise mode creates a separate stock lot for every purchase line without batch or expiry tracking.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Expiry Alert Days</label>
                                        <input type="number" name="expiry_alert_days" min="1" max="3650" class="form-control"
                                            value="{{ old('expiry_alert_days', $expiry_alert_days ?? 30) }}">
                                        <small class="text-muted">Near Expiry report shows available batches expiring within this many days.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="sale-settings" role="tabpanel" aria-labelledby="sale-tab">
                            <h5>Additional Sale Fields</h5>
                            <hr>

                            <div class="form-check">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="sale_manufacturing_date"
                                       name="sale_fields[]"
                                       value="manufacturing_date"
                                       @checked((bool) ($saleSettings['manufacturing_date'] ?? false))>
                                <label class="form-check-label" for="sale_manufacturing_date">
                                    Manufacturing Date (Item-wise)
                                </label>
                            </div>

                            <h5 class="mt-4">Inventory Controls</h5>
                            <hr>

                            <div class="form-check">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="sale_allow_out_of_stock"
                                       name="sale_fields[]"
                                       value="allow_out_of_stock_sale"
                                       @checked((bool) ($saleSettings['allow_out_of_stock_sale'] ?? false))>
                                <label class="form-check-label" for="sale_allow_out_of_stock">
                                    Allow out-of-stock products to proceed in Sale
                                </label>
                                <small class="form-text text-muted">
                                    If disabled, products cannot be added or saved when requested quantity exceeds stock. MRP slots and batch-managed stock always require sufficient tracked quantity.
                                </small>
                            </div>

                            <h5 class="mt-4">Pricing Defaults</h5>
                            <hr>

                            <div class="form-check">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="sale_mrp_pricing_mode"
                                       name="sale_fields[]"
                                       value="mrp_pricing_mode"
                                       @checked((bool) ($saleSettings['mrp_pricing_mode'] ?? false))>
                                <label class="form-check-label" for="sale_mrp_pricing_mode">
                                    Use MRP as Sale unit price
                                </label>
                                <small class="form-text text-muted">
                                    Product selection fills MRP as unit price and applies product margin as the default discount.
                                </small>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="estimation-settings" role="tabpanel" aria-labelledby="estimation-tab">
                            <h5>Accounting Controls</h5>
                            <hr>

                            <div class="form-check">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="estimation_affect_customer_accounts"
                                       name="estimation_fields[]"
                                       value="affect_customer_accounts"
                                       @checked((bool) ($estimationSettings['affect_customer_accounts'] ?? false))>
                                <label class="form-check-label" for="estimation_affect_customer_accounts">
                                    Affect customer accounts and stock like a no-tax sale
                                </label>
                                <small class="form-text text-muted">
                                    When enabled, new/edited estimations create customer ledger, payment, stock and batch movements without GST.
                                </small>
                            </div>

                            <h5 class="mt-4">Pricing Defaults</h5>
                            <hr>

                            <div class="form-check">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="estimation_mrp_pricing_mode"
                                       name="estimation_fields[]"
                                       value="estimation_mrp_pricing_mode"
                                       @checked((bool) ($estimationSettings['estimation_mrp_pricing_mode'] ?? false))>
                                <label class="form-check-label" for="estimation_mrp_pricing_mode">
                                    Use MRP as Estimation unit price
                                </label>
                                <small class="form-text text-muted">
                                    Product selection fills MRP as unit price and applies product margin as the default discount.
                                </small>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="print-settings" role="tabpanel" aria-labelledby="print-tab">
                            <h5>Document Print Profiles</h5>
                            <hr>

                            <div class="alert alert-info py-2">
                                Use Browser Print for the normal dialog, or Hubix Local Print Agent for a mapped Windows printer. Each browser must be linked to its own agent below; Hubix will never send its jobs to another computer automatically.
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 21%;">Document</th>
                                            <th style="width: 20%;">Method</th>
                                            <th style="width: 10%;">Paper</th>
                                            <th style="width: 14%;">Orientation</th>
                                            <th style="width: 10%;">Scale %</th>
                                            <th style="width: 12%;">Margin mm</th>
                                            <th style="width: 10%;">Auto Print</th>
                                            <th style="width: 15%;">Header</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($printDocuments as $documentType => $documentLabel)
                                            @php $setting = $printSettings[$documentType]; @endphp
                                            <tr>
                                                <td class="align-middle font-weight-bold">{{ $documentLabel }}</td>
                                                <td>
                                                    <select name="print_settings[{{ $documentType }}][print_method]" class="form-control form-control-sm">
                                                        @foreach($printMethods as $value => $label)
                                                            <option value="{{ $value }}" {{ old("print_settings.$documentType.print_method", $setting->print_method) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    @if($documentType === 'barcode')
                                                        <input type="hidden" name="print_settings[barcode][paper_size]" value="A4">
                                                        <span class="form-control form-control-sm bg-light">Barcode &amp; QR Settings</span>
                                                        <small class="text-muted">Choose the label roll and dimensions there.</small>
                                                    @else
                                                        <select name="print_settings[{{ $documentType }}][paper_size]" class="form-control form-control-sm">
                                                            @foreach($paperSizes as $value => $label)
                                                                <option value="{{ $value }}" {{ old("print_settings.$documentType.paper_size", $setting->paper_size) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($documentType === 'barcode')
                                                        <input type="hidden" name="print_settings[barcode][orientation]" value="portrait">
                                                        <span class="form-control form-control-sm bg-light">Portrait</span>
                                                    @else
                                                        <select name="print_settings[{{ $documentType }}][orientation]" class="form-control form-control-sm">
                                                            @foreach($orientations as $value => $label)
                                                                <option value="{{ $value }}" {{ old("print_settings.$documentType.orientation", $setting->orientation) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($documentType === 'barcode')
                                                    <input type="hidden" name="print_settings[barcode][scale]" value="100">
                                                    <span class="form-control form-control-sm bg-light">100%</span>
                                                    @else
                                                    <input type="number"
                                                           name="print_settings[{{ $documentType }}][scale]"
                                                           min="50"
                                                           max="150"
                                                           class="form-control form-control-sm"
                                                           value="{{ old("print_settings.$documentType.scale", $setting->scale) }}">
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($documentType === 'barcode')
                                                        <input type="hidden" name="print_settings[barcode][margin_mm]" value="0">
                                                        <span class="form-control form-control-sm bg-light">0 (label controlled)</span>
                                                    @else
                                                        <input type="number"
                                                               name="print_settings[{{ $documentType }}][margin_mm]"
                                                               min="0"
                                                               max="50"
                                                               class="form-control form-control-sm"
                                                               value="{{ old("print_settings.$documentType.margin_mm", $setting->margin_mm) }}">
                                                    @endif
                                                </td>
                                                <td class="align-middle text-center">
                                                    <div class="custom-control custom-switch">
                                                        <input type="hidden" name="print_settings[{{ $documentType }}][auto_print]" value="0">
                                                        <input type="checkbox"
                                                               class="custom-control-input"
                                                               id="print_auto_{{ $documentType }}"
                                                               name="print_settings[{{ $documentType }}][auto_print]"
                                                               value="1"
                                                               @checked((bool) old("print_settings.$documentType.auto_print", $setting->auto_print))>
                                                        <label class="custom-control-label" for="print_auto_{{ $documentType }}"></label>
                                                    </div>
                                                </td>
                                                <td>
                                                    @if($documentType === 'service')
                                                        <select name="print_settings[service][header_mode]" class="form-control form-control-sm">
                                                            @foreach($printHeaderModes as $value => $label)
                                                                <option value="{{ $value }}" @selected(old('print_settings.service.header_mode', $setting->header_mode ?? 'full') === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <span class="text-muted">&mdash;</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <small class="form-text text-muted">Barcode label layout and dimensions are configured in Barcode &amp; QR Settings. Disable Auto Print to preview a document before sending it to Hubix. Printer mappings are maintained per computer below.</small>

                            <h5 class="mt-4">Local Print Agents</h5>
                            <hr>

                            @php $boundPrintAgent = $printAgents->firstWhere('uuid', $boundPrintAgentUuid); @endphp
                            @if($boundPrintAgent)
                                <div class="alert alert-success py-2">
                                    This browser prints through <strong>{{ $boundPrintAgent->name }}</strong>.
                                    <button type="submit" form="unbind-print-agent-browser-form" class="btn btn-sm btn-outline-danger ml-2">Unlink this browser</button>
                                </div>
                            @elseif($boundPrintAgentUuid)
                                <div class="alert alert-warning py-2">
                                    This browser's previous print agent no longer exists. Link an agent below or use browser printing.
                                </div>
                            @else
                                <div class="alert alert-warning py-2">
                                    This browser is not linked to a local print agent. Local-agent documents will offer browser printing instead.
                                </div>
                            @endif

                            <div class="alert alert-info py-2">
                                To link a browser securely, open Hubix Print Agent on that same computer, generate a Browser Link Code, and enter it beside that computer below. The code expires after 10 minutes and can be used once.
                            </div>

                            @if(file_exists(public_path('downloads/HubixPrintAgent-win-x64.zip')))
                                <p>
                                    <a class="btn btn-sm btn-success" href="{{ asset('downloads/HubixPrintAgent-win-x64.zip') }}" download>
                                        <i class="fas fa-download"></i> Download Windows Print Agent
                                    </a>
                                    <small class="text-muted ml-2">Version 1.0.5. Extract the ZIP and keep all files together on the billing computer.</small>
                                </p>
                            @endif

                            @if(session('print_agent_pairing_code'))
                                <div class="alert alert-warning">
                                    <strong>Pair {{ session('print_agent_pairing_name') }} now:</strong>
                                    enter code <code class="h5 ml-1">{{ session('print_agent_pairing_code') }}</code> in the Windows agent.
                                    This code expires in 10 minutes and is shown only once.
                                </div>
                            @endif

                            <div class="form-row align-items-end mb-3">
                                <div class="col-md-7">
                                    <label for="new_print_agent_name">Computer / counter name</label>
                                    <input id="new_print_agent_name" name="name" form="new-print-agent-form" class="form-control" maxlength="100" placeholder="Example: Main Billing Counter">
                                </div>
                                <div class="col-md-5 mt-2 mt-md-0">
                                    <button type="submit" form="new-print-agent-form" class="btn btn-outline-primary btn-block">
                                        <i class="fas fa-plus"></i> Create Pairing Code
                                    </button>
                                </div>
                            </div>

                            @forelse($printAgents as $agent)
                                @php
                                    $agentMappings = $agent->mappings->pluck('printer_name', 'document_type');
                                    $agentPrinters = $agent->printers ?? [];
                                @endphp
                                <div class="card border mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                                        <div>
                                            <strong>{{ $agent->name }}</strong>
                                            @if($agent->uuid === $boundPrintAgentUuid)<span class="badge badge-primary ml-1">This browser</span>@endif
                                            <span class="badge {{ $agent->isOnline() ? 'badge-success' : 'badge-secondary' }} ml-1">
                                                {{ $agent->isOnline() ? 'Online' : 'Offline' }}
                                            </span>
                                        </div>
                                        <small class="text-muted">{{ $agent->last_seen_at ? 'Last seen '.$agent->last_seen_at->diffForHumans() : 'Not paired' }}</small>
                                    </div>
                                    <div class="card-body py-3">
                                        <div class="form-row">
                                            <div class="col-md-6 form-group">
                                                <label>Agent name</label>
                                                <input name="name" form="print-agent-{{ $agent->id }}-form" class="form-control form-control-sm" value="{{ $agent->name }}" required>
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label>Computer</label>
                                                <input class="form-control form-control-sm" value="{{ $agent->machine_name ?: 'Not paired' }}{{ $agent->version ? ' · v'.$agent->version : '' }}" readonly>
                                            </div>
                                        </div>
                                        <input type="hidden" name="enabled" form="print-agent-{{ $agent->id }}-form" value="0">
                                        <div class="custom-control custom-switch mb-3">
                                            <input type="checkbox" class="custom-control-input" id="agent_enabled_{{ $agent->id }}" name="enabled" form="print-agent-{{ $agent->id }}-form" value="1" @checked($agent->enabled)>
                                            <label class="custom-control-label" for="agent_enabled_{{ $agent->id }}">Enabled</label>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-2">
                                                <thead><tr><th>Document</th><th>Windows printer on this computer</th></tr></thead>
                                                <tbody>
                                                    @foreach($printDocuments as $documentType => $documentLabel)
                                                        <tr>
                                                            <td class="align-middle">{{ $documentLabel }}</td>
                                                            <td>
                                                                <select name="mappings[{{ $documentType }}]" form="print-agent-{{ $agent->id }}-form" class="form-control form-control-sm">
                                                                    <option value="">Not mapped</option>
                                                                    @foreach($agentPrinters as $printer)
                                                                        <option value="{{ $printer }}" @selected($agentMappings->get($documentType) === $printer)>{{ $printer }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        @if($agent->last_error)
                                            <div class="alert alert-danger py-2 mb-2"><strong>Agent error:</strong> {{ $agent->last_error }}</div>
                                        @endif

                                        <button type="submit" form="print-agent-{{ $agent->id }}-form" class="btn btn-sm btn-primary">Save Agent</button>
                                        <button type="submit" form="print-agent-{{ $agent->id }}-pair-form" class="btn btn-sm btn-outline-warning">Pair Again</button>
                                        <button type="submit" form="print-agent-{{ $agent->id }}-test-form" class="btn btn-sm btn-outline-success">Print Test</button>
                                        @if($agent->uuid !== $boundPrintAgentUuid)
                                            <div class="input-group input-group-sm mt-2" style="max-width: 410px;">
                                                <input type="text"
                                                       name="browser_link_code"
                                                       form="print-agent-{{ $agent->id }}-bind-form"
                                                       class="form-control text-uppercase"
                                                       minlength="8"
                                                       maxlength="20"
                                                       autocomplete="one-time-code"
                                                       placeholder="Code shown on this computer's agent"
                                                       required>
                                                <div class="input-group-append">
                                                    <button type="submit" form="print-agent-{{ $agent->id }}-bind-form" class="btn btn-outline-secondary">Use on this browser</button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="alert alert-light border">No local print agent is registered yet. Create a pairing code, then install and pair the Windows agent on the billing computer.</div>
                            @endforelse
                        </div>

                        <div class="tab-pane fade" id="barcode-settings" role="tabpanel" aria-labelledby="barcode-tab">
                            <h5>Barcode Label Printing</h5>
                            <hr>

                            <div class="alert alert-info py-2">
                                Select the label-roll format used for barcode and QR printing. Both options use the exact sticker dimensions and print at 100% scale.
                            </div>

                            <div class="form-group" style="max-width: 460px;">
                                <label for="barcode_layout">Label roll format</label>
                                <select id="barcode_layout" name="barcode_layout" class="form-control">
                                    @foreach($barcodeLayouts as $value => $label)
                                        <option value="{{ $value }}" @selected(old('barcode_layout', $barcodeLayout) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="border rounded p-3 mb-3 barcode-layout-panel" data-barcode-layout="thermal">
                                <h6 class="mb-1">Current custom label-printer layout <span class="badge badge-secondary">Optional</span></h6>
                                <p class="text-muted small mb-3">Keep using the existing layout when your printer feeds one or more labels across in each row.</p>
                                <div class="form-row">
                                    <div class="col-md-3 form-group">
                                        <label for="label_width_mm">Sticker width (mm)</label>
                                        <input id="label_width_mm" type="number" name="label_width_mm" min="20" max="150" class="form-control" value="{{ old('label_width_mm', $thermalLabelSettings['label_width_mm']) }}" required>
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label for="label_height_mm">Sticker height (mm)</label>
                                        <input id="label_height_mm" type="number" name="label_height_mm" min="15" max="150" class="form-control" value="{{ old('label_height_mm', $thermalLabelSettings['label_height_mm']) }}" required>
                                    </div>
                                    <div class="col-md-2 form-group">
                                        <label for="label_margin_mm">Inner margin (mm)</label>
                                        <input id="label_margin_mm" type="number" name="label_margin_mm" min="0" max="10" class="form-control" value="{{ old('label_margin_mm', $thermalLabelSettings['label_margin_mm']) }}" required>
                                    </div>
                                    <div class="col-md-2 form-group">
                                        <label for="label_columns">Labels across</label>
                                        <input id="label_columns" type="number" name="label_columns" min="1" max="4" class="form-control" value="{{ old('label_columns', $thermalLabelSettings['label_columns'] ?? 1) }}" required>
                                    </div>
                                    <div class="col-md-2 form-group">
                                        <label for="label_column_gap_mm">Column gap (mm)</label>
                                        <input id="label_column_gap_mm" type="number" name="label_column_gap_mm" min="0" max="10" step="0.1" class="form-control" value="{{ old('label_column_gap_mm', $thermalLabelSettings['label_column_gap_mm'] ?? 0) }}" required>
                                    </div>
                                </div>
                                <small class="text-muted">For multiple labels across, set the printer page width to one full row. Do not include the gap between rows in its height.</small>
                            </div>

                            <div class="border rounded p-3 mb-4 barcode-layout-panel" data-barcode-layout="common">
                                <h6 class="mb-1">Common sticker-roll label <span class="badge badge-primary">Centred</span></h6>
                                <p class="text-muted small mb-3">Prints one label per roll position, with the selected fields and barcode centred on the sticker.</p>
                                <div class="form-row">
                                    <div class="col-md-3 form-group mb-0">
                                        <label for="common_label_width_mm">Sticker width (mm)</label>
                                        <input id="common_label_width_mm" type="number" name="common_label_width_mm" min="20" max="150" class="form-control" value="{{ old('common_label_width_mm', $commonLabelSettings['common_label_width_mm']) }}" required>
                                    </div>
                                    <div class="col-md-3 form-group mb-0">
                                        <label for="common_label_height_mm">Sticker height (mm)</label>
                                        <input id="common_label_height_mm" type="number" name="common_label_height_mm" min="15" max="150" class="form-control" value="{{ old('common_label_height_mm', $commonLabelSettings['common_label_height_mm']) }}" required>
                                    </div>
                                </div>
                            </div>

                            <h5>Barcode / QR Label Content</h5>
                            <hr>

                            <div class="alert alert-info py-2">
                                These fields appear in the product slot table and on printed barcode and QR labels. The encoded value remains the stable slot number for reliable scanning.
                            </div>

                            <div class="row">
                                @foreach($barcodeFields as $field => $label)
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox"
                                                   class="custom-control-input"
                                                   id="barcode_field_{{ $field }}"
                                                   name="barcode_fields[]"
                                                   value="{{ $field }}"
                                                   @checked(in_array($field, old('barcode_fields', $selectedBarcodeFields), true))>
                                            <label class="custom-control-label" for="barcode_field_{{ $field }}">{{ $label }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('barcode_fields')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror

                            <h5 class="mt-4">Purchase Price Masking</h5>
                            <hr>
                            <input type="hidden" name="mask_purchase_price" value="0">
                            <div class="custom-control custom-switch">
                                <input type="checkbox"
                                       class="custom-control-input"
                                       id="mask_purchase_price"
                                       name="mask_purchase_price"
                                       value="1"
                                       @checked((bool) old('mask_purchase_price', $maskPurchasePrice))>
                                <label class="custom-control-label" for="mask_purchase_price">Mask purchase prices in Product Index and barcode/QR print labels</label>
                            </div>
                            <div class="alert alert-light border py-2 mt-3 mb-0">
                                <strong>Mask key:</strong>
                                <code>1=K, 2=V, 3=M, 4=O, 5=U, 6=L, 7=I, 8=N, 9=E, 0=X</code>
                                <span class="d-block text-muted mt-1">Example: 1234.50 is displayed as KVMO.UX. Stored prices and encoded barcode/QR values are unchanged.</span>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-flat"><i class="fas fa-save"></i> Save Changes</button>
                    <a class="btn btn-secondary btn-flat" href="{{ route('company.index') }}"><i class="fas fa-undo-alt"></i> Cancel</a>
                </div>
            </div>
        </form>

        <form id="new-print-agent-form" method="POST" action="{{ route('print-agents.store') }}">@csrf</form>
        <form id="unbind-print-agent-browser-form" method="POST" action="{{ route('print-agents.unbind-browser') }}">@csrf @method('DELETE')</form>
        @foreach($printAgents as $agent)
            <form id="print-agent-{{ $agent->id }}-form" method="POST" action="{{ route('print-agents.update', $agent) }}">@csrf @method('PUT')</form>
            <form id="print-agent-{{ $agent->id }}-pair-form" method="POST" action="{{ route('print-agents.pairing-code', $agent) }}">@csrf</form>
            <form id="print-agent-{{ $agent->id }}-test-form" method="POST" action="{{ route('print-agents.test', $agent) }}">@csrf</form>
            <form id="print-agent-{{ $agent->id }}-bind-form" method="POST" action="{{ route('print-agents.bind-browser', $agent) }}">@csrf</form>
        @endforeach
    </div>
</div>
@endsection

@section('scripts')
<style>
    .company-settings-nav .nav-link {
        border-bottom: 1px solid #dee2e6;
        border-radius: 0;
        color: #495057;
        padding: 0.65rem 1rem;
    }

    .company-settings-nav .nav-link:last-child {
        border-bottom: 0;
    }

    .company-settings-nav .nav-link.active {
        background-color: #001f3f;
        color: #fff;
    }
</style>
<script>
    $(document).ready(function(){
        var Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        @if(session('success'))
            Toast.fire({
                icon: 'success',
                title: @json(session('success'))
            });
        @endif
        @if(session('info'))
            Toast.fire({
                icon: 'info',
                title: @json(session('info'))
            });
        @endif
        @if(session('error'))
            Toast.fire({
                icon: 'error',
                title: @json(session('error'))
            });
        @endif

        function toggleGstScheme() {
            $('#gst_scheme_group').toggle($('#tax_type').val() === 'gst');
        }

        function toggleBarcodeLayout() {
            var selectedLayout = $('#barcode_layout').val();
            $('.barcode-layout-panel').each(function () {
                $(this).toggleClass('d-none', $(this).data('barcode-layout') !== selectedLayout);
            });
        }

        $('#tax_type').on('change', toggleGstScheme);
        $('#barcode_layout').on('change', toggleBarcodeLayout);
        toggleGstScheme();
        toggleBarcodeLayout();

        $('#company-settings-tabs a[data-toggle="pill"]').on('shown.bs.tab', function (event) {
            $('#settings-panel-title').text($(event.target).text().trim());
        });
    });
</script>
@endsection
