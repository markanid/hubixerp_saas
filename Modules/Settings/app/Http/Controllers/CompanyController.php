<?php

namespace Modules\Settings\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Estimation\app\Models\EstimationSetting;
use Modules\Product\app\Services\BatchInventoryService;
use Modules\Product\app\Services\MrpInventoryService;
use Modules\Sale\app\Models\SaleSetting;
use Modules\Settings\app\Models\BarcodeSetting;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\PrintAgent;
use Modules\Settings\app\Models\PrintSetting;

class CompanyController extends Controller
{
    private const PRINT_HEADER_MODES = [
        'full' => 'Display all',
        'compact' => 'Date, number and remark only',
    ];

    private const CURRENCIES = [
        'INR' => ['symbol' => '₹', 'label' => 'Indian Rupee'],
        'USD' => ['symbol' => '$', 'label' => 'US Dollar'],
        'EUR' => ['symbol' => '€', 'label' => 'Euro'],
        'GBP' => ['symbol' => '£', 'label' => 'British Pound'],
        'AED' => ['symbol' => 'د.إ', 'label' => 'UAE Dirham'],
        'SAR' => ['symbol' => '﷼', 'label' => 'Saudi Riyal'],
    ];

    private const TAX_TYPES = [
        'gst' => 'GST',
        'vat' => 'VAT',
    ];

    private const GST_SCHEMES = [
        'regular' => 'Regular Scheme',
        'composition' => 'Composition Scheme',
    ];

    private const SALE_SETTING_FIELDS = [
        'manufacturing_date',
        'allow_out_of_stock_sale',
        'mrp_pricing_mode',
    ];

