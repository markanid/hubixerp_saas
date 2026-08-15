<?php

namespace Modules\Sale\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Contacts\app\Models\Customer;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\LedgerBook;
use Modules\Product\app\Models\Product;
use Modules\Sale\app\Http\Requests\StoreSaleRequest;
use Modules\Sale\app\Models\Sale;
use Modules\Sale\app\Models\SaleSetting;
use Modules\Sale\app\Models\GstStateCode;
use Modules\Sale\app\Services\SaleService;
use Modules\Sale\app\Services\SaleVoucherService;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\PrintSetting;
use Modules\Product\app\Services\BatchInventoryService;
use Modules\Product\app\Services\MrpInventoryService;
use Modules\Product\app\Services\InventoryLabelService;
use Modules\Product\app\Services\InlineProductService;
use Throwable;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
        private readonly SaleVoucherService $voucherService,
        private readonly BatchInventoryService $batchInventory,
        private readonly MrpInventoryService $mrpInventory,
        private readonly InventoryLabelService $inventoryLabels,
        private readonly InlineProductService $inlineProducts
    ) {
    }

    public function index(Request $request)
    {
        $financialYear = session('financial_year');
        $query = Sale::with('customer')
        ->where('status', '1')
        ->where('financial_year', $financialYear)
        ->select('sa_id', 'sa_date', 'sa_vno', 'sa_customer', 'sa_amount_payable', 'sa_paid'); 
        
        // Parse fromtodates to from_date and to_date
        $fromDate = null;
        $toDate = null;

        if ($request->filled('fromtodates')) {
            $dates = explode(' - ', $request->fromtodates);
            if (count($dates) == 2) {
                try {
                    $fromDate = Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
                    $toDate = Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d');
                } catch (\Exception $e) {
                    // Invalid format; skip filtering
                }
            }
        }
        
        // If no date or voucher is provided, default to current month
        $isSearching = $request->filled('voucher') || $fromDate || $toDate || $request->filled('customer') || $request->filled('phone');

        if (!$isSearching) {
        $query->whereMonth('sa_date', Carbon::now()->month)
              ->whereYear('sa_date', Carbon::now()->year);
        } else {
            if ($request->filled('voucher')) {
                $query->where('sa_vno', 'like', '%' . $request->voucher . '%');
            }

            if ($request->filled('customer')) {
                 $query->whereHas('customer', function ($q) use ($request) {
                    $q->where('customer', 'like', '%' . $request->customer . '%');
                });
            }
            
            if ($request->filled('phone')) {
                $query->whereHas('customer', function ($q) use ($request) {
                    $q->where('phone', 'like', '%' . $request->phone . '%');
                });
            }
    
            if ($fromDate && $toDate) {
                $query->whereBetween('sa_date', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('sa_date', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('sa_date', '<=', $toDate);
            }
        }
    
        $sales = $query->latest('sa_id')->get();
        
        return view('sale::sales.index', [
                'sales' => $sales,
                'page_title' => 'Sales List',
                'is_cancelled' => false,
                'search_voucher' => $request->voucher,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'search_customer' => $request->customer,
                'search_phone' => $request->phone,
        ]);
    }

    public function cancelled(Request $request)
    {
        $financialYear = session('financial_year');
        
        $query = Sale::with('customer')
        ->where('status', '0') // cancelled purchases
        ->where('financial_year', $financialYear)
        ->select('sa_id', 'sa_date', 'sa_vno', 'sa_customer', 'sa_amount_payable', 'sa_paid'); 

        // Parse fromtodates to from_date and to_date
        $fromDate = null;
        $toDate = null;

        if ($request->filled('fromtodates')) {
            $dates = explode(' - ', $request->fromtodates);
            if (count($dates) == 2) {
                try {
                    $fromDate = Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
                    $toDate = Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d');
                } catch (\Exception $e) {
                    // Invalid format; skip filtering
                }
            }
        }
        
        // If no date or voucher is provided, default to current month
        $isSearching = $request->filled('voucher') || $fromDate || $toDate || $request->filled('customer') || $request->filled('phone');

        if (!$isSearching) {
        $query->whereMonth('sa_date', Carbon::now()->month)
              ->whereYear('sa_date', Carbon::now()->year);
        } else {
            if ($request->filled('voucher')) {
                $query->where('sa_vno', 'like', '%' . $request->voucher . '%');
            }

            if ($request->filled('customer')) {
                 $query->whereHas('customer', function ($q) use ($request) {
                    $q->where('customer', 'like', '%' . $request->customer . '%');
                });
            }
            
            if ($request->filled('phone')) {
                $query->whereHas('customer', function ($q) use ($request) {
                    $q->where('phone', 'like', '%' . $request->phone . '%');
                });
            }
    
            if ($fromDate && $toDate) {
                $query->whereBetween('sa_date', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('sa_date', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('sa_date', '<=', $toDate);
            }
        }
    
        $sales = $query->latest('sa_id')->get();
        
        return view('sale::sales.index', [
                'sales' => $sales,
                'page_title' => 'Cancelled Sales',
                'is_cancelled' => true,
                'search_voucher' => $request->voucher,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'search_customer' => $request->customer,
                'search_phone' => $request->phone,
        ]);
    }

    public function createOrEdit($id = null)
    {
        $banks      = Banking::all();
        $defaultPaymode = $banks->first(fn ($bank) => strtolower(trim((string) $bank->bk_bank)) === 'cash')?->bk_id;
        $sale       = null;
        if ($id) {
            $sale = Sale::with(['saleDetails.product','saleDetails.mrpStockLot','customer','user','banking'])
                ->where('status', '1')
                ->findOrFail($id);
            $voucher_no = $sale->sa_vno;
            $sa_type    = $sale->sa_type;
            $page_title = "Edit Sale";
        } else {
            $sa_type    = '1'; // Default to B2C
            $voucher_no = $this->voucherService->preview($sa_type, now()->toDateString());
            $page_title = "Create Sale";
        }
        $saleSettings = SaleSetting::values();
        $company = Company::first();
        $taxProfile = Company::taxProfile();
        $taxType = $taxProfile['tax_type'];
        $states = GstStateCode::orderBy('state_name')->get();
        $allowedSaleTypes = $taxProfile['is_composition'] ? ['1'] : ['1', '2'];

        if ($sale && !in_array((string) $sale->sa_type, $allowedSaleTypes, true)) {
            $allowedSaleTypes[] = (string) $sale->sa_type;
        }

        $inventoryMode = $company?->inventory_mode ?? 'standard';
        $data = [
            'page_title'    => $page_title,
            'voucher_no'    => $voucher_no,
            'banks'         => $banks,
            'defaultPaymode'=> $defaultPaymode,
            'sale'          => $sale,
            'sa_type'       => $sa_type,
            'saleSettings'  => $saleSettings,
            'taxType'       => $taxType,
            'taxLabel'      => $taxProfile['tax_label'],
            'gstScheme'     => $taxProfile['gst_scheme'],
            'isComposition' => $taxProfile['is_composition'],
            'collectTax'    => $taxProfile['collect_tax'],
            'allowedSaleTypes' => $allowedSaleTypes,
            'states'        => $states,
            'inventoryMode' => $inventoryMode,
            'batchMode'     => $inventoryMode === 'batch',
            'mrpMode'       => $inventoryMode === 'mrp',
        ];
        return view('sale::sales.create', $data);
    }

    public function getVoucher(Request $request)
    {
        $validated = $request->validate([
            'sa_type' => ['required', 'in:1,2'],
            'sa_date' => ['nullable', 'date_format:d/m/Y'],
        ]);
        $taxProfile = Company::taxProfile();
        if ($taxProfile['is_composition'] && (string) $validated['sa_type'] !== '1') {
            abort(422, 'Composition GST sale bills can only use B2C.');
        }
        $date = !empty($validated['sa_date'])
            ? Carbon::createFromFormat('d/m/Y', $validated['sa_date'])->toDateString()
            : now()->toDateString();
        $voucher_no = $this->voucherService->preview((string) $validated['sa_type'], $date);

        return response()->json($voucher_no);
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        $searchBy = $request->input('searchBy');

        if ($searchBy === 'customer') {
            $customers = Customer::where('customer', 'LIKE', "%{$query}%")
                         ->orWhere('phone', 'LIKE', "%{$query}%")
                         ->get(['id', 'customer', 'phone']);

            return response()->json($customers);
        } 
        
        if ($searchBy === 'product') { 
            $inventoryLabel = $this->inventoryLabels->resolveActive((string) $query);
            $products = Product::with(['stock', 'stockBatches', 'mrpStockLots'])
                ->where('typeid', '!=', 3)
                ->when(
                    $inventoryLabel,
                    fn ($builder) => $builder->whereKey($inventoryLabel->product->id),
                    fn ($builder) => $builder->where(function ($q) use ($query) {
                        $q->where('product_code', 'LIKE', "%{$query}%")
                            ->orWhere('product', 'LIKE', "%{$query}%")
                            ->orWhere('bar_code', 'LIKE', "%{$query}%");
                    })
                )
                ->get();

            $formattedProducts = $products->map(function ($product) use ($inventoryLabel) {
                $unitQty = $product->uqty ?: 1; // Prevent division by zero
                $selectedMrpLot = $inventoryLabel?->mrpStockLot;
                $selectedBatch = $inventoryLabel?->stockBatch;
                $slotDetails = $inventoryLabel ? $this->inventoryLabels->slotDetails($inventoryLabel, $product) : null;
                $stockQty = $inventoryLabel
                    ? $this->inventoryLabels->availableQuantity($inventoryLabel)
                    : ($this->mrpInventory->enabled()
                            ? $product->mrpStockLots->sum('available_quantity')
                            : ($product->is_batch_managed && $this->batchInventory->enabled($product)
                                ? $product->stockBatches->sum('available_quantity')
                                : ($product->stock ? $product->stock->stock_qty : 0)));
                $normalizedStock = $unitQty > 0 ? round($stockQty / $unitQty, 2) : 0;
                return [
                    'id'            => $product->id,
                    'product_code'  => $product->product_code,
                    'product'       => $product->product,
                    'hsn_code'      => $product->hsn_code,
                    'price'         => $slotDetails['sale_price'] ?? $product->price,
                    'mrp'           => $slotDetails['mrp'] ?? $product->mrp,
                    'margin'        => $product->margin,
                    'amt_margin'    => $product->amt_margin,
                    'unit'          => $product->unit,
                    'uqty'          => $product->uqty,
                    'gst'           => $product->gst,
                    'current_stock' => $normalizedStock,
                    'is_batch_managed' => (bool) $product->is_batch_managed,
                    'stock_batch_id' => $selectedBatch?->id,
                    'mrp_stock_lot_id' => $selectedMrpLot?->id,
                    'inventory_barcode' => $inventoryLabel?->barcode,
                ];
            });
            
            return response()->json($formattedProducts);
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

        return response()->json($this->mrpInventory->saleSelectableLots($product->product_code)->map(fn ($lot) => [
            'id' => $lot->id,
            'purchase_voucher' => $lot->purchase_voucher,
            'purchase_date' => $lot->purchase_date?->format('d/m/Y'),
            'purchase_rate' => (float) $lot->purchase_rate,
            'display_purchase_rate' => round((float) $lot->purchase_rate * max((float) ($product->uqty ?: 1), 1), 2),
            'mrp' => (float) $lot->mrp,
            'sale_price' => (float) $lot->sale_price,
            'margin_percentage' => (float) $lot->margin_percentage,
            'margin_amount' => (float) $lot->margin_amount,
            'available_quantity' => (float) $lot->available_quantity,
            'display_quantity' => round((float) $lot->available_quantity / max((float) ($product->uqty ?: 1), 1), 2),
        ]));
    }

    public function addCustomer(Request $request)
    {
        $request->validate([
            'customer'  => 'required|string|max:255',
            'phone'     => 'required|string|max:20|unique:customer,phone',
        ]);

        $customer = Customer::create([
            'customer'  => $request->customer,
            'phone'     => $request->phone,
        ]);

        return response()->json([
            'id'        => $customer->id,
            'customer'  => $customer->customer,
            'phone'     => $customer->phone,
        ], 201);
    }

    public function addProduct(Request $request)
    {
        $request->validate([
            'product'       => 'required|string|max:255',
            'product_code'  => 'required|string|max:50|unique:product,product_code',
            'price'         => 'required|numeric|min:0',
            'gst'           => 'nullable|numeric|in:0,5,12,18,28',
            'pprice'        => 'nullable|numeric|min:0',
            'mrp'           => 'nullable|numeric|min:0',
            'margin'        => 'nullable|numeric|min:0|max:100',
            'hsn_code'      => 'nullable|string|max:50',
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
        ])), 201);
    }

    public function newCode()
    {
        return response()->json(['code' => Product::getProductCode()]);
    }

    public function storeOrUpdate(StoreSaleRequest $request)
    {
        try {
            $data = $request->validated();
            $sale = !empty($data['sa_id'])
                ? $this->saleService->update(Sale::findOrFail($data['sa_id']), $data)
                : $this->saleService->create($data);

            return redirect()->route('sales.show', $sale->sa_id)
                ->with('success', 'Sale saved successfully!');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->back()
                ->withInput()
                ->with('error', 'The sale could not be saved. No changes were committed.');
        }
    }

    public function show($id)
    {
        $sale = Sale::with(['saleDetails.product','saleDetails.batchMovements.batch','saleDetails.mrpStockLot','customer','user','banking'])
            ->where('status', '1')
            ->findOrFail($id);
        $data['saleSettings']   = SaleSetting::values();
        $company                = Company::first();
        $taxProfile             = Company::taxProfile();
        $taxType                = $taxProfile['tax_type'];
        $data['is_cancelled']   = false;
        $data['page_title']     = "View Sale";
        $data['sale']           = $sale;
        $data['taxType']        = $taxType;
        $data['taxLabel']       = $taxProfile['tax_label'];
        $data['gstScheme']      = $taxProfile['gst_scheme'];
        $data['isComposition']  = $taxProfile['is_composition'];
        $data['collectTax']     = $taxProfile['collect_tax'];
        $data['states']         = GstStateCode::orderBy('state_name')->get();
        return view('sale::sales.view',$data);
    }

    public function showCancelled($id)
    {
        $sale = Sale::with(['saleDetails.product','saleDetails.batchMovements.batch','saleDetails.mrpStockLot','customer','user','banking'])
            ->where('status', '0')
            ->findOrFail($id);
        $data['saleSettings']   = SaleSetting::values();
        $company                = Company::first();
        $taxProfile             = Company::taxProfile();
        $taxType                = $taxProfile['tax_type'];
        $data['is_cancelled']   = true;
        $data['page_title']     = "View Cancelled";
        $data['sale']           = $sale;
        $data['taxType']        = $taxType;
        $data['taxLabel']       = $taxProfile['tax_label'];
        $data['gstScheme']      = $taxProfile['gst_scheme'];
        $data['isComposition']  = $taxProfile['is_composition'];
        $data['collectTax']     = $taxProfile['collect_tax'];
        $data['states']         = GstStateCode::orderBy('state_name')->get();
        return view('sale::sales.view',$data);
    }

    public function print($id)
    {
        $sale = Sale::with(['saleDetails.product','saleDetails.batchMovements.batch','saleDetails.mrpStockLot','customer','user','banking'])->findOrFail($id);
        $company            = Company::first() ?? new Company();
        $openingRow = LedgerBook::where('lb_vid', $sale->sa_id)
            ->where('lb_payee', $sale->sa_customer)
            ->whereIn('lb_type', ['s','sp'])
            ->orderByRaw("CASE WHEN lb_tramount > 0 THEN 0 ELSE 1 END") // prefer sale-transaction row
            ->orderBy('lb_id', 'asc')
            ->first();


        $data['saleSettings']   = SaleSetting::values();
        $taxProfile                 = Company::taxProfile();
        $taxType                    = $taxProfile['tax_type'];
        
        $data['sale']               = $sale;
        $data['company']            = $company; 
        $data['taxType']            = $taxType;
        $data['taxLabel']           = $taxProfile['tax_label'];
        $data['gstScheme']          = $taxProfile['gst_scheme'];
        $data['isComposition']      = $taxProfile['is_composition'];
        $data['collectTax']         = $taxProfile['collect_tax'];
        $data['customerOpening']    = $openingRow?->lb_opbalance ?? $sale->customer?->op_balance ?? 0;
        $data['customerClosing']    = $openingRow?->lb_clbalance ?? 0;
        $data['states']             = GstStateCode::orderBy('state_name')->get();
        $data['printSetting']       = PrintSetting::forDocument('sale');
        return view('sale::sales.print',$data);
    }


    public function destroy($id)
    {
        $this->saleService->cancel(Sale::findOrFail($id));
        
        return redirect()->route('sales.index')->with('success', 'Sale cancelled successfully.');
    }
}