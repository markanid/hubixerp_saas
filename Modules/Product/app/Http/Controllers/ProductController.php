<?php

namespace Modules\Product\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Finance\app\Models\StockValue;
use Modules\Master\app\Models\Brand;
use Modules\Master\app\Models\Category;
use Modules\Master\app\Models\Group;
use Modules\Master\app\Models\Subcategory;
use Modules\Product\app\Models\InventoryLabel;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\Stock;
use Modules\Product\app\Models\StockLedger;
use Modules\Product\app\Services\InventoryLabelService;
use Modules\Product\app\Services\OpeningStockService;
use Modules\Product\app\Services\ProductStockService;
use Modules\Purchase\app\Models\PurchaseDetail;
use Modules\Sale\app\Models\SaleDetail;
use Modules\Service\app\Models\ServiceDetail;
use Modules\Settings\app\Models\BarcodeSetting;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\PrintSetting;
use Picqer\Barcode\BarcodeGeneratorPNG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ProductController extends Controller
{
    public function __construct(
        private readonly InventoryLabelService $inventoryLabels,
        private readonly ProductStockService $productStock,
        private readonly OpeningStockService $openingStock
    ) {}

    public function index($id = null)
    {
        $inventoryMode = $this->productStock->inventoryMode();
        $productQuery = Product::with([
            'stock',
            'purchaseInDetails',
            'saleInDetails',
            'returnInDetails',
            'serviceInDetails',
        ])->withCount('estimationInDetails')
            ->selectSub(
                StockLedger::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('stock_item_id', 'product.product_code'),
                'stock_ledgers_count'
            );

        if ($inventoryMode === 'mrp') {
            $productQuery->withSum('mrpStockLots as tracked_stock_qty', 'available_quantity');
        } elseif ($inventoryMode === 'batch') {
            $productQuery->withSum('stockBatches as tracked_stock_qty', 'available_quantity');
        }

        $products = $productQuery->latest('product_code')->get();
        $products->each(function (Product $product) use ($inventoryMode): void {
            $product->setAttribute('display_stock_qty', $this->productStock->displayQuantity($product, $inventoryMode));
        });
        if ($products != null && ! $products->isEmpty()) {
            $data['products'] = $products;
            $data['page_title'] = 'Products List';
            $data['maskPurchasePrice'] = BarcodeSetting::purchasePriceMaskEnabled();

            return view('product::products.index', $data);
        } else {
            return redirect()->route('products.create');
        }
    }

    public function createOrEdit($id = null)
    {
        $brands = Brand::select('id', 'brand')->get();
        $categories = Category::select('id', 'category')->get();
        $groups = Group::select('id', 'groups')->get();
        $product = $id ? Product::with([
            'stock',
            'stockBatches',
            'mrpStockLots',
            'purchaseInDetails',
            'saleInDetails',
            'returnInDetails',
            'serviceInDetails',
        ])->findOrFail($id) : new Product;
        $inventoryMode = $this->productStock->inventoryMode();
        $stockQty = $id ? $this->productStock->displayQuantity($product, $inventoryMode) : null;
        $isUsed = $id ? $this->productHasTransactions($product) : false;
        $hasTrackedRows = $id && ($product->mrpStockLots->isNotEmpty() || $product->stockBatches->isNotEmpty());
        $legacyStockRaw = $id ? (float) ($product->stock?->stock_qty ?? 0) : 0;
        $usesTrackedInventory = $this->productStock->usesTrackedInventory($product, $inventoryMode);
        if ($id && $usesTrackedInventory && ! $hasTrackedRows && $legacyStockRaw >= 0) {
            $stockQty = round($legacyStockRaw / max((float) ($product->uqty ?: 1), 1), 2);
        }
        $product_code = $id ? $product->product_code : Product::getProductCode();

        $data['page_title'] = $id ? 'Edit Product' : 'Create Product';
        $data['product'] = $product;
        $data['brands'] = $brands;
        $data['categories'] = $categories;
        $data['stockQty'] = $stockQty;
        $data['groups'] = $groups;
        $data['product_code'] = $product_code;
        $data['inventoryMode'] = $inventoryMode;
        $data['batchMode'] = $inventoryMode === 'batch';
        $data['isUsed'] = $isUsed;
        $data['openingStockEditable'] = ! $isUsed && ! $hasTrackedRows && $legacyStockRaw >= 0;
        $data['stockEditable'] = ! $isUsed
            && (! $usesTrackedInventory || $data['openingStockEditable']);
        $data['openingBalanceToAllocate'] = $legacyStockRaw > 0
            ? round($legacyStockRaw / max((float) ($product->uqty ?: 1), 1), 2)
            : null;

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
        $validatedData = $request->validate([
            'product_code' => 'required|string|max:255',
            'product' => 'required|string|max:255',
            'hsn_code' => 'nullable|string|max:255',
            'mrp' => 'nullable|numeric',
            'margin' => 'nullable|numeric',
            'amt_margin' => 'nullable|numeric',
            'price' => 'nullable|numeric',
            'pprice' => 'nullable|numeric',
            'gst' => 'nullable|numeric',
            'unit' => 'nullable|string|max:255',
            'uqty' => 'required|numeric|gt:0',
            'maxquantity' => 'nullable|numeric',
            'minquantity' => 'nullable|numeric',
            'brandid' => 'nullable|exists:brand,id',
            'categoryid' => 'nullable|exists:category,id',
            'subcategoryid' => 'nullable|exists:subcategory,id',
            'groupid' => 'nullable|exists:groups,id',
            'typeid' => 'required|integer',
            'stock_qty' => 'nullable|numeric',
            'multiple_opening_stock' => 'nullable|boolean',
            'opening_batch_no' => 'nullable|string|max:100',
            'opening_expiry_date' => 'nullable|date_format:Y-m-d',
            'opening_stock' => 'nullable|array',
            'opening_stock.*.quantity' => 'nullable|numeric|gt:0',
            'opening_stock.*.batch_no' => 'nullable|string|max:100',
            'opening_stock.*.expiry_date' => 'nullable|date_format:Y-m-d',
            'opening_stock.*.purchase_price' => 'nullable|numeric|min:0',
            'opening_stock.*.mrp' => 'nullable|numeric|gt:0',
            'opening_stock.*.sale_price' => 'nullable|numeric|min:0',
            'is_batch_managed' => 'nullable|boolean',
            'bar_code' => [
                'required',
                'digits:10',
                Rule::unique('product', 'bar_code')->ignore($product->id ?? null),
            ],
            'product_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:30000',
        ]);
        $validatedData['is_batch_managed'] = $request->boolean('is_batch_managed');

        $isNew = empty($request->id);
        $inventoryMode = $this->productStock->inventoryMode();
        $wasTracked = $product
            ? $this->productStock->usesTrackedInventory($product, $inventoryMode)
            : false;
        $hasTransactions = $product ? $this->productHasTransactions($product) : false;

        if ($product && $hasTransactions) {
            $validatedData['unit'] = $product->unit;
            $validatedData['uqty'] = $product->uqty;
            $validatedData['typeid'] = $product->typeid;
            $validatedData['is_batch_managed'] = $product->is_batch_managed;
        }

        $stockWasSubmitted = array_key_exists('stock_qty', $validatedData)
            && $validatedData['stock_qty'] !== null;
        $submittedStock = $stockWasSubmitted ? (float) $validatedData['stock_qty'] : null;
        $multipleOpeningStock = $request->boolean('multiple_opening_stock');
        $batchManaged = (bool) $validatedData['is_batch_managed'];
        $usesTrackedOpening = (int) $validatedData['typeid'] === 2
            && ($inventoryMode === 'mrp' || ($inventoryMode === 'batch' && $batchManaged));
        $openingEntries = [];
        $openingDate = now()->toDateString();
        $openingUsesExistingBalance = false;

        if ($usesTrackedOpening && $stockWasSubmitted && $submittedStock < 0) {
            throw ValidationException::withMessages([
                'stock_qty' => 'Opening stock cannot be negative in tracked inventory.',
            ]);
        }

        if ($usesTrackedOpening && $stockWasSubmitted && $submittedStock > 0) {
            $hasTrackedRows = $product && ($product->mrpStockLots()->exists() || $product->stockBatches()->exists());
            $legacyStockRaw = $product ? (float) ($product->stock()->value('stock_qty') ?? 0) : 0;
            if ($hasTransactions || $hasTrackedRows || $legacyStockRaw < 0) {
                throw ValidationException::withMessages([
                    'stock_qty' => 'Opening stock can only be recorded before any inventory balance or transaction exists.',
                ]);
            }
            $openingUsesExistingBalance = $legacyStockRaw > 0.00001;

            if ($multipleOpeningStock) {
                $openingEntries = collect($validatedData['opening_stock'] ?? [])
                    ->filter(fn (array $entry): bool => ($entry['quantity'] ?? null) !== null && ($entry['quantity'] ?? '') !== '')
                    ->values()
                    ->all();
                if ($openingEntries === []) {
                    throw ValidationException::withMessages([
                        'opening_stock' => 'Add at least one opening-stock allocation row.',
                    ]);
                }
            } else {
                $openingEntries = [[
                    'quantity' => $submittedStock,
                    'batch_no' => $validatedData['opening_batch_no'] ?? null,
                    'expiry_date' => $validatedData['opening_expiry_date'] ?? null,
                    'purchase_price' => $validatedData['pprice'] ?? 0,
                    'mrp' => $validatedData['mrp'] ?? 0,
                    'sale_price' => $validatedData['price'] ?? 0,
                ]];
            }

            $seenBatches = [];
            foreach ($openingEntries as $index => &$entry) {
                $entry['mrp'] = ($entry['mrp'] ?? null) !== null && $entry['mrp'] !== ''
                    ? (float) $entry['mrp']
                    : (float) ($validatedData['mrp'] ?? 0);
                $entry['sale_price'] = ($entry['sale_price'] ?? null) !== null && $entry['sale_price'] !== ''
                    ? (float) $entry['sale_price']
                    : (float) ($validatedData['price'] ?? 0);
                $entry['purchase_price'] = ($entry['purchase_price'] ?? null) !== null && $entry['purchase_price'] !== ''
                    ? (float) $entry['purchase_price']
                    : (float) ($validatedData['pprice'] ?? 0);

                if ($entry['mrp'] <= 0) {
                    throw ValidationException::withMessages([
                        $multipleOpeningStock ? "opening_stock.$index.mrp" : 'mrp' => 'MRP must be greater than zero for opening stock.',
                    ]);
                }
                if ($entry['sale_price'] > $entry['mrp']) {
                    throw ValidationException::withMessages([
                        $multipleOpeningStock ? "opening_stock.$index.sale_price" : 'price' => 'Sale price cannot exceed MRP.',
                    ]);
                }

                if ($inventoryMode === 'batch') {
                    $batchNo = trim((string) ($entry['batch_no'] ?? ''));
                    if ($batchNo === '') {
                        throw ValidationException::withMessages([
                            $multipleOpeningStock ? "opening_stock.$index.batch_no" : 'opening_batch_no' => 'Batch number is required for opening stock.',
                        ]);
                    }
                    $batchKey = mb_strtolower($batchNo);
                    if (isset($seenBatches[$batchKey])) {
                        throw ValidationException::withMessages([
                            "opening_stock.$index.batch_no" => 'Each opening-stock batch number must be unique.',
                        ]);
                    }
                    $seenBatches[$batchKey] = true;
                    $entry['batch_no'] = $batchNo;
                }
            }
            unset($entry);

            $allocatedQuantity = round((float) collect($openingEntries)->sum('quantity'), 2);
            if (abs($allocatedQuantity - $submittedStock) > 0.009) {
                throw ValidationException::withMessages([
                    'opening_stock' => "Opening-stock allocations must total Stock Qty {$submittedStock}.",
                ]);
            }

            if ($openingUsesExistingBalance) {
                $submittedRaw = round($submittedStock * (float) $validatedData['uqty'], 2);
                if (abs($submittedRaw - $legacyStockRaw) > 0.009) {
                    throw ValidationException::withMessages([
                        'stock_qty' => 'Stock Qty must match the existing balance of '
                            .round($legacyStockRaw / (float) $validatedData['uqty'], 2).' '.$validatedData['unit'].'.',
                    ]);
                }
            }
        }

        unset(
            $validatedData['opening_stock'],
            $validatedData['multiple_opening_stock'],
            $validatedData['opening_batch_no'],
            $validatedData['opening_expiry_date']
        );

        if ($request->hasFile('product_image')) {
            if ($product && $product->product_image) {
                Storage::disk('public')->delete('product_logos/'.$product->product_image);
            }
            $file = $request->file('product_image');
            $filename = $request->product_code.'.'.$file->getClientOriginalExtension();
            $file->storeAs('product_logos', $filename, 'public');
            $validatedData['product_image'] = $filename;
        }

        $oldBarcode = $product ? $product->bcode_image : null;
        $oldQrCode = $product?->qrcode_image;
        $oldBarcodeNumber = $product ? (string) $product->bar_code : null;
        $newBarcodeText = trim((string) $request->bar_code);
        $qrContent = $newBarcodeText;
        $replaceBarcode = ! $product || $newBarcodeText !== $oldBarcodeNumber;
        $replaceQrCode = true;

        if ($replaceBarcode) {
            generateBarcodeImage($newBarcodeText, $validatedData);
        }
        if ($replaceQrCode) {
            generateQrCodeImage($qrContent, $validatedData);
        }

        unset($validatedData['stock_qty']);

        $product = DB::transaction(function () use (
            $request,
            $validatedData,
            $inventoryMode,
            $wasTracked,
            $hasTransactions,
            $stockWasSubmitted,
            $submittedStock,
            $isNew,
            $openingEntries,
            $openingDate,
            $openingUsesExistingBalance
        ) {
            $savedProduct = Product::updateOrCreate(
                ['id' => $request->id ?? null],
                $validatedData
            );

            if ($savedProduct && $this->productStock->canApplyDirectAdjustment(
                $savedProduct,
                $inventoryMode,
                $wasTracked,
                $hasTransactions,
                $stockWasSubmitted
            )) {
                $stockQty = (float) $savedProduct->uqty * $submittedStock;
                $stockModel = Stock::firstOrNew(['stock_product_id' => $savedProduct->product_code]);
                $existingQty = $stockModel->exists ? $stockModel->stock_qty : 0;

                if ($stockQty != $existingQty) {
                    $stockModel->stock_qty = $stockQty;
                    $stockModel->stock_date = now()->toDateString();
                    $stockModel->save();

                    $diffQty = $stockQty - $existingQty;
                    if ($diffQty != 0) {
                        StockLedger::updateStockLedger(
                            now()->toDateString(),
                            $savedProduct->product_code,
                            $diffQty,
                            $savedProduct->id,
                            $isNew ? 'INIT' : 'ADJUST'
                        );
                    }
                }
            }

            if ($openingEntries !== []) {
                $this->openingStock->record(
                    $savedProduct,
                    $inventoryMode,
                    $openingEntries,
                    $openingDate,
                    $openingUsesExistingBalance
                );
            }

            return $savedProduct;
        }, 5);

        if ($replaceBarcode && $oldBarcode && $oldBarcode !== $product->bcode_image) {
            Storage::disk('public')->delete("product_logos/barcode_logos/{$oldBarcode}");
        }
        if ($replaceQrCode && $oldQrCode && $oldQrCode !== $product->qrcode_image) {
            Storage::disk('public')->delete("product_logos/qrcode_logos/{$oldQrCode}");
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
        $inventoryMode = $this->productStock->inventoryMode();
        $purchases = PurchaseDetail::getProductPurchaseSummaryBySupplier($product->product_code)->keyBy('pu_vendor');
        $sales_summary = SaleDetail::getProductSaleSummaryByCustomer($product->product_code);
        $services_summary = ServiceDetail::getProductServiceSummaryByCustomer($product->product_code);

        $sales = $sales_summary->keyBy('customer_id');
        $services = $services_summary->keyBy('customer_id');

        $merged_summary = $sales->map(function ($sale, $customerId) use ($services) {
            $service = $services->get($customerId);

            return (object) [
                'customer_id' => $customerId,
                'customer_name' => $sale->customer_name,
                'total_qty' => $sale->sale_count + ($service->service_count ?? 0),
                'total_amount' => $sale->total_amount + ($service->total_amount ?? 0),
            ];
        });

        // Add customers who had only services
        foreach ($services as $customerId => $service) {
            if (! isset($merged_summary[$customerId])) {
                $merged_summary[$customerId] = (object) [
                    'customer_id' => $customerId,
                    'customer_name' => $service->customer_name,
                    'total_qty' => $service->service_count,
                    'total_amount' => $service->total_amount,
                ];
            }
        }

        $data['merged_customer_summary'] = $merged_summary->values();
        $data['total_customer_amount'] = $merged_summary->sum('total_amount');

        $data['page_title'] = 'View Product';
        $data['product'] = $product;
        $data['stockQty'] = $this->productStock->displayQuantity($product, $inventoryMode);
        $data['purchases'] = $purchases;
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

        return view('product::products.view', $data);
    }

    private function productHasTransactions(Product $product): bool
    {
        if (StockLedger::where('stock_item_id', $product->product_code)->exists()) {
            return true;
        }

        foreach ([
            'purchaseInDetails',
            'saleInDetails',
            'returnInDetails',
            'serviceInDetails',
            'estimationInDetails',
        ] as $relation) {
            if ($product->relationLoaded($relation)) {
                if ($product->getRelation($relation)->isNotEmpty()) {
                    return true;
                }

                continue;
            }

            if ($product->{$relation}()->exists()) {
                return true;
            }
        }

        return false;
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

        return $this->productLabelSheetPrintView($products, $codeType);
    }

    public function inventoryBarcode(InventoryLabel $label)
    {
        $generator = new BarcodeGeneratorPNG;
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
        if (! empty($product->product_image) && Storage::disk('public')->exists('product_logos/'.$product->product_image)) {
            Storage::disk('public')->delete('product_logos/'.$product->product_image);
        }
        if (! empty($product->bcode_image) && Storage::disk('public')->exists('product_logos/barcode_logos/'.$product->bcode_image)) {
            Storage::disk('public')->delete('product_logos/barcode_logos/'.$product->bcode_image);
        }
        if (! empty($product->qrcode_image) && Storage::disk('public')->exists('product_logos/qrcode_logos/'.$product->qrcode_image)) {
            Storage::disk('public')->delete('product_logos/qrcode_logos/'.$product->qrcode_image);
        }
        Storage::delete('public/product_logos/'.$product->product_image);
        Storage::delete('public/product_logos/barcode_logos/'.$product->bcode_image);
        Storage::delete('public/product_logos/qrcode_logos/'.$product->qrcode_image);
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

    private function inventoryLabelRowsPrintView($rows, string $codeType, string $backUrl, string $pageTitle, bool $printAgentRender = false)
    {
        $rows = collect($rows)->values();

        if ($printAgentRender) {
            $barcodeGenerator = new BarcodeGeneratorPNG;
            $rows = $rows->map(function (array $row) use ($barcodeGenerator, $codeType) {
                $barcode = (string) $row['label']->barcode;
                if (in_array($codeType, ['barcode', 'both'], true)) {
                    $image = $barcodeGenerator->getBarcode($barcode, $barcodeGenerator::TYPE_CODE_128, 2, 65);
                    $row['barcode_image_src'] = 'data:image/png;base64,'.base64_encode($image);
                }
                if (in_array($codeType, ['qr', 'both'], true)) {
                    $image = QrCode::format('png')->size(220)->margin(1)->generate($barcode);
                    $row['qr_image_src'] = 'data:image/png;base64,'.base64_encode((string) $image);
                }

                return $row;
            });
        }

        return view('product::products.inventory-label-print', [
            'rows' => $rows,
            'codeType' => $codeType,
            'barcodeFieldLabels' => $this->barcodeFieldLabels(),
            'thermalSettings' => BarcodeSetting::activeLabelSettings(),
            'currencySymbol' => Company::query()->value('currency_symbol') ?: 'Rs.',
            'maskPurchasePrice' => BarcodeSetting::purchasePriceMaskEnabled(),
            'backUrl' => $backUrl,
            'page_title' => $pageTitle,
            'printAgentRender' => $printAgentRender,
            'printSetting' => PrintSetting::forDocument('barcode'),
            'printPayload' => [
                'source' => 'inventory_labels',
                'ids' => $rows->pluck('label.id')->map(fn ($id) => (int) $id)->values()->all(),
                'code_type' => $codeType,
                'layout' => 'sheet',
            ],
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
            'thermalSettings' => BarcodeSetting::activeLabelSettings(),
            'currencySymbol' => Company::query()->value('currency_symbol') ?: 'Rs.',
            'maskPurchasePrice' => BarcodeSetting::purchasePriceMaskEnabled(),
            'page_title' => $codeType === 'qr' ? 'Print QR Code' : 'Print Barcode',
            'printSetting' => PrintSetting::forDocument('barcode'),
            'printPayload' => [
                'source' => 'products',
                'ids' => [(int) $product->id],
                'code_type' => $codeType,
                'layout' => 'single',
            ],
        ]);
    }

    public function renderBarcodePrintJob(array $payload)
    {
        $ids = array_values(array_unique(array_map('intval', $payload['ids'] ?? [])));
        $codeType = in_array($payload['code_type'] ?? null, ['barcode', 'qr', 'both'], true)
            ? $payload['code_type']
            : 'barcode';

        if (($payload['source'] ?? null) === 'products') {
            $products = Product::query()->whereIn('id', $ids)->orderBy('product')->get();
            abort_unless($products->count() === count($ids), 404);

            if (($payload['layout'] ?? 'sheet') === 'single' && $products->count() === 1) {
                return $this->productLabelPrintView($products->first(), $codeType);
            }

            return $this->productLabelSheetPrintView($products, $codeType);
        }

        abort_unless(($payload['source'] ?? null) === 'inventory_labels', 404);
        $labelsById = InventoryLabel::query()->with('product')->whereIn('id', $ids)->get()->keyBy('id');
        abort_unless($labelsById->count() === count($ids) && $labelsById->every(fn (InventoryLabel $label) => $label->product !== null), 404);
        $labels = collect($ids)->map(fn (int $id) => $labelsById->get($id));

        $rows = $labels->map(fn (InventoryLabel $label) => $this->inventoryLabels->slotDetails($label, $label->product));

        return $this->inventoryLabelRowsPrintView(
            $rows,
            $codeType,
            route('products.index'),
            $codeType === 'qr' ? 'Print Inventory QR Codes' : ($codeType === 'barcode' ? 'Print Inventory Barcodes' : 'Print Inventory Labels'),
            true
        );
    }

    private function productLabelSheetPrintView($products, string $codeType)
    {
        $products = collect($products)->values();

        return view('product::products.barcode-sheet', [
            'products' => $products,
            'codeType' => $codeType,
            'barcodeFieldLabels' => $this->barcodeFieldLabels(),
            'thermalSettings' => BarcodeSetting::activeLabelSettings(),
            'currencySymbol' => Company::query()->value('currency_symbol') ?: 'Rs.',
            'maskPurchasePrice' => BarcodeSetting::purchasePriceMaskEnabled(),
            'page_title' => $codeType === 'qr' ? 'Print QR Codes' : 'Print Barcodes',
            'printSetting' => PrintSetting::forDocument('barcode'),
            'printPayload' => [
                'source' => 'products',
                'ids' => $products->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'code_type' => $codeType,
                'layout' => 'sheet',
            ],
        ]);
    }

    private function barcodeFieldLabels(): array
    {
        return collect(BarcodeSetting::selectedFields())
            ->mapWithKeys(fn (string $field) => [$field => BarcodeSetting::FIELDS[$field]])
            ->all();
    }
}
