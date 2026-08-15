<?php

namespace Modules\Product\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Finance\app\Models\StockValue;
use Modules\Master\app\Models\Brand;
use Modules\Master\app\Models\Category;
use Modules\Master\app\Models\Group;
use Modules\Master\app\Models\Subcategory;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\InventoryLabel;
use Modules\Product\app\Models\Stock;
use Modules\Product\app\Models\StockLedger;
use Modules\Purchase\app\Models\PurchaseDetail;
use Modules\Sale\app\Models\SaleDetail;
use Modules\Service\app\Models\ServiceDetail;
use Modules\Product\app\Services\BatchInventoryService;
use Modules\Product\app\Services\InventoryLabelService;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\BarcodeSetting;
use Picqer\Barcode\BarcodeGeneratorPNG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ProductController extends Controller
{
    public function __construct(
        private readonly BatchInventoryService $batchInventory,
        private readonly InventoryLabelService $inventoryLabels
    ) {
    }

    public function index($id = null)
    {
        $products = Product::with(['stock', 'purchaseInDetails', 'saleInDetails', 'returnInDetails', 'serviceInDetails'])->latest('product_code')->get();
        if ($products!=null && !$products->isEmpty()) { 
            $data['products']   = $products;
            $data['page_title'] = "Products List";
            $data['maskPurchasePrice'] = BarcodeSetting::purchasePriceMaskEnabled();
            return view('product::products.index',$data);
        } else {
            return redirect()->route('products.create');
        }       
    }

    public function createOrEdit($id = null)
    {
        $brands = Brand::select('id', 'brand')->get();
        $categories = Category::select('id', 'category')->get();
        $groups = Group::select('id', 'groups')->get();
        $product    =  $id ? Product::with(['stock', 'purchaseInDetails', 'saleInDetails', 'returnInDetails', 'serviceInDetails'])->findOrFail($id) : new Product();
        // $product    = $id ? Product::with('stock')->findOrFail($id) : new Product();
        $stockQty = null;
        if ($product->stock && $product->uqty) {
            $stockQty = $product->stock->stock_qty / $product->uqty;
        }
        $product_code = $id ? $product->product_code : Product::getProductCode();

        $data['page_title']     = $id ? "Edit Product" : "Create Product";
        $data['product']        = $product;
        $data['brands']         = $brands;
        $data['categories']     = $categories;
        $data['stockQty']       = $stockQty;
        $data['groups']         = $groups;  
        $data['product_code']   = $product_code;
        $data['batchMode']      = (Company::query()->value('inventory_mode') ?? 'standard') === 'batch';

        return view('product::products.create', $data);
    }

    public function getSubcategories($category_id)
    {
        $subcategories = Subcategory::where('categoryid', $category_id)->get();
        return response()->json($subcategories);
    }

    public function storeOrUpdate(Request $request)
    {
        $product = Product::find($request->id);
        $validatedData  = $request->validate([
            'product_code'      => 'required|string|max:255',
            'product'           => 'required|string|max:255',
            'hsn_code'          => 'nullable|string|max:255',
            'mrp'               => 'nullable|numeric',
            'margin'            => 'nullable|numeric',
            'amt_margin'        => 'nullable|numeric',
            'price'             => 'nullable|numeric',
            'pprice'            => 'nullable|numeric',
            'gst'               => 'nullable|numeric',
            'unit'              => 'nullable|string|max:255',
            'uqty'              => 'nullable|numeric',
            'maxquantity'       => 'nullable|numeric',
            'minquantity'       => 'nullable|numeric',
            'brandid'           => 'nullable|exists:brand,id',
            'categoryid'        => 'nullable|exists:category,id',
            'subcategoryid'     => 'nullable|exists:subcategory,id',
            'groupid'           => 'nullable|exists:groups,id',
            'typeid'            => 'required|integer',
            'stock_qty'         => 'nullable|numeric',
            'is_batch_managed'  => 'nullable|boolean',
            'bar_code'          => [
                'required',
                'digits:10',
                Rule::unique('product', 'bar_code')->ignore($product->id ?? null),
            ],
            'product_image'     => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:30000',
        ]);
        $validatedData['is_batch_managed'] = $request->boolean('is_batch_managed');
        
        $isNew = empty($request->id);

        if ($request->hasFile('product_image')) {
            if ($product && $product->product_image) {
                Storage::disk('public')->delete('product_logos/' . $product->product_image);
            }
            $file = $request->file('product_image');
            $filename = $request->product_code . '.' . $file->getClientOriginalExtension();
            $file->storeAs('product_logos', $filename, 'public'); 
            $validatedData['product_image'] = $filename; 
        }    

        $oldBarcode         = $product ? $product->bcode_image : null;
        $oldQrCode          = $product?->qrcode_image;
        $oldBarcodeNumber   = $product ? (string) $product->bar_code : null;
        $newBarcodeText     = trim((string) $request->bar_code);
        $qrContent = $newBarcodeText;
        $replaceBarcode = !$product || $newBarcodeText !== $oldBarcodeNumber;
        $replaceQrCode = true;

        if ($replaceBarcode) {
            generateBarcodeImage($newBarcodeText, $validatedData);
        }
        if ($replaceQrCode) {
            generateQrCodeImage($qrContent, $validatedData);
        }

        // Extract stock_qty before updating product
        $uqty       = $validatedData['uqty'] ?? 0;
        $stock      = $validatedData['stock_qty'] ?? 0;
        $stockQty   = $uqty * $stock;
        unset($validatedData['stock_qty']); // Remove from product data

        $product = Product::updateOrCreate(
            ['id' => $request->id ?? null], 
            $validatedData
        );

        if ($replaceBarcode && $oldBarcode && $oldBarcode !== $product->bcode_image) {
            Storage::disk('public')->delete("product_logos/barcode_logos/{$oldBarcode}");
        }
        if ($replaceQrCode && $oldQrCode && $oldQrCode !== $product->qrcode_image) {
            Storage::disk('public')->delete("product_logos/qrcode_logos/{$oldQrCode}");
        }

        if ($product && $product->typeid != 3 && !$this->batchInventory->enabled($product)) {
            $stockModel = Stock::firstOrNew(['stock_product_id' => $product->product_code]);
            $existingQty = $stockModel->exists ? $stockModel->stock_qty : 0;
            
            // Only proceed if quantity changed
            if ($stockQty !== null && $stockQty != $existingQty) {
                $stockModel->stock_qty = $stockQty;
                $stockModel->stock_date = now()->toDateString();
                $stockModel->save();

                // Ledger entry for the difference
                $diffQty = $stockQty - $existingQty;

                if ($diffQty != 0) {
                    $latestLedger = StockLedger::where('stock_item_id', $product->product_code)
                        ->latest('id')
                        ->first();

                    $previousBalance = $latestLedger ? $latestLedger->stock_balance : 0;

                    $ledger = new StockLedger();
                    $ledger->stock_item_id = $product->product_code;
                    $ledger->stock_date = now()->toDateString();
                    $ledger->stock_type = $isNew ? 'INIT' : 'ADJUST'; // different type on edit
                    $ledger->stock_ref_id = $product->id;
                    $ledger->stock_in = $diffQty > 0 ? $diffQty : 0;
                    $ledger->stock_out = $diffQty < 0 ? abs($diffQty) : 0;
                    $ledger->stock_balance = $previousBalance + $diffQty;
                    $ledger->save();
                }
            }
        }
        StockValue::stockClosing();
        
        if ($product) {
            return $isNew
                ? redirect()->route('products.index')->with('success', 'Product created successfully.')
                : redirect()->route('products.show', $product->id)->with('success', 'Product details updated successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to update brand details.');
        }
    }

    public function show($id)
    {
        $product = Product::with(['stock', 'stockBatches', 'mrpStockLots'])->findOrFail($id);
        $inventoryMode = Company::query()->value('inventory_mode') ?? 'standard';
        $purchases  = PurchaseDetail::getProductPurchaseSummaryBySupplier($product->product_code)->keyBy('pu_vendor');
        $sales_summary = SaleDetail::getProductSaleSummaryByCustomer($product->product_code);
        $services_summary = ServiceDetail::getProductServiceSummaryByCustomer($product->product_code);

        $sales = $sales_summary->keyBy('customer_id');
        $services = $services_summary->keyBy('customer_id');
        
        $merged_summary = $sales->map(function ($sale, $customerId) use ($services) {
            $service = $services->get($customerId);

            return (object)[
                'customer_id'   => $customerId,
                'customer_name' => $sale->customer_name,
                'total_qty'     => $sale->sale_count + ($service->service_count ?? 0),
                'total_amount'  => $sale->total_amount + ($service->total_amount ?? 0),
            ];
        });

        // Add customers who had only services
        foreach ($services as $customerId => $service) {
            if (!isset($merged_summary[$customerId])) {
                $merged_summary[$customerId] = (object)[
                    'customer_id'   => $customerId,
                    'customer_name' => $service->customer_name,
                    'total_qty'     => $service->service_count,
                    'total_amount'  => $service->total_amount,
                ];
            }
        }

        $data['merged_customer_summary'] = $merged_summary->values();
        $data['total_customer_amount'] = $merged_summary->sum('total_amount');

        $data['page_title'] = "View Product";
        $data['product']    = $product;
        $rawStock = $inventoryMode === 'mrp'
            ? $product->mrpStockLots->sum('available_quantity')
            : ($inventoryMode === 'batch' && $product->is_batch_managed
                ? $product->stockBatches->sum('available_quantity')
                : ($product->stock?->stock_qty ?? 0));
        $data['stockQty']   = $rawStock / ($product->uqty ?: 1);
        $data['purchases']  = $purchases;
        $data['inventoryMode'] = $inventoryMode;
        $data['inventoryLabels'] = in_array($inventoryMode, ['mrp', 'batch'], true)
            ? $this->inventoryLabels->labelsForProduct($product, $inventoryMode)
            : collect();
        $data['inventoryLabelRows'] = $data['inventoryLabels']
            ->map(fn (InventoryLabel $label) => $this->inventoryLabels->slotDetails($label, $product));
        $data['inventoryTableFieldLabels'] = collect($this->barcodeFieldLabels())
            ->except(['product_code', 'product_name'])
            ->all();
        $data['currencySymbol'] = Company::query()->value('currency_symbol') ?: 'Rs.';

        return view('product::products.view',$data);
    }

    public function printBarcode($id)
    {
        return $this->productLabelPrintView(Product::findOrFail($id), 'barcode');
    }

    public function printQrCode($id)
    {
        return $this->productLabelPrintView(Product::findOrFail($id), 'qr');
    }

    public function printSelectedBarcodes(Request $request)
    {
        return $this->selectedProductLabelPrintView($request, 'barcode');
    }

    public function printSelectedQrCodes(Request $request)
    {
        return $this->selectedProductLabelPrintView($request, 'qr');
    }

    private function selectedProductLabelPrintView(Request $request, string $codeType)
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:product,id'],
        ]);

        $products = Product::whereIn('id', $validated['product_ids'])
            ->orderBy('product')
            ->get();

        $inventoryMode = Company::query()->value('inventory_mode') ?? 'standard';
        if (in_array($inventoryMode, ['mrp', 'batch'], true)) {
            $rows = $products->flatMap(function (Product $product) use ($inventoryMode) {
                return $this->inventoryLabels
                    ->labelsForProduct($product, $inventoryMode)
                    ->map(fn (InventoryLabel $label) => $this->inventoryLabels->slotDetails($label, $product));
            });

            return $this->inventoryLabelRowsPrintView(
                $rows,
                $codeType,
                route('products.index'),
                $codeType === 'qr' ? 'Print Selected Inventory QR Codes' : 'Print Selected Inventory Barcodes'
            );
        }

        return view('product::products.barcode-sheet', [
            'products' => $products,
            'codeType' => $codeType,
            'barcodeFieldLabels' => $this->barcodeFieldLabels(),
            'thermalSettings' => BarcodeSetting::thermalSettings(),
            'currencySymbol' => Company::query()->value('currency_symbol') ?: 'Rs.',
            'maskPurchasePrice' => BarcodeSetting::purchasePriceMaskEnabled(),
            'page_title' => $codeType === 'qr' ? 'Print QR Codes' : 'Print Barcodes',
        ]);
    }

    public function inventoryBarcode(InventoryLabel $label)
    {
        $generator = new BarcodeGeneratorPNG();
        $image = $generator->getBarcode($label->barcode, $generator::TYPE_CODE_128, 2, 65);

        return response($image, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function inventoryQrCode(InventoryLabel $label)
    {
        $image = QrCode::format('png')->size(220)->margin(1)->generate($label->barcode);

        return response($image, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function printInventoryLabel(Product $product, InventoryLabel $label)
    {
        abort_unless($label->product_id === $product->product_code, 404);

        return $this->inventoryLabelPrintView($product, collect([$label]), 'both');
    }

    public function printInventoryBarcode(Product $product, InventoryLabel $label)
    {
        abort_unless($label->product_id === $product->product_code, 404);

        return $this->inventoryLabelPrintView($product, collect([$label]), 'barcode');
    }

    public function printInventoryQrCode(Product $product, InventoryLabel $label)
    {
        abort_unless($label->product_id === $product->product_code, 404);

        return $this->inventoryLabelPrintView($product, collect([$label]), 'qr');
    }

    public function printInventoryLabels(Product $product)
    {
        $inventoryMode = Company::query()->value('inventory_mode') ?? 'standard';
        $labels = $this->inventoryLabels->labelsForProduct($product, $inventoryMode);

        return $this->inventoryLabelPrintView($product, $labels, 'both');
    }

    public function printAllInventoryBarcodes(Product $product)
    {
        return $this->allInventoryLabelPrintView($product, 'barcode');
    }

    public function printAllInventoryQrCodes(Product $product)
    {
        return $this->allInventoryLabelPrintView($product, 'qr');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        if (!empty($product->product_image) && Storage::disk('public')->exists('product_logos/' . $product->product_image)) {
            Storage::disk('public')->delete('product_logos/' . $product->product_image);
        }
        if (!empty($product->bcode_image) && Storage::disk('public')->exists('product_logos/barcode_logos/' . $product->bcode_image)) {
            Storage::disk('public')->delete('product_logos/barcode_logos/' . $product->bcode_image);
        }
        if (!empty($product->qrcode_image) && Storage::disk('public')->exists('product_logos/qrcode_logos/' . $product->qrcode_image)) {
            Storage::disk('public')->delete('product_logos/qrcode_logos/' . $product->qrcode_image);
        }
        Storage::delete('public/product_logos/' . $product->product_image);
        Storage::delete('public/product_logos/barcode_logos/' . $product->bcode_image);
        Storage::delete('public/product_logos/qrcode_logos/' . $product->qrcode_image);
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Record deleted successfully');
    }

    private function inventoryLabelPrintView(Product $product, $labels, string $codeType)
    {
        $labels->each->loadMissing(['mrpStockLot', 'stockBatch']);

        return $this->inventoryLabelRowsPrintView(
            $labels->map(fn (InventoryLabel $label) => $this->inventoryLabels->slotDetails($label, $product)),
            $codeType,
            route('products.show', $product->id),
            $codeType === 'qr' ? 'Print Inventory QR Codes' : ($codeType === 'barcode' ? 'Print Inventory Barcodes' : 'Print Inventory Labels')
        );
    }

    private function inventoryLabelRowsPrintView($rows, string $codeType, string $backUrl, string $pageTitle)
    {
        return view('product::products.inventory-label-print', [
            'rows' => $rows,
            'codeType' => $codeType,
            'barcodeFieldLabels' => $this->barcodeFieldLabels(),
            'thermalSettings' => BarcodeSetting::thermalSettings(),
            'currencySymbol' => Company::query()->value('currency_symbol') ?: 'Rs.',
            'maskPurchasePrice' => BarcodeSetting::purchasePriceMaskEnabled(),
            'backUrl' => $backUrl,
            'page_title' => $pageTitle,
        ]);
    }

    private function allInventoryLabelPrintView(Product $product, string $codeType)
    {
        $inventoryMode = Company::query()->value('inventory_mode') ?? 'standard';
        $labels = $this->inventoryLabels->labelsForProduct($product, $inventoryMode);

        return $this->inventoryLabelPrintView($product, $labels, $codeType);
    }

    private function productLabelPrintView(Product $product, string $codeType)
    {
        return view('product::products.barcode-print', [
            'product' => $product,
            'codeType' => $codeType,
            'barcodeFieldLabels' => $this->barcodeFieldLabels(),
            'thermalSettings' => BarcodeSetting::thermalSettings(),
            'currencySymbol' => Company::query()->value('currency_symbol') ?: 'Rs.',
            'maskPurchasePrice' => BarcodeSetting::purchasePriceMaskEnabled(),
            'page_title' => $codeType === 'qr' ? 'Print QR Code' : 'Print Barcode',
        ]);
    }

    private function barcodeFieldLabels(): array
    {
        return collect(BarcodeSetting::selectedFields())
            ->mapWithKeys(fn (string $field) => [$field => BarcodeSetting::FIELDS[$field]])
            ->all();
    }

}