<?php

namespace Modules\Purchase\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\LedgerBook;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Services\InventoryLabelService;
use Modules\Purchase\app\Http\Requests\StorePurchaseRequest;
use Modules\Purchase\app\Models\Purchase;
use Modules\Contacts\app\Models\Vendor;
use Modules\Purchase\app\Services\PurchaseService;
use Modules\Purchase\app\Services\PurchaseVoucherService;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\PrintSetting;
use Throwable;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchaseService,
        private readonly PurchaseVoucherService $voucherService,
        private readonly InventoryLabelService $inventoryLabels
    ) {
    }

    public function index(Request $request)
    {
        $financialYear = session('financial_year') ?: Purchase::getFinancialYear(now());
        $query = Purchase::with('vendor')
        ->where('pu_status', '1')
        ->where('financial_year', $financialYear)
        ->select('pu_id', 'pu_date', 'pu_vno', 'pu_bill_number', 'pu_vendor', 'pu_amount_payable', 'pu_paid'); 
        
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
        $isSearching = $request->filled('voucher') || $fromDate || $toDate || $request->filled('vendor') || $request->filled('phone');

        if (!$isSearching) {
        $query->whereMonth('pu_date', Carbon::now()->month)
              ->whereYear('pu_date', Carbon::now()->year);
        } else {
            if ($request->filled('voucher')) {
                $query->where('pu_vno', 'like', '%' . $request->voucher . '%');
            }

            if ($request->filled('vendor')) {
                 $query->whereHas('vendor', function ($q) use ($request) {
                    $q->where('cp_name', 'like', '%' . $request->vendor . '%');
                });
            }
            
            if ($request->filled('phone')) {
                $query->whereHas('vendor', function ($q) use ($request) {
                    $q->where('cp_phone', 'like', '%' . $request->phone . '%');
                });
            }
    
            if ($fromDate && $toDate) {
                $query->whereBetween('pu_date', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('pu_date', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('pu_date', '<=', $toDate);
            }
        }
    
        $purchases = $query->latest('pu_id')->get();
        
        return view('purchase::purchases.index', [
                'purchases' => $purchases,
                'page_title' => 'Purchase List',
                'is_cancelled' => false,
                'search_voucher' => $request->voucher,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'search_vendor' => $request->vendor,
                'search_phone' => $request->phone,
        ]);
    }

    public function cancelled(Request $request)
    {
        $financialYear = session('financial_year') ?: Purchase::getFinancialYear(now());
        $query = Purchase::with('vendor')
        ->where('pu_status', '0') // cancelled purchases
        ->where('financial_year', $financialYear)
        ->select('pu_id', 'pu_date', 'pu_vno', 'pu_bill_number', 'pu_vendor', 'pu_amount_payable', 'pu_paid');

        // Parse date range
        $fromDate = null;
        $toDate = null;

        if ($request->filled('fromtodates')) {
            $dates = explode(' - ', $request->fromtodates);
            if (count($dates) === 2) {
                try {
                    $fromDate = Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
                    $toDate = Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d');
                } catch (\Exception $e) {
                    // Invalid format, ignore
                }
            }
        }

        $isSearching = $request->filled('voucher') || $fromDate || $toDate || $request->filled('vendor') || $request->filled('phone');

        if ($isSearching) {
            if ($request->filled('voucher')) {
                $query->where('pu_vno', 'like', '%' . $request->voucher . '%');
            }

            if ($request->filled('vendor')) {
                $query->whereHas('vendor', function ($q) use ($request) {
                    $q->where('cp_name', 'like', '%' . $request->vendor . '%');
                });
            }

            if ($request->filled('phone')) {
                $query->whereHas('vendor', function ($q) use ($request) {
                    $q->where('cp_phone', 'like', '%' . $request->phone . '%');
                });
            }

            if ($fromDate && $toDate) {
                $query->whereBetween('pu_date', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('pu_date', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('pu_date', '<=', $toDate);
            }
        }

        $purchases = $query->latest('pu_id')->get();

        return view('purchase::purchases.index', [
            'purchases' => $purchases,
            'page_title' => 'Cancelled Purchases',
            'is_cancelled' => true,
            'search_voucher' => $request->voucher,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'search_vendor' => $request->vendor,
            'search_phone' => $request->phone,
        ]);
    }

    public function createOrEdit($id = null)
    {
        $banks      = Banking::all();
        $purchase   = null;
        if ($id) {
            $purchase = Purchase::with(['purchaseDetails.product','vendor','user','banking'])
                ->where('pu_status', '1')
                ->findOrFail($id);
            $voucher_no = $purchase->pu_vno;
            $pu_type    = $purchase->pu_type;
            $page_title = "Edit Purchase";
        } else {
            $pu_type    = '1'; // Default to B2B
            $voucher_no = $this->voucherService->preview($pu_type, now()->toDateString());
            $page_title = "Create Purchase";
        }
        $company = Company::first();
        $taxProfile = Company::taxProfile();
        $allowedPurchaseTypes = $taxProfile['is_composition'] ? ['1'] : ['1', '2'];

        if ($purchase && !in_array((string) $purchase->pu_type, $allowedPurchaseTypes, true)) {
            $allowedPurchaseTypes[] = (string) $purchase->pu_type;
        }

        $inventoryMode = $company?->inventory_mode ?? 'standard';
        $data = [
            'page_title' => $page_title,
            'voucher_no' => $voucher_no,
            'banks'      => $banks,
            'purchase'   => $purchase,
            'pu_type'    => $pu_type,
            'taxType'    => $taxProfile['tax_type'],
            'taxLabel'   => $taxProfile['tax_label'],
            'gstScheme'  => $taxProfile['gst_scheme'],
            'isComposition' => $taxProfile['is_composition'],
            'collectTax' => $taxProfile['collect_tax'],
            'allowedPurchaseTypes' => $allowedPurchaseTypes,
            'inventoryMode' => $inventoryMode,
            'batchMode'  => $inventoryMode === 'batch',
            'mrpMode'    => $inventoryMode === 'mrp',
        ];
        return view('purchase::purchases.create', $data);
    }

    public function getVoucher()
    {
        $pu_type    = (string) request()->get('pu_type', 1);
        if (Company::taxProfile()['is_composition'] && $pu_type !== '1') {
            abort(422, 'Composition GST purchase bills can only use B2B.');
        }
        $date = request()->filled('pu_date')
            ? Carbon::createFromFormat('d/m/Y', request('pu_date'))->toDateString()
            : now()->toDateString();
        $voucher_no = $this->voucherService->preview((string) $pu_type, $date);
        return response()->json($voucher_no);
    }

    public function psearch(Request $request)
    {
        $query = $request->input('query');
        $searchBy = $request->input('searchBy');

        if ($searchBy === 'vendor') {
            $vendors = Vendor::where('cp_name', 'LIKE', "%{$query}%")
                         ->orWhere('cp_phone', 'LIKE', "%{$query}%")
                         ->orWhere('cp_cphone', 'LIKE', "%{$query}%")
                         ->get(['id', 'cp_name', 'cp_phone']);
            return response()->json($vendors);
        } elseif ($searchBy === 'product') {
            $products = Product::where('typeid', '!=', 3)
                ->where(function ($q) use ($query) {
                    $q->where('product_code', 'LIKE', "%{$query}%")
                    ->orWhere('product', 'LIKE', "%{$query}%");
                })
                ->get(['id', 'product_code', 'product', 'hsn_code', 'pprice', 'mrp', 'price', 'margin', 'amt_margin', 'unit', 'uqty', 'gst', 'is_batch_managed']);
            // if ($products->isEmpty()) {
            //     return response()->json(['message' => 'No products found'], 404);
            // }
            return response()->json($products);
        }
        return response()->json([]);
    }

    public function addVendor(Request $request)
    {
        $request->validate([
            'cp_name'   => 'required|string|max:255',
            'cp_phone'  => 'required|string|max:20|unique:vendor,cp_phone',
        ]);

        $vendor = Vendor::create([
            'cp_name' => $request->cp_name,
            'cp_phone' => $request->cp_phone,
        ]);

        return response()->json([
            'id' => $vendor->id,
            'cp_name' => $vendor->cp_name,
            'cp_phone' => $vendor->cp_phone,
        ], 201);
    }

    public function storeOrUpdate(StorePurchaseRequest $request)
    {
        try {
            $data = $request->validated();
            $purchase = !empty($data['pu_id'])
                ? $this->purchaseService->update(Purchase::findOrFail($data['pu_id']), $data)
                : $this->purchaseService->create($data);

            return redirect()->route('purchases.show', $purchase->pu_id)
                ->with('success', 'Purchase saved successfully!');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->back()
                ->withInput()
                ->with('error', 'The purchase could not be saved. No changes were committed.');
        }
    }

    public function show($id)
    {
        $purchase = Purchase::with($this->purchaseViewRelations())
            ->where('pu_status', '1')
            ->findOrFail($id);
        $data['is_cancelled']   = false;
        $data['page_title']     = "View Purchase";
        $data['purchase']       = $purchase;
        $taxProfile             = Company::taxProfile();
        $taxType                = $taxProfile['tax_type'];
        $data['taxType']        = $taxType;
        $data['taxLabel']       = $taxProfile['tax_label'];
        $data['gstScheme']      = $taxProfile['gst_scheme'];
        $data['isComposition']  = $taxProfile['is_composition'];
        $data['collectTax']     = $taxProfile['collect_tax'];
        $data['purchaseItemLabels'] = $this->purchaseItemLabels($purchase);
        return view('purchase::purchases.view',$data);
    }

    public function showCancelled($id)
    {
        $purchase = Purchase::with($this->purchaseViewRelations())
            ->where('pu_status', '0')
            ->findOrFail($id);
        $data['is_cancelled']   = true;
        $data['page_title']     = "View Cancelled";
        $data['purchase']       = $purchase;
        $taxProfile             = Company::taxProfile();
        $taxType                = $taxProfile['tax_type'];
        $data['taxType']        = $taxType;
        $data['taxLabel']       = $taxProfile['tax_label'];
        $data['gstScheme']      = $taxProfile['gst_scheme'];
        $data['isComposition']  = $taxProfile['is_composition'];
        $data['collectTax']     = $taxProfile['collect_tax'];
        $data['purchaseItemLabels'] = $this->purchaseItemLabels($purchase);
        return view('purchase::purchases.view',$data);
    }

    private function purchaseViewRelations(): array
    {
        return [
            'purchaseDetails.product',
            'purchaseDetails.mrpStockLot',
            'purchaseDetails.batchMovement.batch',
            'vendor',
            'user',
            'banking',
        ];
    }

    private function purchaseItemLabels(Purchase $purchase)
    {
        $inventoryMode = Company::query()->value('inventory_mode') ?? 'standard';

        if (!in_array($inventoryMode, ['mrp', 'batch'], true)) {
            return collect();
        }

        return $purchase->purchaseDetails
            ->mapWithKeys(function ($detail) use ($inventoryMode) {
                $label = $inventoryMode === 'mrp'
                    ? ($detail->mrpStockLot ? $this->inventoryLabels->ensureForMrpLot($detail->mrpStockLot) : null)
                    : ($detail->batchMovement?->batch ? $this->inventoryLabels->ensureForBatch($detail->batchMovement->batch) : null);

                return $label ? [$detail->pud_id => $label] : [];
            });
    }

    public function print($id)
    {
        $purchase   = Purchase::with(['purchaseDetails.product','vendor','user','banking'])->findOrFail($id);
        $company            = Company::first() ?? new Company();
        $openingRow = LedgerBook::where('lb_vid', $purchase->pu_id)
            ->where('lb_payee', $purchase->pu_vendor)
            ->whereIn('lb_type', ['p', 'pp'])
            ->orderByRaw("CASE WHEN lb_tramount > 0 THEN 0 ELSE 1 END")
            ->orderBy('lb_id', 'asc')
            ->first();

        $taxProfile                 = Company::taxProfile();
        $taxType                    = $taxProfile['tax_type'];
        $data['purchase']           = $purchase;
        $data['company']            = $company;
        $data['taxType']            = $taxType;
        $data['taxLabel']           = $taxProfile['tax_label'];
        $data['gstScheme']          = $taxProfile['gst_scheme'];
        $data['isComposition']      = $taxProfile['is_composition'];
        $data['collectTax']         = $taxProfile['collect_tax'];
        $data['vendorOpening']      = $openingRow?->lb_opbalance ?? $purchase->vendor?->op_balance ?? 0;
        $data['vendorClosing']      = $openingRow?->lb_clbalance ?? 0;
        $data['printSetting']       = PrintSetting::forDocument('purchase');
        return view('purchase::purchases.print',$data);
    }

    public function destroy($id)
    {
        $this->purchaseService->cancel(Purchase::findOrFail($id));
        
        return redirect()->route('purchases.index')->with('success', 'Purchase cancelled successfully.');
    }
}