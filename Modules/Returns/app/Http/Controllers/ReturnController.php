<?php

namespace Modules\Returns\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Contacts\app\Models\Customer;
use Modules\Contacts\app\Models\Vendor;
use Modules\Finance\app\Models\Balance;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\DailyBank;
use Modules\Finance\app\Models\LedgerBook;
use Modules\Finance\app\Models\StockValue;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\Stock;
use Modules\Product\app\Models\StockLedger;
use Modules\Purchase\app\Models\Purchase;
use Modules\Purchase\app\Models\PurchaseDetail;
use Modules\Returns\app\Http\Requests\StorePurchaseReturnRequest;
use Modules\Returns\app\Http\Requests\StoreSaleReturnRequest;
use Modules\Returns\app\Models\ReturnDetail;
use Modules\Returns\app\Models\Returns;
use Modules\Returns\app\Services\PurchaseReturnService;
use Modules\Returns\app\Services\SaleReturnService;
use Modules\Product\app\Models\StockBatch;
use Modules\Product\app\Services\BatchInventoryService;
use Modules\Product\app\Services\MrpInventoryService;
use Modules\Sale\app\Models\Sale;
use Modules\Sale\app\Models\SaleDetail;
use Modules\Sale\app\Models\GstStateCode;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\PrintSetting;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReturnController extends Controller
{
    public function __construct(
        private readonly BatchInventoryService $batchInventory,
        private readonly MrpInventoryService $mrpInventory,
        private readonly PurchaseReturnService $purchaseReturnService,
        private readonly SaleReturnService $saleReturnService
    )
    {
    }
    public function pindex(Request $request)
    {
        $financialYear = session('financial_year') ?: Returns::getFinancialYear(now());
        $query = Returns::with('vendor')->where('status', '1')->where('pr_type', '1')->where('financial_year', $financialYear)->select('pr_id', 'pr_date', 'pr_vno', 'pr_pvno', 'pr_vendor', 'pr_amount_payable', 'pr_paid'); 
        
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
        $query->whereMonth('pr_date', Carbon::now()->month)
              ->whereYear('pr_date', Carbon::now()->year);
        } else {
            if ($request->filled('voucher')) {
                $query->where('pr_vno', 'like', '%' . $request->voucher . '%');
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
                $query->whereBetween('pr_date', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('pr_date', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('pr_date', '<=', $toDate);
            }
        }
    
        $returns = $query->latest('pr_id')->get();
        
        return view('returns::preturn.index', [
                'returns'           => $returns,
                'page_title'        => 'Purchase-Returns List',
                'is_cancelled'      => false,
                'search_voucher'    => $request->voucher,
                'from_date'         => $fromDate,
                'to_date'           => $toDate,
                'search_vendor'     => $request->vendor,
                'search_phone'      => $request->phone,
        ]);
    }

    public function pcancelled(Request $request)
    {
        $financialYear = session('financial_year') ?: Returns::getFinancialYear(now());
        $query = Returns::with('vendor')
            ->where('status', '0')
            ->where('pr_type', '1')
            ->where('financial_year', $financialYear)
            ->select('pr_id', 'pr_date', 'pr_vno', 'pr_pvno', 'pr_vendor', 'pr_amount_payable', 'pr_paid');

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

        $isSearching = $request->filled('voucher') || $fromDate || $toDate || $request->filled('vendor') || $request->filled('phone');

        if (!$isSearching) {
            $query->whereMonth('pr_date', Carbon::now()->month)
                ->whereYear('pr_date', Carbon::now()->year);
        } else {
            if ($request->filled('voucher')) {
                $query->where('pr_vno', 'like', '%' . $request->voucher . '%');
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
                $query->whereBetween('pr_date', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('pr_date', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('pr_date', '<=', $toDate);
            }
        }

        $returns = $query->latest('pr_id')->get();

        return view('returns::preturn.index', [
            'returns' => $returns,
            'page_title' => 'Cancelled Purchase-Returns',
            'is_cancelled' => true,
            'search_voucher' => $request->voucher,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'search_vendor' => $request->vendor,
            'search_phone' => $request->phone,
        ]);
    }

    public function sindex(Request $request)
    {
        $financialYear = session('financial_year');
        $query = Returns::with('customer')->where('status', '1')->where('pr_type', '2')->where('financial_year', $financialYear)->select('pr_id', 'pr_date', 'pr_vno', 'pr_pvno', 'pr_vendor', 'pr_amount_payable', 'pr_paid'); 
        
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
        $query->whereMonth('pr_date', Carbon::now()->month)
              ->whereYear('pr_date', Carbon::now()->year);
        } else {
            if ($request->filled('voucher')) {
                $query->where('pr_vno', 'like', '%' . $request->voucher . '%');
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
                $query->whereBetween('pr_date', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('pr_date', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('pr_date', '<=', $toDate);
            }
        }
    
        $returns = $query->latest('pr_id')->get();
        
        return view('returns::sreturn.index', [
                'returns'           => $returns,
                'page_title'        => 'Sale-Returns List',
                'is_cancelled'      => false,
                'search_voucher'    => $request->voucher,
                'from_date'         => $fromDate,
                'to_date'           => $toDate,
                'search_customer'   => $request->customer,
                'search_phone'      => $request->phone,
        ]);
    }

    public function scancelled(Request $request)
    {
        $financialYear = session('financial_year');
        $query = Returns::with('customer')
            ->where('status', '0')
            ->where('pr_type', '2')
            ->where('financial_year', $financialYear)
            ->select('pr_id', 'pr_date', 'pr_vno', 'pr_pvno', 'pr_vendor', 'pr_amount_payable', 'pr_paid');

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

        $isSearching = $request->filled('voucher') || $fromDate || $toDate || $request->filled('customer') || $request->filled('phone');

        if (!$isSearching) {
            $query->whereMonth('pr_date', Carbon::now()->month)
                ->whereYear('pr_date', Carbon::now()->year);
        } else {
            if ($request->filled('voucher')) {
                $query->where('pr_vno', 'like', '%' . $request->voucher . '%');
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
                $query->whereBetween('pr_date', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('pr_date', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('pr_date', '<=', $toDate);
            }
        }

        $returns = $query->latest('pr_id')->get();

        return view('returns::sreturn.index', [
            'returns' => $returns,
            'page_title' => 'Cancelled Sale-Returns',
            'is_cancelled' => true,
            'search_voucher' => $request->voucher,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'search_customer' => $request->customer,
            'search_phone' => $request->phone,
        ]);
    }

    public function pcreateOrEdit($id = null)
    {
        $banks      = Banking::all();
        $return     = null;
        if ($id) {
            $return = Returns::with(['returnDetails.product','returnDetails.mrpStockLot','vendor','user','banking'])
                ->where('pr_type', '1')
                ->where('status', '1')
                ->findOrFail($id);
            $voucher_no = $return->pr_vno;
            $page_title = "Edit";
        } else {
            $voucher_no = Returns::getVoucherCode(1);
            $page_title = "Create";
        }

        $inventoryMode = Company::query()->value('inventory_mode') ?? 'standard';
        $data = [
            'page_title'=> $page_title,
            'voucher_no'=> $voucher_no,
            'banks'     => $banks,
            'return'    => $return,
            'inventoryMode' => $inventoryMode,
            'batchMode' => $inventoryMode === 'batch',
            'mrpMode' => $inventoryMode === 'mrp',
        ];
        return view('returns::preturn.create', $data);
    }

    public function screateOrEdit($id = null)
    {
        $banks      = Banking::all();
        $company    = Company::first();
        $taxType    = strtolower((string) ($company?->tax_type ?? 'gst'));
        $return     = null;
        if ($id) {
            $return = Returns::with(['returnDetails.product','returnDetails.mrpStockLot','customer','user','banking'])
                ->where('pr_type', '2')
                ->where('status', '1')
                ->findOrFail($id);
            $voucher_no = $return->pr_vno;
            $page_title = "Edit";
        } else {
            $voucher_no = Returns::getVoucherCode(2);
            $page_title = "Create";
        }

        $inventoryMode = $company?->inventory_mode ?? 'standard';
        $data = [
            'page_title'=> $page_title,
            'voucher_no'=> $voucher_no,
            'banks'     => $banks,
            'return'    => $return,
            'inventoryMode' => $inventoryMode,
            'batchMode' => $inventoryMode === 'batch',
            'mrpMode' => $inventoryMode === 'mrp',
            'taxType'   => $taxType,
            'taxLabel'  => $taxType === 'vat' ? 'VAT' : 'GST',
            'states'    => GstStateCode::orderBy('state_name')->get(),
        ];
        return view('returns::sreturn.create', $data);
    }

    public function pRetSearch(Request $request)
    {
        $query = $request->input('query');
        $searchBy = $request->input('searchBy');

        if ($searchBy === 'vendor') {
            $vendors = Vendor::where('cp_name', 'LIKE', "%{$query}%")
                         ->orWhere('cp_phone', 'LIKE', "%{$query}%")
                         ->orWhere('cp_cphone', 'LIKE', "%{$query}%")
                         ->get(['id', 'cp_name', 'cp_phone']);
            return response()->json($vendors);
        }

        if ($searchBy === 'purchase') {
            $purchase = Purchase::with(['vendor', 'purchaseDetails.product', 'purchaseDetails.mrpStockLot'])
                ->where('pu_status', '1')
                ->where(function ($q) use ($query) {
                    $q->where('pu_vno', $query)
                        ->orWhere('pu_bill_number', $query);
                })
                ->first();

            if (!$purchase) {
                return response()->json(null, 404);
            }

            $returnedRaw = ReturnDetail::whereHas('return', function ($q) use ($purchase) {
                    $q->where('pr_type', 1)
                        ->where('status', '1')
                        ->where(function ($query) use ($purchase) {
                            $query->where('pr_pvno', $purchase->pu_vno);

                            if ($purchase->pu_bill_number) {
                                $query->orWhere('pr_pvno', $purchase->pu_bill_number);
                            }
                        });
                })
                ->get()
                ->groupBy('prd_itemid')
                ->map(function ($rows) {
                    return $rows->sum(function ($detail) {
                        return in_array($detail->prd_unit, ['No.s', 'Nos.'], true)
                            ? (float) $detail->prd_itemqty
                            : (float) $detail->prd_itemqty * max((float) $detail->prd_uqty, 1);
                    });
                })
                ->all();

            $items = $purchase->purchaseDetails->map(function ($detail) use (&$returnedRaw) {
                    $rawPurchased = in_array($detail->pud_unit, ['No.s', 'Nos.'], true)
                        ? (float) $detail->pud_itemqty
                        : (float) $detail->pud_itemqty * max((float) $detail->pud_uqty, 1);
                    $rawReturned = min($rawPurchased, (float) ($returnedRaw[$detail->pud_itemid] ?? 0));
                    $returnedRaw[$detail->pud_itemid] = max(0, (float) ($returnedRaw[$detail->pud_itemid] ?? 0) - $rawReturned);
                    $rawRemaining = max(0, $rawPurchased - $rawReturned);

                    if ($rawRemaining <= 0) {
                        return null;
                    }

                    $quantity = in_array($detail->pud_unit, ['No.s', 'Nos.'], true)
                        ? $rawRemaining
                        : round($rawRemaining / max((float) $detail->pud_uqty, 1), 2);

                    return [
                        'product_id' => $detail->pud_itemid,
                        'product' => $detail->product?->product,
                        'hsn_code' => $detail->pud_hsn,
                        'quantity' => $quantity,
                        'unit' => $detail->pud_unit,
                        'unit_price' => (float) $detail->pud_uprice,
                        'unit_qty' => (float) $detail->pud_uqty,
                        'gst' => (float) ($detail->product?->gst ?? 0),
                        'stock_batch_id' => null,
                        'batch_no' => $detail->batch_no,
                        'mrp_stock_lot_id' => $detail->mrpStockLot?->id,
                        'stock_mrp' => (float) ($detail->mrpStockLot?->mrp ?? $detail->batch_mrp ?? 0),
                        'mrp_lot_label' => $detail->mrpStockLot
                            ? trim(($detail->mrpStockLot->purchase_voucher ?: 'Purchase') . ' | MRP ' . number_format((float) $detail->mrpStockLot->mrp, 2))
                            : null,
                    ];
                })
                ->filter()
                ->values();

            return response()->json([
                'id' => $purchase->pu_id,
                'voucher' => $purchase->pu_vno,
                'bill_number' => $purchase->pu_bill_number,
                'vendor' => [
                    'id' => $purchase->vendor?->id,
                    'cp_name' => $purchase->vendor?->cp_name,
                    'cp_phone' => $purchase->vendor?->cp_phone,
                ],
                'items' => $items,
            ]);
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
                    'pprice'        => $product->pprice,
                    'unit'          => $product->unit,
                    'uqty'          => $product->uqty,
                    'gst'           => $product->gst,
                    'current_stock' => $normalizedStock,
                    'is_batch_managed' => (bool) $product->is_batch_managed,
                    'batches' => $this->batchInventory->enabled($product)
                        ? $this->batchInventory->availableBatches($product->product_code)->map(fn ($batch) => [
                            'id' => $batch->id,
                            'batch_no' => $batch->batch_no,
                            'expiry_date' => $batch->expiry_date?->format('d/m/Y'),
                            'available_quantity' => round((float) $batch->available_quantity / max((float) ($product->uqty ?: 1), 1), 2),
                        ])->values()
                        : [],
                    'mrp_lots' => $this->mrpInventory->enabled()
                        ? $this->mrpInventory->availableLots($product->product_code)->map(fn ($lot) => [
                            'id' => $lot->id,
                            'purchase_voucher' => $lot->purchase_voucher,
                            'purchase_date' => $lot->purchase_date?->format('d/m/Y'),
                            'purchase_rate' => (float) $lot->purchase_rate,
                            'display_purchase_rate' => round((float) $lot->purchase_rate * max((float) ($product->uqty ?: 1), 1), 2),
                            'mrp' => (float) $lot->mrp,
                            'available_quantity' => round((float) $lot->available_quantity / max((float) ($product->uqty ?: 1), 1), 2),
                        ])->values()
                        : [],
                ];
            });    
            return response()->json($formattedProducts);
        }
        return response()->json([]);
    }

    public function sRetSearch(Request $request)
    {
        $query = $request->input('query');
        $searchBy = $request->input('searchBy');

        if ($searchBy === 'customer') {
            $customers = Customer::where('customer', 'LIKE', "%{$query}%")
                ->orWhere('phone', 'LIKE', "%{$query}%")
                ->get(['id', 'customer', 'phone']);

            return response()->json($customers);
        } 

        if ($searchBy === 'sale') {
            $mrpMode = $this->mrpInventory->enabled();
            $sale = Sale::with(['customer', 'saleDetails.product', 'saleDetails.mrpStockLot'])
                ->where('sa_vno', $query)
                ->where('status', '1')
                ->first();

            if (!$sale) {
                return response()->json(null, 404);
            }

            $returnedRaw = ReturnDetail::whereHas('return', function ($q) use ($sale) {
                    $q->where('pr_type', 2)
                        ->where('status', '1')
                        ->where('pr_pvno', $sale->sa_vno);
                })
                ->get()
                ->groupBy(fn ($detail) => $mrpMode
                    ? $detail->prd_itemid . ':' . ($detail->mrp_stock_lot_id ?? '')
                    : $detail->prd_itemid)
                ->map(function ($rows) {
                    return $rows->sum(function ($detail) {
                        return in_array($detail->prd_unit, ['No.s', 'Nos.'], true)
                            ? (float) $detail->prd_itemqty
                            : (float) $detail->prd_itemqty * max((float) $detail->prd_uqty, 1);
                    });
                })
                ->all();

            $items = $sale->saleDetails->map(function ($detail) use (&$returnedRaw, $mrpMode) {
                    $rawSold = in_array($detail->sad_unit, ['No.s', 'Nos.'], true)
                        ? (float) $detail->sad_itemqty
                        : (float) $detail->sad_itemqty * max((float) $detail->sad_uqty, 1);
                    $returnKey = $mrpMode
                        ? $detail->sad_itemid . ':' . ($detail->mrp_stock_lot_id ?? '')
                        : $detail->sad_itemid;
                    $rawReturned = min($rawSold, (float) ($returnedRaw[$returnKey] ?? 0));
                    $returnedRaw[$returnKey] = max(0, (float) ($returnedRaw[$returnKey] ?? 0) - $rawReturned);
                    $rawRemaining = max(0, $rawSold - $rawReturned);

                    if ($rawRemaining <= 0) {
                        return null;
                    }

                    $quantity = in_array($detail->sad_unit, ['No.s', 'Nos.'], true)
                        ? $rawRemaining
                        : round($rawRemaining / max((float) $detail->sad_uqty, 1), 2);

                    return [
                        'product_id' => $detail->sad_itemid,
                        'product' => $detail->product?->product,
                        'hsn_code' => $detail->sad_hsn,
                        'quantity' => $quantity,
                        'unit' => $detail->sad_unit,
                        'unit_price' => (float) $detail->sad_uprice,
                        'unit_qty' => (float) $detail->sad_uqty,
                        'gst' => (float) ($detail->product?->gst ?? 0),
                        'mrp_stock_lot_id' => $detail->mrp_stock_lot_id,
                        'stock_mrp' => (float) ($detail->stock_mrp ?? $detail->mrpStockLot?->mrp ?? $detail->product?->mrp ?? 0),
                        'mrp_lot_label' => $detail->mrpStockLot
                            ? trim(($detail->mrpStockLot->purchase_voucher ?: 'Purchase') . ' | MRP ' . number_format((float) $detail->mrpStockLot->mrp, 2))
                            : null,
                    ];
                })
                ->filter()
                ->values();

            return response()->json([
                'id' => $sale->sa_id,
                'voucher' => $sale->sa_vno,
                'customer' => [
                    'id' => $sale->customer?->id,
                    'customer' => $sale->customer?->customer,
                    'phone' => $sale->customer?->phone,
                ],
                'state_code' => $sale->sa_state_code,
                'is_igst' => (bool) $sale->sa_is_igst,
                'items' => $items,
            ]);
        }
        
        if ($searchBy === 'product') { 
             $products = Product::where('typeid', '!=', 3)
                ->where(function ($q) use ($query) {
                    $q->where('product_code', 'LIKE', "%{$query}%")
                    ->orWhere('product', 'LIKE', "%{$query}%");
                })
                ->get(['id', 'product_code', 'product', 'hsn_code', 'price', 'mrp', 'unit', 'uqty', 'gst', 'is_batch_managed']);
            return response()->json($products);
        }
        return response()->json([]);
    }

    public function pstoreOrUpdate(StorePurchaseReturnRequest $request)
    {
        try {
            $return = $this->purchaseReturnService->save($request->validated());

            return redirect()->route('purchase-returns.show', $return->pr_id)
                ->with('success', 'Purchase-Return saved successfully!');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->back()
                ->withInput()
                ->with('error', 'The purchase-return could not be saved. No changes were committed.');
        }
    } 

    public function sstoreOrUpdate(StoreSaleReturnRequest $request)
    {
        try {
            $return = $this->saleReturnService->save($request->validated());

            return redirect()->route('sales-returns.show', $return->pr_id)
                ->with('success', 'Sale-Return saved successfully!');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->back()
                ->withInput()
                ->with('error', 'The sale-return could not be saved. No changes were committed.');
        }
    } 
    
    public function pshow($id)
    {
        $return = $id ? Returns::with(['returnDetails.product','returnDetails.mrpStockLot','vendor','user','banking'])
            ->where('pr_type', '1')
            ->where('status', '1')
            ->findOrFail($id) : new Returns();
        $taxType = strtolower((string) (Company::query()->value('tax_type') ?? 'gst'));
        $data['is_cancelled']   = false;
        $data['page_title']     = "View";
        $data['return']         = $return;
        $data['taxType']        = $taxType;
        $data['taxLabel']       = $taxType === 'vat' ? 'VAT' : 'GST';
        return view('returns::preturn.view',$data);
    }

    public function showPCancelled($id)
    {
        $return = $id ? Returns::with(['returnDetails.product','returnDetails.mrpStockLot','vendor','user','banking'])
            ->where('pr_type', '1')
            ->where('status', '0')
            ->findOrFail($id) : new Returns();
        $taxType = strtolower((string) (Company::query()->value('tax_type') ?? 'gst'));
        $data['is_cancelled']   = true;
        $data['page_title']     = "View Cancelled";
        $data['return']         = $return;
        $data['taxType']        = $taxType;
        $data['taxLabel']       = $taxType === 'vat' ? 'VAT' : 'GST';
        return view('returns::preturn.view',$data);
    }

    public function pprint($id)
    {
        $return = Returns::with(['returnDetails.product','returnDetails.mrpStockLot','vendor','user','banking'])
            ->where('pr_type', '1')
            ->findOrFail($id);
        $company = Company::first() ?? new Company();
        $openingRow = LedgerBook::where('lb_vid', $return->pr_id)
            ->where('lb_payee', $return->pr_vendor)
            ->whereIn('lb_type', ['pr', 'prp'])
            ->orderByRaw("CASE WHEN lb_tramount > 0 THEN 0 ELSE 1 END")
            ->orderBy('lb_id', 'asc')
            ->first();
        $taxType = strtolower((string) ($company?->tax_type ?? 'gst'));

        return view('returns::preturn.print', [
            'return' => $return,
            'company' => $company,
            'taxType' => $taxType,
            'taxLabel' => $taxType === 'vat' ? 'VAT' : 'GST',
            'vendorOpening' => $openingRow?->lb_opbalance ?? $return->vendor?->op_balance ?? 0,
            'vendorClosing' => $openingRow?->lb_clbalance ?? 0,
            'printSetting' => PrintSetting::forDocument('purchase_return'),
        ]);
    }
    
    public function sshow($id)
    {
        $return = $id ? Returns::with(['returnDetails.product','returnDetails.mrpStockLot','customer','user','banking'])
            ->where('pr_type', '2')
            ->where('status', '1')
            ->findOrFail($id) : new Returns();
        $taxType = strtolower((string) (Company::query()->value('tax_type') ?? 'gst'));
        $data['is_cancelled']   = false;
        $data['page_title']     = "View";
        $data['return']         = $return;
        $data['taxType']        = $taxType;
        $data['taxLabel']       = $taxType === 'vat' ? 'VAT' : 'GST';
        $data['states']         = GstStateCode::orderBy('state_name')->get();
        return view('returns::sreturn.view',$data);
    }

    public function showSCancelled($id)
    {
        $return = $id ? Returns::with(['returnDetails.product','returnDetails.mrpStockLot','customer','user','banking'])
            ->where('pr_type', '2')
            ->where('status', '0')
            ->findOrFail($id) : new Returns();
        $taxType = strtolower((string) (Company::query()->value('tax_type') ?? 'gst'));
        $data['is_cancelled']   = true;
        $data['page_title']     = "View Cancelled";
        $data['return']         = $return;
        $data['taxType']        = $taxType;
        $data['taxLabel']       = $taxType === 'vat' ? 'VAT' : 'GST';
        $data['states']         = GstStateCode::orderBy('state_name')->get();
        return view('returns::sreturn.view',$data);
    }

    public function sprint($id)
    {
        $return = Returns::with(['returnDetails.product','returnDetails.mrpStockLot','customer','user','banking'])
            ->where('pr_type', '2')
            ->findOrFail($id);
        $company = Company::first() ?? new Company();
        $openingRow = LedgerBook::where('lb_vid', $return->pr_id)
            ->where('lb_payee', $return->pr_vendor)
            ->whereIn('lb_type', ['sr', 'srp'])
            ->orderByRaw("CASE WHEN lb_tramount > 0 THEN 0 ELSE 1 END")
            ->orderBy('lb_id', 'asc')
            ->first();
        $taxType = strtolower((string) ($company?->tax_type ?? 'gst'));

        return view('returns::sreturn.print', [
            'return' => $return,
            'company' => $company,
            'taxType' => $taxType,
            'taxLabel' => $taxType === 'vat' ? 'VAT' : 'GST',
            'customerOpening' => $openingRow?->lb_opbalance ?? $return->customer?->op_balance ?? 0,
            'customerClosing' => $openingRow?->lb_clbalance ?? 0,
            'states' => GstStateCode::orderBy('state_name')->get(),
            'printSetting' => PrintSetting::forDocument('sale_return'),
        ]);
    }

    public function pdestroy($id)
    {
        $this->purchaseReturnService->cancel(Returns::findOrFail($id));
        
        return redirect()->route('purchase-returns.index')->with('success', 'Purchase-Return cancelled successfully.');
    }

    public function sdestroy($id)
    {
        $this->saleReturnService->cancel(Returns::findOrFail($id));
        
        return redirect()->route('sales-returns.index')->with('success', 'Sale-Return cancelled successfully.');
    }
}