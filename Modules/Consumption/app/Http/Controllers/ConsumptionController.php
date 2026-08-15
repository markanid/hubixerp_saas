<?php

namespace Modules\Consumption\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Consumption\app\Models\Consumption;
use Modules\Consumption\app\Models\ConsumptionDetail;
use Modules\Finance\app\Models\DailyBank;
use Modules\Finance\app\Models\StockValue;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\Stock;
use Modules\Product\app\Models\StockLedger;
use Modules\Product\app\Services\BatchInventoryService;
use Modules\Product\app\Services\MrpInventoryService;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\PrintSetting;

class ConsumptionController extends Controller
{
    public function __construct(
        private readonly BatchInventoryService $batchInventory,
        private readonly MrpInventoryService $mrpInventory
    )
    {
    }

    public function index(Request $request)
    {
        $financialYear = session('financial_year');
        $query = Consumption::with('user')
            ->where('status', '1')
            ->where('financial_year', $financialYear)
            ->select('con_id', 'con_date', 'con_vno', 'con_amount', 'con_user');
        
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
        $isSearching = $request->filled('voucher') || $fromDate || $toDate;

        if (!$isSearching) {
        $query->whereMonth('con_date', Carbon::now()->month)
              ->whereYear('con_date', Carbon::now()->year);
        } else {
            if ($request->filled('voucher')) {
                $query->where('con_vno', 'like', '%' . $request->voucher . '%');
            }
    
            if ($fromDate && $toDate) {
                $query->whereBetween('con_date', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('con_date', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('con_date', '<=', $toDate);
            }
        }
    
        $consumptions = $query->latest('con_id')->get();
        
        return view('consumption::consumptions.index', [
                'consumptions' => $consumptions,
                'page_title' => 'Consumptions List',
                'is_cancelled' => false,
                'search_voucher' => $request->voucher,
                'from_date' => $fromDate,
                'to_date' => $toDate,
        ]);
    }

    public function cancelled(Request $request)
    {
        $financialYear = session('financial_year');
        $query = Consumption::with('user')
            ->where('status', '0')
            ->where('financial_year', $financialYear)
            ->select('con_id', 'con_date', 'con_vno', 'con_amount', 'con_user');

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

        $isSearching = $request->filled('voucher') || $fromDate || $toDate;

        if (!$isSearching) {
            $query->whereMonth('con_date', Carbon::now()->month)
                ->whereYear('con_date', Carbon::now()->year);
        } else {
            if ($request->filled('voucher')) {
                $query->where('con_vno', 'like', '%' . $request->voucher . '%');
            }

            if ($fromDate && $toDate) {
                $query->whereBetween('con_date', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('con_date', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('con_date', '<=', $toDate);
            }
        }

        return view('consumption::consumptions.index', [
            'consumptions' => $query->latest('con_id')->get(),
            'page_title' => 'Cancelled Consumptions',
            'is_cancelled' => true,
            'search_voucher' => $request->voucher,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);
    }

    public function createOrEdit($id = null)
    {
        $consumption   = null;
        $company = Company::first();
        if ($id) {
            $consumption = Consumption::with(['consumptionDetails.product', 'consumptionDetails.batchMovements.batch', 'consumptionDetails.mrpStockLot', 'user'])->findOrFail($id);
            $voucher_no = $consumption->con_vno;
            $page_title = "Edit Consumption";
        } else {
            $voucher_no = Consumption::getVoucherCode();
            $page_title = "Create Consumption";
        }

        $inventoryMode = $company?->inventory_mode ?? 'standard';
        $data = [
            'page_title'    => $page_title,
            'voucher_no'    => $voucher_no,
            'consumption'   => $consumption,
            'inventoryMode' => $inventoryMode,
            'batchMode'     => $inventoryMode === 'batch',
            'mrpMode'       => $inventoryMode === 'mrp',
        ];
        return view('consumption::consumptions.create', $data);
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        $searchBy = $request->input('searchBy');
        
        if ($searchBy === 'product') { 
            $products = Product::with(['stock', 'stockBatches', 'mrpStockLots'])
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
                    : ($product->is_batch_managed && $this->batchInventory->enabled($product)
                        ? $product->stockBatches->sum('available_quantity')
                        : ($product->stock ? $product->stock->stock_qty : 0));
                $normalizedStock = $unitQty > 0 ? round($stockQty / $unitQty, 2) : 0;
                return [
                    'id'            => $product->id,
                    'product_code'  => $product->product_code,
                    'product'       => $product->product,
                    'hsn_code'      => $product->hsn_code,
                    'price'         => $product->pprice,
                    'unit'          => $product->unit,
                    'uqty'          => $product->uqty,
                    'current_stock' => $normalizedStock,
                    'is_batch_managed' => (bool) $product->is_batch_managed,
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

    public function storeOrUpdate(Request $request)
    {
        $isUpdate = !empty($request->con_id);  
        $rules = [
            'con_user'          => 'nullable|exists:users,id',
            'con_date'          => 'required|date_format:d/m/Y',
            'con_amount'        => 'required',
            'consumption_items' => 'required|json',
            'con_vno'           => 'required' . ($isUpdate ? '' : '|unique:consume,con_vno'),
            // 'voucher_no'        => 'required',
        ];
        
        $request->validate($rules);
        
        try {
            DB::beginTransaction(); 
        
            $consumptionDate = Carbon::createFromFormat('d/m/Y', $request->con_date)->format('Y-m-d');
            $consumptionItems = json_decode($request->consumption_items, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return redirect()->back()->with('error', 'Invalid consumption items format.');
            }
            $consumptionItems = $this->normaliseItems($consumptionItems);

            $consumptionData = [
                'con_date'           => $consumptionDate,
                'financial_year'     => session('financial_year'),
                'con_amount'         => $request->con_amount ?? 0,
                'con_user'           => Auth::id() ?: $request->con_user,
            ];
            
            if ($isUpdate) {
                $consumption = Consumption::whereKey($request->con_id)->lockForUpdate()->firstOrFail();
                $oldDate = $consumption->con_date;
                $consumption->update($consumptionData);

                $consumption_Items = ConsumptionDetail::where('cond_cid', $request->con_id)->lockForUpdate()->get();
                $this->reverseStock($consumption, $consumption_Items, $oldDate);
                $this->batchInventory->reverseReference('consumption', (int) $request->con_id, $oldDate);
                $this->mrpInventory->reverseReference('consumption', (int) $request->con_id, $oldDate);
                $consumption->consumptionDetails()->delete();
                 
            } else {
                $consumptionData['con_vno']    = $request->con_vno;
                $consumption = Consumption::create($consumptionData);
            }
            $consumptionID = $consumption->con_id;
            if (!$consumption) {
                throw new \Exception('Failed to save consumption.');
            }
            
            $this->createDetailsAndApplyStock($consumption, $consumptionItems, $consumptionDate);
            StockValue::stockClosing();
            DB::commit();
            return redirect()->route('consumptions.show', $consumption->con_id)->with('success', 'Consumption saved successfully!');
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack(); 
            report($e);
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        $consumption = $id ? Consumption::with(['consumptionDetails.product', 'consumptionDetails.batchMovements.batch', 'consumptionDetails.mrpStockLot', 'user'])->findOrFail($id) : new Consumption();
        $data['is_cancelled']   = false;
        $data['page_title']     = "View Consumption";
        $data['consumption']    = $consumption;
        return view('consumption::consumptions.view',$data);
    }

    public function showCancelled($id)
    {
        $consumption = $id ? Consumption::with(['consumptionDetails.product', 'consumptionDetails.batchMovements.batch', 'consumptionDetails.mrpStockLot', 'user'])->findOrFail($id) : new Consumption();
        $data['is_cancelled']   = true;
        $data['page_title']     = "View Cancelled";
        $data['consumption']    = $consumption;
        return view('consumption::consumptions.view',$data);
    }

    public function print($id)
    {
        $consumption = Consumption::with(['consumptionDetails.product', 'consumptionDetails.batchMovements.batch', 'consumptionDetails.mrpStockLot', 'user'])->findOrFail($id);

        return view('consumption::consumptions.print', [
            'consumption' => $consumption,
            'company' => Company::first() ?? new Company(),
            'printSetting' => PrintSetting::forDocument('consumption'),
        ]);
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            $consumption       = Consumption::whereKey($id)->lockForUpdate()->firstOrFail();
            $consumptionId     = $consumption->con_id;
            $consumptionDate   = $consumption->con_date;

            $consumptionItems = ConsumptionDetail::where('cond_cid', $consumptionId)->lockForUpdate()->get();
            $this->reverseStock($consumption, $consumptionItems, $consumptionDate);
            $this->batchInventory->reverseReference('consumption', (int) $consumptionId, $consumptionDate);
            $this->mrpInventory->reverseReference('consumption', (int) $consumptionId, $consumptionDate);

            $consumption->status = '0';
            $consumption->save();
            StockValue::stockClosing();
        });
        
        return redirect()->route('consumptions.index')->with('success', 'Record deleted successfully');
    }

    private function normaliseItems(array $items): array
    {
        return collect($items)->map(function (array $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitQty = (float) ($item['unit_qty'] ?? 1);
            $unitQty = $unitQty > 0 ? $unitQty : 1;
            $unit = (string) ($item['unit'] ?? '');
            $rawQuantity = $this->rawQuantity($quantity, $unit, $unitQty);

            return [
                'product_id' => $item['product_id'] ?? null,
                'hsn_code' => $item['hsn_code'] ?? null,
                'quantity' => $quantity,
                'unit' => $unit,
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'unit_qty' => $unitQty,
                'final_total' => (float) ($item['final_total'] ?? 0),
                'remark' => $item['remark'] ?? '',
                'stock_batch_id' => $item['stock_batch_id'] ?? null,
                'mrp_stock_lot_id' => $item['mrp_stock_lot_id'] ?? null,
                'raw_quantity' => $rawQuantity,
            ];
        })->all();
    }

    private function createDetailsAndApplyStock(Consumption $consumption, array $items, string $consumptionDate): void
    {
        foreach ($items as $item) {
            $detail = ConsumptionDetail::create([
                'cond_cid'      => $consumption->con_id,
                'cond_itemid'   => $item['product_id'],
                'stock_batch_id'=> $item['stock_batch_id'] ?? null,
                'mrp_stock_lot_id'=> $item['mrp_stock_lot_id'] ?? null,
                'cond_hsn'      => $item['hsn_code'],
                'cond_qty'      => $item['quantity'],
                'cond_unit'     => $item['unit'],
                'cond_uprice'   => $item['unit_price'],
                'cond_uqty'     => $item['unit_qty'],
                'cond_total'    => $item['final_total'],
                'cond_remark'   => $item['remark'],
            ]);

            $product = Product::where('product_code', $item['product_id'])->firstOrFail();
            $allocations = $this->batchInventory->allocateSale(
                $product,
                (float) $item['raw_quantity'],
                (int) $consumption->con_id,
                (int) $detail->cond_id,
                $consumptionDate,
                $item['stock_batch_id'] ?? null,
                in_array($item['unit'], ['No.s', 'Nos.'], true)
                    ? (float) $item['unit_price']
                    : (float) $item['unit_price'] / max((float) $item['unit_qty'], 1),
                referenceType: 'consumption',
                movementType: 'consumption'
            );

            if ($allocations->count() === 1) {
                $batch = $allocations->first()->batch;
                $detail->update([
                    'stock_batch_id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'expiry_date' => $batch->expiry_date,
                ]);
            }

            $mrpAllocations = $this->mrpInventory->allocateSale(
                $product,
                (float) $item['raw_quantity'],
                (int) $consumption->con_id,
                (int) $detail->cond_id,
                $consumptionDate,
                $item['mrp_stock_lot_id'] ?? null,
                in_array($item['unit'], ['No.s', 'Nos.'], true)
                    ? (float) $item['unit_price']
                    : (float) $item['unit_price'] / max((float) $item['unit_qty'], 1),
                referenceType: 'consumption',
                movementType: 'consumption'
            );
            if ($mrpAllocations->count() === 1) {
                $lot = $mrpAllocations->first()->lot;
                $detail->update(['mrp_stock_lot_id' => $lot->id, 'stock_mrp' => $lot->mrp]);
            }

            Stock::updateStock($consumptionDate, $item['product_id'], -$item['raw_quantity']);
            StockLedger::updateStockLedger($consumptionDate, $item['product_id'], -$item['raw_quantity'], $consumption->con_id, 'c');
        }
    }

    private function reverseStock(Consumption $consumption, $items, string $originalDate): void
    {
        foreach ($items as $item) {
            $quantity = $this->rawQuantity((float) $item->cond_qty, (string) $item->cond_unit, (float) $item->cond_uqty);
            Stock::updateStock($originalDate, $item->cond_itemid, $quantity);
            StockLedger::updateStockLedger($originalDate, $item->cond_itemid, $quantity, $consumption->con_id, 'cd');
        }
    }

    private function rawQuantity(float $quantity, string $unit, float $unitQty): float
    {
        return in_array($unit, ['No.s', 'Nos.'], true)
            ? $quantity
            : $quantity * max($unitQty, 1);
    }
}