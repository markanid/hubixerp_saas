<?php

namespace Modules\Reports\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Product\app\Models\MrpStockLot;
use Modules\Product\app\Models\MrpStockMovement;
use Modules\Product\app\Models\Product;
use Modules\Settings\app\Models\Company;

class MrpInventoryController extends Controller
{
    private const TITLES = [
        'stock' => 'MRP-wise Stock Report',
        'movements' => 'MRP Stock Movement Report',
        'profitability' => 'MRP-wise Profitability Report',
    ];

    public function index(Request $request, string $type = 'stock')
    {
        abort_unless((Company::query()->value('inventory_mode') ?? 'standard') === 'mrp', 404);
        abort_unless(array_key_exists($type, self::TITLES), 404);

        $filters = $request->validate([
            'product_id' => ['nullable', 'exists:product,product_code'],
            'mrp' => ['nullable', 'numeric', 'min:0'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        $rows = match ($type) {
            'stock' => $this->stock($filters),
            'movements' => $this->movements($filters),
            'profitability' => $this->profitability($filters),
        };

        return view('reports::mrp.index', [
            'page_title' => self::TITLES[$type],
            'type' => $type,
            'rows' => $rows,
            'filters' => $filters,
            'products' => Product::orderBy('product')->get(['product_code', 'product']),
        ]);
    }

    private function stock(array $filters)
    {
        $query = MrpStockLot::with('product')->where('available_quantity', '>', 0);
        $this->applyLotFilters($query, $filters);

        return $query->orderBy('product_id')->orderBy('purchase_date')->paginate(50)->withQueryString();
    }

    private function movements(array $filters)
    {
        $query = MrpStockMovement::with(['lot', 'lot.product']);
        if (!empty($filters['product_id'])) $query->where('product_id', $filters['product_id']);
        if (isset($filters['mrp']) && $filters['mrp'] !== '') $query->where('mrp', $filters['mrp']);
        if (!empty($filters['from_date'])) $query->whereDate('movement_date', '>=', $filters['from_date']);
        if (!empty($filters['to_date'])) $query->whereDate('movement_date', '<=', $filters['to_date']);

        return $query->latest('movement_date')->latest('id')->paginate(50)->withQueryString();
    }

    private function profitability(array $filters)
    {
        $query = MrpStockMovement::query()
            ->with(['lot', 'lot.product'])
            ->whereIn('movement_type', [
                'sale', 'service', 'estimation',
                'sale_reversal', 'service_reversal', 'estimation_reversal',
                'sale_return', 'sale_return_reversal',
            ])
            ->selectRaw("mrp_stock_lot_id, product_id, mrp,
                SUM(CASE
                    WHEN movement_type IN ('sale', 'service', 'estimation') THEN quantity_out
                    WHEN movement_type IN ('sale_reversal', 'service_reversal', 'estimation_reversal', 'sale_return') THEN -quantity_in
                    WHEN movement_type = 'sale_return_reversal' THEN quantity_out
                    ELSE 0 END) quantity_sold,
                SUM((CASE
                    WHEN movement_type IN ('sale', 'service', 'estimation') THEN quantity_out
                    WHEN movement_type IN ('sale_reversal', 'service_reversal', 'estimation_reversal', 'sale_return') THEN -quantity_in
                    WHEN movement_type = 'sale_return_reversal' THEN quantity_out
                    ELSE 0 END) * purchase_rate) cost,
                SUM((CASE
                    WHEN movement_type IN ('sale', 'service', 'estimation') THEN quantity_out
                    WHEN movement_type IN ('sale_reversal', 'service_reversal', 'estimation_reversal', 'sale_return') THEN -quantity_in
                    WHEN movement_type = 'sale_return_reversal' THEN quantity_out
                    ELSE 0 END) * sale_rate) revenue")
            ->groupBy('mrp_stock_lot_id', 'product_id', 'mrp');

        if (!empty($filters['product_id'])) $query->where('product_id', $filters['product_id']);
        if (isset($filters['mrp']) && $filters['mrp'] !== '') $query->where('mrp', $filters['mrp']);
        if (!empty($filters['from_date'])) $query->whereDate('movement_date', '>=', $filters['from_date']);
        if (!empty($filters['to_date'])) $query->whereDate('movement_date', '<=', $filters['to_date']);

        return $query->paginate(50)->withQueryString();
    }

    private function applyLotFilters($query, array $filters): void
    {
        if (!empty($filters['product_id'])) $query->where('product_id', $filters['product_id']);
        if (isset($filters['mrp']) && $filters['mrp'] !== '') $query->where('mrp', $filters['mrp']);
        if (!empty($filters['from_date'])) $query->whereDate('purchase_date', '>=', $filters['from_date']);
        if (!empty($filters['to_date'])) $query->whereDate('purchase_date', '<=', $filters['to_date']);
    }
}
