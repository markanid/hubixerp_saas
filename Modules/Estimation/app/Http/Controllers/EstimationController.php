<?php

namespace Modules\Estimation\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Contacts\app\Models\Customer;
use Modules\Estimation\app\Models\Estimation;
use Modules\Estimation\app\Models\EstimationDetail;
use Modules\Estimation\app\Models\EstimationSetting;
use Modules\Estimation\app\Services\EstimationBillingService;
use Modules\Finance\app\Models\Banking;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Services\MrpInventoryService;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\PrintSetting;

class EstimationController extends Controller
{
    public function __construct(
        private readonly EstimationBillingService $billingService,
        private readonly MrpInventoryService $mrpInventory
    )
    {
    }

    public function index(Request $request)
    {
        $financialYear = session('financial_year');
        $query = Estimation::with('customer')
        ->where('status', '1')
        ->where('financial_year', $financialYear)
        ->select('es_id', 'es_date', 'es_vno', 'es_customer', 'es_amount_payable', 'es_account_effect', 'es_amount_paid', 'es_balance'); 
        
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
        $query->whereMonth('es_date', Carbon::now()->month)
              ->whereYear('es_date', Carbon::now()->year);
        } else {
            if ($request->filled('voucher')) {
                $query->where('es_vno', 'like', '%' . $request->voucher . '%');
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
                $query->whereBetween('es_date', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('es_date', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('es_date', '<=', $toDate);
            }
        }
    
        $estimations = $query->latest('es_id')->get();
        
        return view('estimation::estimations.index', [
                'estimations'       => $estimations,
                'page_title'        => 'Estimations List',
                'is_cancelled'      => false,
                'search_voucher'    => $request->voucher,
                'from_date'         => $fromDate,
                'to_date'           => $toDate,
                'search_customer'   => $request->customer,
                'search_phone'      => $request->phone,
        ]);
    }

    public function cancelled(Request $request)
    {
        $financialYear = session('financial_year');
        $query = Estimation::with('customer')
        ->where('status', '0')
        ->where('financial_year', $financialYear)
        ->select('es_id', 'es_date', 'es_vno', 'es_customer', 'es_amount_payable', 'es_account_effect', 'es_amount_paid', 'es_balance'); 

        $fromDate = null;
        $toDate = null;

        if ($request->filled('fromtodates')) {
            $dates = explode(' - ', $request->fromtodates);
            if (count($dates) == 2) {
                try {
                    $fromDate = Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
                    $toDate = Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }
        }
        
        // If no date or voucher is provided, default to current month
        $isSearching = $request->filled('voucher') || $fromDate || $toDate || $request->filled('customer') || $request->filled('phone');

        if (!$isSearching) {
        $query->whereMonth('es_date', Carbon::now()->month)
              ->whereYear('es_date', Carbon::now()->year);
        } else {
            if ($request->filled('voucher')) {
                $query->where('es_vno', 'like', '%' . $request->voucher . '%');
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
                $query->whereBetween('es_date', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('es_date', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('es_date', '<=', $toDate);
            }
        }
    
        $estimations = $query->latest('es_id')->get();
        
        return view('estimation::estimations.index', [
                'estimations'       => $estimations,
                'page_title'        => 'Cancelled Estimations',
                'is_cancelled'      => true,
                'search_voucher'    => $request->voucher,
                'from_date'         => $fromDate,
                'to_date'           => $toDate,
                'search_customer'   => $request->customer,
                'search_phone'      => $request->phone,
        ]);
    }

    public function createOrEdit($id = null)
    {
        $banks      = Banking::where('bk_status', '1')->get();
        $defaultPaymode = $banks->first(fn ($bank) => strtolower(trim((string) $bank->bk_bank)) === 'cash')?->bk_id;
        $settings = EstimationSetting::values();
        $accountEffectEnabled = (bool) ($settings['affect_customer_accounts'] ?? false);
        $useMrpPricingMode = (bool) ($settings['estimation_mrp_pricing_mode'] ?? false);
        $company = Company::first();
        $estimation   = null;
        if ($id) {
            $estimation = Estimation::with(['estimationDetails.product','estimationDetails.mrpStockLot','customer','user','banking'])->findOrFail($id);
            $voucher_no = $estimation->es_vno;
            $page_title = "Edit Estimation";
        } else {
            $voucher_no = Estimation::getVoucherCode();
            $page_title = "Create Estimation";
        }

        $inventoryMode = $company?->inventory_mode ?? 'standard';
        $data = [
            'page_title'    => $page_title,
            'voucher_no'    => $voucher_no,
            'estimation'    => $estimation,
            'banks'         => $banks,
            'defaultPaymode'=> $defaultPaymode,
            'accountEffectEnabled' => $accountEffectEnabled,
            'useMrpPricingMode' => $useMrpPricingMode,
            'inventoryMode' => $inventoryMode,
            'batchMode'     => $inventoryMode === 'batch',
            'mrpMode'       => $inventoryMode === 'mrp',
        ];
        return view('estimation::estimations.create', $data);
    }

    public function eSearch(Request $request)
    {
        $query = $request->input('query');
        $searchBy = $request->input('searchBy');

        if ($searchBy === 'customer') {
            $customers = Customer::where('customer', 'LIKE', "%{$query}%")
                         ->orWhere('phone', 'LIKE', "%{$query}%")
                         ->orWhere('phone', 'LIKE', "%{$query}%")
                         ->get(['id', 'customer', 'phone']);

            return response()->json($customers);
        } 
        
        if ($searchBy === 'product') { 
            $products = Product::with(['stock', 'mrpStockLots']) // Eager-load the stock relationship
                ->where('typeid', '!=', 3) // Exclude service products
                ->where(function ($q) use ($query) {
                    $q->where('product_code', 'LIKE', "%{$query}%")
                    ->orWhere('product', 'LIKE', "%{$query}%");
                })
                ->get();

            $formattedProducts = $products->map(function ($product) {
                $unitQty = $product->uqty ?: 1; // Prevent division by zero
                $stockQty = $this->mrpInventory->enabled()
                    ? $product->mrpStockLots->sum('available_quantity')
                    : ($product->stock ? $product->stock->stock_qty : 0);
                $normalizedStock = $unitQty > 0 ? round($stockQty / $unitQty, 2) : 0;
                return [
                    'id'            => $product->id,
                    'product_code'  => $product->product_code,
                    'product'       => $product->product,
                    'hsn_code'      => $product->hsn_code,
                    'price'         => $product->price,
                    'mrp'           => $product->mrp,
                    'margin'        => $product->margin,
                    'amt_margin'    => $product->amt_margin,
                    'unit'          => $product->unit,
                    'uqty'          => $product->uqty,
                    'current_stock' => $normalizedStock
                ];
            });
            
            return response()->json($formattedProducts);
        }
        return response()->json([]);
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
            'available_quantity' => (float) $lot->available_quantity,
            'display_quantity' => round((float) $lot->available_quantity / max((float) ($product->uqty ?: 1), 1), 2),
        ]));
    }

    public function storeOrUpdate(Request $request)
    {
        $isUpdate = !empty($request->es_id);  
        $accountEffectEnabled = (bool) (EstimationSetting::values()['affect_customer_accounts'] ?? false);
        if (!$request->filled('es_customer')) {
            $request->merge(['es_customer' => Customer::walkIn()->id]);
        }

        $rules = [
            'es_user'           => 'required|exists:users,id',
            'es_date'           => 'required|date_format:d/m/Y',
            'es_customer'       => 'required|exists:customer,id',
            'es_amount_payable' => 'required',
            'es_round'          => 'required',
            'estimation_items'  => 'required|json',
            'es_vno'            => 'required' . ($isUpdate ? '' : '|unique:estimation,es_vno'),
            // 'voucher_no'        => 'required',
        ];

        if ($accountEffectEnabled) {
            $rules['es_paymode'] = 'required|exists:banking,bk_id';
            $rules['es_amount_paid'] = 'required|numeric|min:0';
            $rules['es_due_days'] = 'nullable|integer|min:0';
        }
        
        $request->validate($rules);
        
        try {
            DB::beginTransaction(); 
        
            $estimationDate = Carbon::createFromFormat('d/m/Y', $request->es_date)->format('Y-m-d');
            $estimationItems = json_decode($request->estimation_items, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid estimation items format.');
            }

            if ($accountEffectEnabled) {
                $useMrpPricingMode = (bool) (EstimationSetting::values()['estimation_mrp_pricing_mode'] ?? false);
                $estimationItems = $this->mrpInventory->applySalePricing($estimationItems, $useMrpPricingMode);
                foreach ($estimationItems as &$item) {
                    $lineAmount = round((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0), 2);
                    $discount = min($lineAmount, round((float) ($item['discount_amount'] ?? 0), 2));
                    $item['total_before_discount'] = $lineAmount;
                    $item['discount_amount'] = $discount;
                    $item['discount_percentage'] = $lineAmount > 0 ? round(($discount / $lineAmount) * 100, 2) : 0;
                    $item['total_after_discount'] = round($lineAmount - $discount, 2);
                }
                unset($item);
            }

            $amount = round((float) collect($estimationItems)->sum('total_before_discount'), 2);
            $discount = round((float) collect($estimationItems)->sum('discount_amount'), 2);
            $amountPayable = round((float) collect($estimationItems)->sum('total_after_discount'));
            $amountPaid = $accountEffectEnabled ? round((float) ($request->es_amount_paid ?? 0), 2) : 0;

            if ($amountPaid > $amountPayable) {
                return redirect()->back()->withInput()->with('error', 'Amount paid cannot exceed amount payable.');
            }

            $balance = round($amountPayable - $amountPaid, 2);
            if ($accountEffectEnabled && $balance > 0 && !$request->filled('es_due_days') && (string) ($request->es_due_days ?? '') !== '0') {
                return redirect()->back()->withInput()->with('error', 'Due days is required when the estimation is not fully paid.');
            }

            $dueDays = $accountEffectEnabled && $balance > 0 ? (int) $request->es_due_days : null;
            $dueDate = $dueDays !== null ? Carbon::parse($estimationDate)->addDays($dueDays)->toDateString() : null;

            $estimationData = [
                'es_date'           => $estimationDate,
                'es_customer'       => $request->es_customer,
                'es_amount'         => $amount,
                'es_discount'       => $discount,
                'es_grandtotal'     => round($amount - $discount, 2),
                'es_amount_payable' => $amountPayable,
                'es_round'          => round($amountPayable - ($amount - $discount), 2),
                'es_account_effect' => $accountEffectEnabled,
                'es_paymode'        => $accountEffectEnabled ? $request->es_paymode : null,
                'es_amount_paid'    => $amountPaid,
                'es_balance'        => $accountEffectEnabled ? $balance : 0,
                'es_due_days'       => $dueDays,
                'es_due_date'       => $dueDate,
                'es_paid'           => $accountEffectEnabled ? ($balance <= 0 ? 'FP' : ($amountPaid > 0 ? 'HP' : 'NP')) : null,
                'es_user'           => $request->es_user,
            ];
            
            if ($isUpdate) {
                $estimation = Estimation::whereKey($request->es_id)->lockForUpdate()->firstOrFail();
                $oldDetails = EstimationDetail::where('esd_sid', $estimation->es_id)->lockForUpdate()->get();
                $this->billingService->removeEffects($estimation, $oldDetails);
                $estimation->update($estimationData);
                $estimation->estimationDetails()->delete();
            } else {
                $estimationData['es_vno']   = $request->es_vno;
                $estimation = Estimation::create($estimationData);
            }
            $estimationID = $estimation->es_id;
            if (!$estimation) {
                throw new \Exception('Failed to save estimation.');
            }
            
            try {
                foreach ($estimationItems as $item) {
                    EstimationDetail::create([
                        'esd_sid'       => $estimationID,
                        'esd_itemid'    => $item['product_id'],
                        'mrp_stock_lot_id' => $item['mrp_stock_lot_id'] ?? null,
                        'esd_itemqty'   => $item['quantity'],
                        'esd_unit'      => $item['unit'],
                        'esd_uprice'    => $item['unit_price'],
                        'esd_uqty'      => $item['unit_qty'],
                        'esd_price'     => $item['total_before_discount'] ?? 0,
                        'esd_adisc'     => $item['discount_amount'] ?? 0,
                        'esd_pdisc'     => $item['discount_percentage'] ?? 0,
                        'esd_total'     => $item['total_after_discount'],
                    ]);
                }  
            } catch (\Exception $e) {
                throw $e;
            }         
            $this->billingService->applyEffects($estimation->fresh(['estimationDetails']));
            DB::commit();
            return redirect()->route('estimations.show', $estimationID)->with('success', 'Estimation saved successfully!');
        } catch (\Exception $e) {
            DB::rollBack(); 
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
    
    public function show($id)
    {
        $estimation = $id ? Estimation::with(['estimationDetails.product','estimationDetails.mrpStockLot','customer','user','banking'])->findOrFail($id) : new Estimation();
        $data['is_cancelled'] = false;
        $data['page_title'] = "View Estimation";
        $data['estimation'] = $estimation;
        return view('estimation::estimations.view',$data);
    }

    public function showCancelled($id)
    {
        $estimation = $id ? Estimation::with(['estimationDetails.product','estimationDetails.mrpStockLot','customer','user','banking'])->findOrFail($id) : new Estimation();
        $data['is_cancelled'] = true;
        $data['page_title'] = "View Cancelled";
        $data['estimation'] = $estimation;
        return view('estimation::estimations.view',$data);
    }

    public function print($id)
    {
        $estimation         = $id ? Estimation::with(['estimationDetails.product','estimationDetails.mrpStockLot','customer','user','banking'])->findOrFail($id) : new Estimation();
        $company            = Company::first() ?? new Company();
        $data['estimation'] = $estimation;
        $data['company']    = $company; 
        $data['printSetting'] = PrintSetting::forDocument('estimation');
        return view('estimation::estimations.print',$data);
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            $estimation     = Estimation::whereKey($id)->lockForUpdate()->firstOrFail();
            $details = EstimationDetail::where('esd_sid', $estimation->es_id)->lockForUpdate()->get();
            $this->billingService->cancelEffects($estimation, $details);
            $estimation->status = '0';
            $estimation->save();
        });
        
        return redirect()->route('estimations.index')->with('success', 'Record deleted successfully');
    }
}