    private const ESTIMATION_SETTING_FIELDS = [
        'affect_customer_accounts',
        'estimation_mrp_pricing_mode',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $company = Company::orderBy('id', 'DESC')->get();
        if ($company != null && ! $company->isEmpty()) {
            $data = (new Company)->getCompanyDetails();
            $data['page_title'] = 'Company View';

            return view('settings::company.view', $data);
        } else {
            return redirect()->route('company.edit');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit()
    {
        $data = (new Company)->getCompanyDetails(); // Fetch data using model method
        $data['page_title'] = 'Edit Company';

        return view('settings::company.create', $data);
    }

    public function settings()
    {
        $company = Company::first();

        if (! $company) {
            return redirect()->route('company.edit')->with('info', 'Please add company details before changing company settings.');
        }

        $data = (new Company)->getCompanyDetails();
        $data['page_title'] = 'Company Settings';
        $data['currencies'] = self::CURRENCIES;
        $data['taxTypes'] = self::TAX_TYPES;
        $data['gstSchemes'] = self::GST_SCHEMES;
        $data['saleSettings'] = SaleSetting::values();
        $data['estimationSettings'] = EstimationSetting::values();
        $data['printSettings'] = PrintSetting::values();
        $data['printDocuments'] = PrintSetting::DOCUMENTS;
        $data['printMethods'] = PrintSetting::PRINT_METHODS;
        $data['paperSizes'] = PrintSetting::PAPER_SIZES;
        $data['orientations'] = PrintSetting::ORIENTATIONS;
        $data['printHeaderModes'] = self::PRINT_HEADER_MODES;
        $data['barcodeFields'] = BarcodeSetting::FIELDS;
        $data['selectedBarcodeFields'] = BarcodeSetting::selectedFields();
        $data['thermalLabelSettings'] = BarcodeSetting::thermalSettings();
        $data['maskPurchasePrice'] = BarcodeSetting::purchasePriceMaskEnabled();
        $data['printAgents'] = Schema::hasTable('print_agents')
            ? PrintAgent::with('mappings')->orderByDesc('is_default')->orderBy('name')->get()
            : collect();
        $browserBinding = (string) request()->cookie(PrintAgent::BROWSER_COOKIE, '');
        $data['boundPrintAgentUuid'] = $data['printAgents']
            ->first(fn (PrintAgent $agent) => $agent->matchesBrowserBinding($browserBinding))
            ?->uuid ?? '';

        return view('settings::company.settings', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {

        //  dd($request->all());
        $validated = $request->validate([
            'company' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'website' => 'nullable|string|max:255',
            'licence_no' => 'nullable|string|max:255',
            'gst_no' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_ifsc' => 'nullable|string|max:255',
            'bank_acno' => 'nullable|string|max:255',
            'bank_branch' => 'nullable|string|max:255',
            'tags' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpg,png,jpeg,gif,webp|max:300000',
            'qr_code' => 'nullable|image|mimes:jpg,png,jpeg,gif,webp|max:300000',
        ]);

        $company = Company::find($request->id);

        unset($validated['logo'], $validated['qr_code']);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');

            if (! $file->isValid()) {
                return back()->with('error', 'Invalid logo upload.');
            }

            $filename = time().'_logo_'.uniqid().'.'.$file->getClientOriginalExtension();

            $storedPath = $file->storeAs('company_logos', $filename, 'public');

            if (! $storedPath || ! Storage::disk('public')->exists('company_logos/'.$filename)) {
                return back()->with('error', 'Logo upload failed.');
            }

            if ($company && ! empty($company->company_logo)) {
                Storage::disk('public')->delete('company_logos/'.$company->company_logo);
            }

            $validated['company_logo'] = $filename;
        }

        if ($request->hasFile('qr_code')) {
            $file = $request->file('qr_code');

            if (! $file->isValid()) {
                return back()->with('error', 'Invalid QR code upload.');
            }

            $filename = time().'_qr_'.uniqid().'.'.$file->getClientOriginalExtension();

            $storedPath = $file->storeAs('company_qr_codes', $filename, 'public');

            if (! $storedPath || ! Storage::disk('public')->exists('company_qr_codes/'.$filename)) {
                return back()->with('error', 'QR code upload failed.');
            }

            if ($company && ! empty($company->qr_code)) {
                Storage::disk('public')->delete('company_qr_codes/'.$company->qr_code);
            }

            $validated['qr_code'] = $filename;
        }

        $company = Company::updateOrCreate(
            ['id' => $request->id], // Find by ID
            $validated
        );

        // $update = Company::createOrUpdateCompany($validated);

        if ($company) {
            return redirect()->route('company.index')->with('success', 'Company details updated successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to update company details.');
        }
    }

    public function updateSettings(
        Request $request,
        MrpInventoryService $mrpInventory,
        BatchInventoryService $batchInventory
    ) {
        $validated = $request->validate([
            'inventory_mode' => 'required|in:standard,mrp,batch',
            'expiry_alert_days' => 'required|integer|min:1|max:3650',
            'currency_code' => ['required', Rule::in(array_keys(self::CURRENCIES))],
            'tax_type' => ['required', Rule::in(array_keys(self::TAX_TYPES))],
            'gst_scheme' => ['nullable', Rule::in(array_keys(self::GST_SCHEMES))],
            'sale_fields' => ['nullable', 'array'],
            'sale_fields.*' => ['string', Rule::in(self::SALE_SETTING_FIELDS)],
            'estimation_fields' => ['nullable', 'array'],
            'estimation_fields.*' => ['string', Rule::in(self::ESTIMATION_SETTING_FIELDS)],
            'barcode_fields' => ['required', 'array', 'min:1'],
            'barcode_fields.*' => ['string', Rule::in(array_keys(BarcodeSetting::FIELDS))],
            'label_width_mm' => ['required', 'integer', 'min:20', 'max:150'],
            'label_height_mm' => ['required', 'integer', 'min:15', 'max:150'],
            'label_margin_mm' => ['required', 'integer', 'min:0', 'max:10'],
            'label_columns' => ['sometimes', 'required', 'integer', 'min:1', 'max:4'],
            'label_column_gap_mm' => ['sometimes', 'required', 'numeric', 'min:0', 'max:10', 'decimal:0,1'],
            'mask_purchase_price' => ['nullable', 'boolean'],
            'print_settings' => ['nullable', 'array'],
            'print_settings.*.print_method' => ['required', Rule::in(array_keys(PrintSetting::PRINT_METHODS))],
            'print_settings.*.paper_size' => ['required', Rule::in(array_keys(PrintSetting::PAPER_SIZES))],
            'print_settings.*.orientation' => ['required', Rule::in(array_keys(PrintSetting::ORIENTATIONS))],
            'print_settings.*.scale' => ['required', 'integer', 'min:50', 'max:150'],
            'print_settings.*.margin_mm' => ['required', 'integer', 'min:0', 'max:50'],
            'print_settings.*.auto_print' => ['nullable', 'boolean'],
            'print_settings.*.header_mode' => ['nullable', Rule::in(array_keys(self::PRINT_HEADER_MODES))],
        ]);

        $company = Company::first();

        if (! $company) {
            return redirect()->route('company.edit')->with('info', 'Please add company details before changing company settings.');
        }

        $previousInventoryMode = (string) ($company->inventory_mode ?? 'standard');
        $validated['currency_symbol'] = self::CURRENCIES[$validated['currency_code']]['symbol'];
        $validated['gst_scheme'] = $validated['tax_type'] === 'gst'
            ? ($validated['gst_scheme'] ?? 'regular')
            : 'regular';

        $selectedSaleFields = $validated['sale_fields'] ?? [];
        $selectedEstimationFields = $validated['estimation_fields'] ?? [];
        $selectedBarcodeFields = array_values(array_unique($validated['barcode_fields']));
        $thermalLabelSettings = [
            'label_width_mm' => $validated['label_width_mm'],
            'label_height_mm' => $validated['label_height_mm'],
            'label_margin_mm' => $validated['label_margin_mm'],
            'mask_purchase_price' => ! empty($validated['mask_purchase_price']),
        ];
        foreach (['label_columns', 'label_column_gap_mm'] as $key) {
            if (array_key_exists($key, $validated)) {
                $thermalLabelSettings[$key] = $validated[$key];
            }
        }
        $submittedPrintSettings = $validated['print_settings'] ?? [];
        unset(
            $validated['sale_fields'],
            $validated['estimation_fields'],
            $validated['barcode_fields'],
            $validated['label_width_mm'],
            $validated['label_height_mm'],
            $validated['label_margin_mm'],
            $validated['label_columns'],
            $validated['label_column_gap_mm'],
            $validated['mask_purchase_price'],
            $validated['print_settings']
        );

        DB::transaction(function () use (
            $company,
            $validated,
            $previousInventoryMode,
            $mrpInventory,
            $batchInventory
        ) {
            $company->update($validated);

            if ($validated['inventory_mode'] === 'mrp' && $previousInventoryMode !== 'mrp') {
                $mrpInventory->bootstrapExistingStock($previousInventoryMode);
            }
            if ($validated['inventory_mode'] === 'batch' && $previousInventoryMode !== 'batch') {
                $batchInventory->bootstrapExistingStock();
            }
        });

        foreach (self::SALE_SETTING_FIELDS as $field) {
            SaleSetting::updateOrCreate(
                ['key' => $field],
                ['value' => in_array($field, $selectedSaleFields, true) ? 1 : 0]
            );
        }

        foreach (self::ESTIMATION_SETTING_FIELDS as $field) {
            EstimationSetting::updateOrCreate(
                ['key' => $field],
                ['value' => in_array($field, $selectedEstimationFields, true) ? 1 : 0]
            );
        }

        BarcodeSetting::updateOrCreate(
            ['context' => BarcodeSetting::CONTEXT_INVENTORY],
            array_merge(['selected_fields' => $selectedBarcodeFields], $thermalLabelSettings)
        );

        foreach (PrintSetting::DOCUMENTS as $documentType => $label) {
            $settings = $submittedPrintSettings[$documentType] ?? [];
            $defaults = PrintSetting::defaultsFor($documentType);

            PrintSetting::updateOrCreate(
                ['document_type' => $documentType],
                [
                    'print_method' => $settings['print_method'] ?? $defaults['print_method'],
                    'paper_size' => $settings['paper_size'] ?? $defaults['paper_size'],
                    'orientation' => $settings['orientation'] ?? $defaults['orientation'],
                    'scale' => $settings['scale'] ?? $defaults['scale'],
                    'margin_mm' => $settings['margin_mm'] ?? $defaults['margin_mm'],
                    'auto_print' => ! empty($settings['auto_print']),
                    'printer_name' => null,
                ]
            );

            if ($documentType === 'service' && Schema::hasColumn('print_settings', 'header_mode')) {
                DB::table('print_settings')
                    ->where('document_type', 'service')
                    ->update([
                        'header_mode' => $settings['header_mode'] ?? $defaults['header_mode'] ?? 'full',
                        'updated_at' => now(),
                    ]);
            }
        }

        return redirect()->route('company.settings')->with('success', 'Company settings updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        $company = Company::firstOrFail();
        if (! empty($company->company_logo) && Storage::disk('public')->exists('company_logos/'.$company->company_logo)) {
            Storage::disk('public')->delete('company_logos/'.$company->company_logo);
        }
        if (! empty($company->qr_code) && Storage::disk('public')->exists('company_qr_codes/'.$company->qr_code)) {
            Storage::disk('public')->delete('company_qr_codes/'.$company->qr_code);
        }
        // Storage::delete('public/company_logos/' . $company->logo);
        $company->delete();

        return redirect()->route('company.index')->with('success', 'Record deleted successfully');
    }
}
