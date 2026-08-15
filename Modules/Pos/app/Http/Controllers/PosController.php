<?php

namespace Modules\Pos\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\Contacts\app\Models\Customer;
use Modules\Finance\app\Models\Banking;
use Modules\Pos\app\Http\Requests\StorePosSaleRequest;
use Modules\Pos\app\Models\PosHeldBill;
use Modules\Pos\app\Models\PosSession;
use Modules\Pos\app\Services\PosSaleService;
use Modules\Pos\app\Services\StockService;
use Modules\Sale\app\Models\GstStateCode;
use Modules\Sale\app\Models\Sale;
use Modules\Sale\app\Models\SaleSetting;
use Modules\Settings\app\Models\Company;

class PosController extends Controller
{
    public function __construct(
        private readonly PosSaleService $posSaleService,
        private readonly StockService $stockService
    ) {
    }

    public function index()
    {
        $session = PosSession::where('user_id', Auth::id())->where('status', 'open')->latest('id')->first();
        $walkInCustomer = Customer::walkIn();
        $taxProfile = Company::taxProfile();

        return view('pos::pos.index', [
            'page_title' => 'Point of Sale',
            'session' => $session,
            'banks' => Banking::where('bk_status', '1')->orderBy('bk_bank')->get(),
            'customers' => Customer::where('status', '1')
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$walkInCustomer->id])
                ->orderBy('customer')
                ->limit(50)
                ->get(),
            'walkInCustomerId' => $walkInCustomer->id,
            'company' => Company::first(),
            'taxProfile' => $taxProfile,
            'allowedSaleTypes' => $taxProfile['is_composition'] ? ['1'] : ['1', '2'],
            'states' => GstStateCode::orderBy('state_name')->get(),
            'financialYear' => session('financial_year') ?: Sale::getFinancialYear(now()),
            'allowOutOfStockSale' => (bool) (SaleSetting::values()['allow_out_of_stock_sale'] ?? false),
            'useMrpPricingMode' => (bool) (SaleSetting::values()['mrp_pricing_mode'] ?? false),
        ]);
    }

    public function products(Request $request)
    {
        $validated = $request->validate(['q' => ['required', 'string', 'max:100']]);

        return response()->json(['products' => $this->stockService->search($validated['q'])->values()]);
    }

    public function customer(Request $request)
    {
        $validated = $request->validate([
            'customer' => ['required', 'string', 'max:255', 'unique:customer,customer'],
            'phone' => ['required', 'string', 'max:20', 'unique:customer,phone'],
        ]);

        $customer = Customer::create($validated);

        return response()->json([
            'id' => $customer->id,
            'customer' => $customer->customer,
            'phone' => $customer->phone,
        ], 201);
    }

    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'sa_type' => ['required', Rule::in($this->allowedSaleTypes())],
            'sale_items' => ['required', 'array', 'min:1'],
            'sale_items.*.product_id' => ['required', 'exists:product,product_code'],
            'sale_items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'sale_items.*.unit' => ['required', 'string', 'max:10'],
            'sale_items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'sale_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'sale_items.*.stock_batch_id' => ['nullable', 'integer', 'exists:stock_batches,id'],
            'sale_items.*.mrp_stock_lot_id' => ['nullable', 'integer', 'exists:mrp_stock_lots,id'],
        ]);

        return response()->json($this->posSaleService->calculate($validated['sale_items'], $validated['sa_type']));
    }

    public function store(StorePosSaleRequest $request)
    {
        $session = PosSession::whereKey($request->validated('pos_session_id'))
            ->where('user_id', Auth::id())
            ->where('status', 'open')
            ->firstOrFail();

        $sale = $this->posSaleService->create($request->validated(), $session);

        return response()->json([
            'message' => 'Sale saved successfully.',
            'sale' => [
                'id' => $sale->sa_id,
                'invoice_no' => $sale->sa_vno,
                'amount_payable' => (float) $sale->sa_amount_payable,
                'receipt_url' => route('pos.receipt', $sale->sa_id),
            ],
        ], 201);
    }

    public function receipt(Sale $sale)
    {
        $sale->load(['saleDetails.product', 'customer', 'banking']);

        return view('pos::pos.receipt', [
            'sale' => $sale,
            'company' => Company::first(),
            'payments' => $sale->posPayments ?? collect(),
        ]);
    }

    public function hold(Request $request)
    {
        $validated = $request->validate([
            'pos_session_id' => ['required', 'integer', 'exists:pos_sessions,id'],
            'sa_customer' => ['nullable', 'exists:customer,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'sa_type' => ['required', Rule::in($this->allowedSaleTypes())],
            'sale_items' => ['required', 'array', 'min:1'],
            'sale_items.*.product_id' => ['required', 'exists:product,product_code'],
            'sale_items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'sale_items.*.unit' => ['required', 'string', 'max:10'],
            'sale_items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'sale_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'sale_items.*.stock_batch_id' => ['nullable', 'integer', 'exists:stock_batches,id'],
            'sale_items.*.mrp_stock_lot_id' => ['nullable', 'integer', 'exists:mrp_stock_lots,id'],
            'payments' => ['nullable', 'array'],
        ]);

        $session = PosSession::whereKey($validated['pos_session_id'])
            ->where('user_id', Auth::id())
            ->where('status', 'open')
            ->firstOrFail();

        $hold = $this->posSaleService->hold($validated, $session);

        return response()->json(['hold' => $hold], 201);
    }

    public function holds(Request $request)
    {
        $sessionId = $request->integer('pos_session_id');
        $holds = PosHeldBill::where('user_id', Auth::id())
            ->when($sessionId, fn ($query) => $query->where('pos_session_id', $sessionId))
            ->latest()
            ->limit(30)
            ->get();

        return response()->json(['holds' => $holds]);
    }

    public function recall(PosHeldBill $hold)
    {
        abort_unless((int) $hold->user_id === (int) Auth::id(), 403);

        $savedItems = collect($hold->cart['sale_items'] ?? []);
        $products = $this->stockService->details($savedItems->pluck('product_id')->filter()->all());
        $items = $savedItems->map(function (array $savedItem) use ($products) {
            $product = $products->get($savedItem['product_id'] ?? '');
            if (!$product) {
                return null;
            }

            $mrpLotId = $savedItem['mrp_stock_lot_id'] ?? null;
            $selectedMrpLot = collect($product['mrp_lots'] ?? [])->first(
                fn (array $lot) => (string) $lot['id'] === (string) $mrpLotId
            );

            return array_merge($product, [
                'product_id' => $product['product_code'],
                'name' => $product['product'],
                'quantity' => (float) ($savedItem['quantity'] ?? 0),
                'unit' => $savedItem['unit'] ?? $product['unit'],
                'unit_price' => (float) ($savedItem['unit_price'] ?? $product['price']),
                'discount_amount' => (float) ($savedItem['discount_amount'] ?? 0),
                'stock_batch_id' => $savedItem['stock_batch_id'] ?? null,
                'mrp_stock_lot_id' => $mrpLotId,
                'stock_mrp' => $selectedMrpLot ? (float) $selectedMrpLot['mrp'] : null,
                'raw_stock' => $selectedMrpLot ? (float) $selectedMrpLot['available_quantity'] : (float) $product['raw_stock'],
                'current_stock' => $selectedMrpLot ? (float) $selectedMrpLot['display_quantity'] : (float) $product['current_stock'],
            ]);
        })->filter()->values();

        abort_if($items->isEmpty(), 422, 'The held bill has no available products to recall.');

        return response()->json(['hold' => $hold, 'items' => $items]);
    }

    public function destroyHold(PosHeldBill $hold)
    {
        abort_unless((int) $hold->user_id === (int) Auth::id(), 403);
        $hold->delete();

        return response()->noContent();
    }

    private function allowedSaleTypes(): array
    {
        return Company::taxProfile()['is_composition'] ? ['1'] : ['1', '2'];
    }
}