<?php

namespace Modules\Reports\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Product\app\Models\BatchMovement;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\StockBatch;
use Modules\Settings\app\Models\Company;

class BatchInventoryController extends Controller
{
    private const TYPES = [
        'stock' => 'Batch Stock Report',
        'near-expiry' => 'Near Expiry Report',
        'expired' => 'Expired Stock Report',
        'movements' => 'Batch Movement Report',
        'ledger' => 'Batch Ledger',
        'profitability' => 'Batch Profitability Report',
    ];

    public function index(Request $request, string $type = 'stock')
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        abort_unless((Company::query()->value('inventory_mode') ?? 'standard') === 'batch', 404);

        $filters = $request->validate([
            'product_id' => ['nullable', 'exists:product,product_code'],
            'batch_no' => ['nullable', 'string', 'max:100'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        $data = in_array($type, ['movements', 'ledger', 'profitability'], true)
            ? $this->movementReport($type, $filters)
            : $this->stockReport($type, $filters);

        return view('reports::batch.index', [
            'page_title' => self::TYPES[$type],
            'reportType' => $type,
            'rows' => $data,
            'products' => Product::where('is_batch_managed', true)->orderBy('product')->get(['product_code', 'product']),
            'filters' => $filters,
            'expiryAlertDays' => (int) (Company::query()->value('expiry_alert_days') ?? 30),
        ]);
    }

    private function stockReport(string $type, array $filters)
    {
        $query = StockBatch::with('product')->where('available_quantity', '>', 0);
        $this->applyBatchFilters($query, $filters);

        if ($type === 'near-expiry') {
            $days = (int) (Company::query()->value('expiry_alert_days') ?? 30);
            $query->whereBetween('expiry_date', [today(), today()->addDays($days)]);
        } elseif ($type === 'expired') {
            $query->whereDate('expiry_date', '<', today());
        }

        return $query->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->paginate(50)->withQueryString();
    }

    private function movementReport(string $type, array $filters)
    {
        $query = BatchMovement::with(['batch', 'batch.product']);
        if (!empty($filters['product_id'])) $query->where('product_id', $filters['product_id']);
        if (!empty($filters['batch_no'])) {
            $query->whereHas('batch', fn ($q) => $q->where('batch_no', 'like', '%' . $filters['batch_no'] . '%'));
        }
        if (!empty($filters['from_date'])) $query->whereDate('movement_date', '>=', $filters['from_date']);
        if (!empty($filters['to_date'])) $query->whereDate('movement_date', '<=', $filters['to_date']);

        if ($type === 'profitability') {
            return $query->where('movement_type', 'sale')
                ->selectRaw('stock_batch_id, product_id, SUM(quantity_out) quantity_sold, SUM(quantity_out * purchase_rate) cost, SUM(quantity_out * sale_rate) revenue')
                ->groupBy('stock_batch_id', 'product_id')
                ->orderByDesc('revenue')
                ->paginate(50)->withQueryString();
        }

        return $query->orderByDesc('movement_date')->orderByDesc('id')->paginate(50)->withQueryString();
    }

    private function applyBatchFilters($query, array $filters): void
    {
        if (!empty($filters['product_id'])) $query->where('product_id', $filters['product_id']);
        if (!empty($filters['batch_no'])) $query->where('batch_no', 'like', '%' . $filters['batch_no'] . '%');
        if (!empty($filters['from_date'])) $query->whereDate('created_at', '>=', $filters['from_date']);
        if (!empty($filters['to_date'])) $query->whereDate('created_at', '<=', $filters['to_date']);
    }
}
