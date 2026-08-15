<?php

namespace Modules\Service\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Contacts\app\Models\Customer;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\LedgerBook;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Services\BatchInventoryService;
use Modules\Product\app\Services\MrpInventoryService;
use Modules\Product\app\Services\InlineProductService;
use Modules\Sale\app\Models\GstStateCode;
use Modules\Sale\app\Models\SaleSetting;
use Modules\Service\app\Http\Requests\StoreServiceRequest;
use Modules\Service\app\Models\Service;
use Modules\Service\app\Services\ServiceBillingService;
use Modules\Service\app\Services\ServiceVoucherService;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\PrintSetting;
use Throwable;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceBillingService $serviceBilling,
        private readonly ServiceVoucherService $voucherService,
        private readonly BatchInventoryService $batchInventory,
        private readonly MrpInventoryService $mrpInventory,
        private readonly InlineProductService $inlineProducts
    ) {
    }

    public function index(Request $request)
    {
        return $this->listing($request, '1', 'Services List', false);
    }

    public function cancelled(Request $request)
    {
        return $this->listing($request, '0', 'Cancelled Services', true);
    }

    private function listing(Request $request, string $status, string $title, bool $isCancelled)
    {
        $financialYear = session('financial_year');
        $query = Service::with('customer')
            ->where('status', $status)
            ->where('financial_year', $financialYear)
            ->select('sv_id', 'sv_date', 'sv_vno', 'sv_customer', 'sv_amount_payable', 'sv_paid');

        [$fromDate, $toDate] = $this->dateRange($request);
        $isSearching = $request->filled('voucher') || $fromDate || $toDate || $request->filled('customer') || $request->filled('phone');

        if (!$isSearching) {
            $query->whereMonth('sv_date', Carbon::now()->month)
                ->whereYear('sv_date', Carbon::now()->year);
        } else {
            if ($request->filled('voucher')) {
                $query->where('sv_vno', 'like', '%' . $request->voucher . '%');
            }

            if ($request->filled('customer')) {
                $query->whereHas('customer', fn ($q) => $q->where('customer', 'like', '%' . $request->customer . '%'));
            }

            if ($request->filled('phone')) {
                $query->whereHas('customer', fn ($q) => $q->where('phone', 'like', '%' . $request->phone . '%'));
            }

            if ($fromDate && $toDate) {
                $query->whereBetween('sv_date', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('sv_date', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('sv_date', '<=', $toDate);
            }
        }

        return view('service::services.index', [
            'services' => $query->latest('sv_id')->get(),
            'page_title' => $title,
            'is_cancelled' => $isCancelled,
            'search_voucher' => $request->voucher,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'search_customer' => $request->customer,
            'search_phone' => $request->phone,
        ]);
    }

    public function createOrEdit($id = null)
    {
        $banks = Banking::all();
        $defaultPaymode = $banks->first(fn ($bank) => strtolower(trim((string) $bank->bk_bank)) === 'cash')?->bk_id;
        $service = null;

        if ($id) {
            $service = Service::with(['serviceDetails.product', 'serviceDetails.mrpStockLot', 'customer', 'user', 'banking'])
                ->where('status', '1')
                ->findOrFail($id);
            $voucherNo = $service->sv_vno;
            $svType = $service->sv_type ?? '1';
            $pageTitle = 'Edit Service';
        } else {
            $voucherNo = $this->voucherService->preview(now()->toDateString());
            $svType = '1';
            $pageTitle = 'Create Service';
        }

        $company = Company::first();
        $taxProfile = Company::taxProfile();
        $taxType = $taxProfile['tax_type'];
        $saleSettings = SaleSetting::values();
        $allowedServiceTypes = $taxProfile['is_composition'] ? ['1'] : ['1', '2'];

        if ($service && !in_array((string) ($service->sv_type ?? '1'), $allowedServiceTypes, true)) {
            $allowedServiceTypes[] = (string) ($service->sv_type ?? '1');
        }

        $inventoryMode = $company?->inventory_mode ?? 'standard';
        return view('service::services.create', [
            'page_title' => $pageTitle,
            'voucher_no' => $voucherNo,
            'banks' => $banks,
            'defaultPaymode' => $defaultPaymode,
            'service' => $service,
            'sv_type' => $svType,
            'saleItems' => $service?->serviceDetails->where('svd_type', 'sale')->values() ?? collect(),
            'serviceItems' => $service?->serviceDetails->where('svd_type', 'service')->values() ?? collect(),
            'saleSettings' => $saleSettings,
            'showManufacturingDate' => (bool) ($saleSettings['manufacturing_date'] ?? false),
            'taxType' => $taxType,
            'taxLabel' => $taxProfile['tax_label'],
            'gstScheme' => $taxProfile['gst_scheme'],
            'isComposition' => $taxProfile['is_composition'],
            'collectTax' => $taxProfile['collect_tax'],
            'allowedServiceTypes' => $allowedServiceTypes,
            'states' => GstStateCode::orderBy('state_name')->get(),
            'inventoryMode' => $inventoryMode,
            'batchMode' => $inventoryMode === 'batch',
            'mrpMode' => $inventoryMode === 'mrp',
        ]);
    }

    public function getVoucher(Request $request)
    {
        $validated = $request->validate([
            'sv_date' => ['nullable', 'date_format:d/m/Y'],
        ]);
        $date = !empty($validated['sv_date'])
            ? Carbon::createFromFormat('d/m/Y', $validated['sv_date'])->toDateString()
            : now()->toDateString();

        return response()->json($this->voucherService->preview($date));
    }

    public function svSearch(Request $request)
    {
        $query = $request->input('query');
        $searchBy = $request->input('searchBy');

        if ($searchBy === 'customer') {
            return response()->json(
                Customer::where('customer', 'LIKE', "%{$query}%")
                    ->orWhere('phone', 'LIKE', "%{$query}%")
                    ->get(['id', 'customer', 'phone'])
            );
        }

        if ($searchBy === 'product') {
            return response()->json($this->formatProducts(
                Product::with(['stock', 'stockBatches', 'mrpStockLots'])
                    ->where('typeid', '!=', 3)
                    ->where(fn ($q) => $q->where('product_code', 'LIKE', "%{$query}%")
                        ->orWhere('product', 'LIKE', "%{$query}%"))
                    ->get()
            ));
        }

        if ($searchBy === 'svproduct') {
            return response()->json($this->formatProducts(
                Product::where('typeid', 3)
                    ->where(fn ($q) => $q->where('product_code', 'LIKE', "%{$query}%")
                        ->orWhere('product', 'LIKE', "%{$query}%"))
                    ->get()
            ));
        }

        return response()->json([]);
    }

    public function batches(Product $product)
    {
        abort_unless($this->batchInventory->enabled($product), 404);

        return response()->json($this->batchInventory->availableBatches($product->product_code)->map(fn ($batch) => [
            'id' => $batch->id,
            'batch_no' => $batch->batch_no,
            'expiry_date' => $batch->expiry_date?->format('d/m/Y'),
            'available_quantity' => (float) $batch->available_quantity,
            'display_quantity' => round((float) $batch->available_quantity / max((float) ($product->uqty ?: 1), 1), 2),
            'mrp' => (float) $batch->mrp,
        ]));
    }

    public function mrpLots(Product $product)
    {
        abort_unless($this->mrpInventory->enabled(), 404);

        return response()->json($this->mrpInventory->availableLots($product->product_code)->map(fn ($lot) => [
            'id' => $lot->id,
            'purchase_voucher' => $lot->purchase_voucher,
            'purchase_date' => $lot->purchase_date?->format('d/m/Y'),
            'purchase_rate' => (float) $lot->purchase_rate,
            'display_purchase_rate' => round((float) $lot->purchase_rate * max((float) ($product->uqty ?: 1), 1), 2),
            'mrp' => (float) $lot->mrp,
            'sale_price' => (float) $lot->sale_price,
            'margin_percentage' => (float) $lot->margin_percentage,
            'margin_amount' => (float) $lot->margin_amount,
            'display_quantity' => round((float) $lot->available_quantity / max((float) ($product->uqty ?: 1), 1), 2),
        ]));
    }

    public function addCustomer(Request $request)
    {
        $request->validate([
            'customer' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customer,phone',
        ]);

        $customer = Customer::create([
            'customer' => $request->customer,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'id' => $customer->id,
            'customer' => $customer->customer,
            'phone' => $customer->phone,
        ], 201);
    }

    public function addProduct(Request $request)
    {
        $request->validate([
            'product' => 'required|string|max:255',
            'product_code' => 'required|string|max:50|unique:product,product_code',
            'price' => 'required|numeric|min:0',
            'gst' => 'nullable|numeric|in:0,5,12,18,28',
            'pprice' => 'nullable|numeric|min:0',
            'mrp' => 'nullable|numeric|min:0',
            'margin' => 'nullable|numeric|min:0|max:100',
            'hsn_code' => 'nullable|string|max:50',
            'typeid' => 'nullable|integer|in:2,3',
        ]);

        return response()->json($this->inlineProducts->create($request->only([
            'product',
            'product_code',
            'hsn_code',
            'gst',
            'pprice',
            'mrp',
            'margin',
            'price',
            'typeid',
        ])), 201);
    }

    public function newCode()
    {
        return response()->json(['code' => Product::getProductCode()]);
    }

    public function storeOrUpdate(StoreServiceRequest $request)
    {
        try {
            $data = $request->validated();
            $service = !empty($data['sv_id'])
                ? $this->serviceBilling->update(Service::findOrFail($data['sv_id']), $data)
                : $this->serviceBilling->create($data);

            return redirect()->route('services.show', $service->sv_id)
                ->with('success', 'Service saved successfully!');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->back()
                ->withInput()
                ->with('error', 'The service bill could not be saved. No changes were committed.');
        }
    }

    public function show($id)
    {
        return $this->showService($id, '1', false, 'View Service');
    }

    public function showCancelled($id)
    {
        return $this->showService($id, '0', true, 'View Cancelled');
    }

    public function print($id)
    {
        $service = Service::with(['serviceDetails.product', 'serviceDetails.mrpStockLot', 'customer', 'user', 'banking'])->findOrFail($id);
        $company = Company::first() ?? new Company();
        $openingRow = LedgerBook::where('lb_vid', $service->sv_id)
            ->where('lb_payee', $service->sv_customer)
            ->whereIn('lb_type', ['sv', 'svp'])
            ->orderByRaw("CASE WHEN lb_tramount > 0 THEN 0 ELSE 1 END")
            ->orderBy('lb_id', 'asc')
            ->first();
        $taxProfile = Company::taxProfile();
        $taxType = $taxProfile['tax_type'];

        return view('service::services.print', [
            'service' => $service,
            'serviceItems' => $service->serviceDetails->where('svd_type', 'service')->values(),
            'saleItems' => $service->serviceDetails->where('svd_type', 'sale')->values(),
            'company' => $company,
            'taxType' => $taxType,
            'taxLabel' => $taxProfile['tax_label'],
            'gstScheme' => $taxProfile['gst_scheme'],
            'isComposition' => $taxProfile['is_composition'],
            'collectTax' => $taxProfile['collect_tax'],
            'customerOpening' => $openingRow?->lb_opbalance ?? $service->customer?->op_balance ?? 0,
            'customerClosing' => $openingRow?->lb_clbalance ?? 0,
            'printSetting' => PrintSetting::forDocument('service'),
            'states' => GstStateCode::orderBy('state_name')->get(),
        ]);
    }

    public function destroy($id)
    {
        $this->serviceBilling->cancel(Service::findOrFail($id));

        return redirect()->route('services.index')->with('success', 'Service cancelled successfully.');
    }

    private function showService($id, string $status, bool $isCancelled, string $title)
    {
        $service = Service::with(['serviceDetails.product', 'serviceDetails.mrpStockLot', 'customer', 'user', 'banking'])
            ->where('status', $status)
            ->findOrFail($id);
        $taxProfile = Company::taxProfile();
        $taxType = $taxProfile['tax_type'];

        return view('service::services.view', [
            'is_cancelled' => $isCancelled,
            'page_title' => $title,
            'service' => $service,
            'serviceItems' => $service->serviceDetails->where('svd_type', 'service')->values(),
            'saleItems' => $service->serviceDetails->where('svd_type', 'sale')->values(),
            'taxType' => $taxType,
            'taxLabel' => $taxProfile['tax_label'],
            'gstScheme' => $taxProfile['gst_scheme'],
            'isComposition' => $taxProfile['is_composition'],
            'collectTax' => $taxProfile['collect_tax'],
            'states' => GstStateCode::orderBy('state_name')->get(),
        ]);
    }

    private function dateRange(Request $request): array
    {
        if (!$request->filled('fromtodates')) {
            return [null, null];
        }

        $dates = explode(' - ', $request->fromtodates);
        if (count($dates) !== 2) {
            return [null, null];
        }

        try {
            return [
                Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d'),
                Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d'),
            ];
        } catch (Throwable) {
            return [null, null];
        }
    }

    private function formatProducts($products)
    {
        return $products->map(function ($product) {
            $unitQty = $product->uqty ?: 1;
            $stockQty = $this->mrpInventory->enabled()
                ? $product->mrpStockLots->sum('available_quantity')
                : (($product->is_batch_managed ?? false) && $this->batchInventory->enabled($product)
                    ? $product->stockBatches->sum('available_quantity')
                    : ($product->stock ? $product->stock->stock_qty : 0));
            $normalizedStock = $unitQty > 0 ? round($stockQty / $unitQty, 2) : 0;

            return [
                'id' => $product->id,
                'product_code' => $product->product_code,
                'product' => $product->product,
                'hsn_code' => $product->hsn_code,
                'price' => $product->price,
                'unit' => $product->unit,
                'uqty' => $product->uqty,
                'gst' => $product->gst,
                'current_stock' => $normalizedStock,
                'is_batch_managed' => (bool) ($product->is_batch_managed ?? false),
            ];
        });
    }
}