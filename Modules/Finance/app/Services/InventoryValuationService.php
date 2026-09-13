<?php

namespace Modules\Finance\app\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryValuationService
{
    public function inventoryMode(): string
    {
        $mode = (string) (DB::table('company')->value('inventory_mode') ?? 'standard');

        return in_array($mode, ['standard', 'mrp', 'batch'], true) ? $mode : 'standard';
    }

    /**
     * @return array{purchase_value: float, sale_value: float, mrp_value: float}
     */
    public function totals(?string $inventoryMode = null): array
    {
        $inventoryMode ??= $this->inventoryMode();

        $totals = match ($inventoryMode) {
            'mrp' => $this->trackedTotals('mrp_stock_lots', 'l'),
            'batch' => $this->addTotals(
                $this->trackedTotals('stock_batches', 'b', true),
                $this->standardTotals(true)
            ),
            default => $this->standardTotals(),
        };

        return array_map(static fn (float $value): float => round($value, 2), $totals);
    }

    public function mrpValue(?string $inventoryMode = null): float
    {
        return $this->totals($inventoryMode)['mrp_value'];
    }

    /**
     * @return array{purchase_value: float, sale_value: float, mrp_value: float}
     */
    private function standardTotals(bool $onlyNonBatchManaged = false): array
    {
        $unitQuantity = 'COALESCE(NULLIF(p.uqty, 0), 1)';
        $query = DB::table('stock as s')
            ->join('product as p', 's.stock_product_id', '=', 'p.product_code')
            ->where('s.stock_qty', '>', 0);

        if ($onlyNonBatchManaged) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('p.is_batch_managed')
                    ->orWhere('p.is_batch_managed', false);
            });
        }

        $row = $query->selectRaw(
            "COALESCE(SUM(COALESCE(p.pprice, 0) * s.stock_qty / {$unitQuantity}), 0) AS purchase_value, "
            . "COALESCE(SUM(COALESCE(p.price, 0) * s.stock_qty / {$unitQuantity}), 0) AS sale_value, "
            . "COALESCE(SUM(COALESCE(p.mrp, 0) * s.stock_qty / {$unitQuantity}), 0) AS mrp_value"
        )->first();

        return $this->rowToTotals($row);
    }

    /**
     * @return array{purchase_value: float, sale_value: float, mrp_value: float}
     */
    private function trackedTotals(string $table, string $alias, bool $onlyBatchManaged = false): array
    {
        if (!Schema::hasTable($table)) {
            return $this->zeroTotals();
        }

        $unitQuantity = 'COALESCE(NULLIF(p.uqty, 0), 1)';
        $salePrice = Schema::hasColumn($table, 'sale_price')
            ? "COALESCE({$alias}.sale_price, p.price, 0)"
            : 'COALESCE(p.price, 0)';
        $mrp = "COALESCE(NULLIF({$alias}.mrp, 0), p.mrp, 0)";

        $query = DB::table("{$table} as {$alias}")
            ->join('product as p', "{$alias}.product_id", '=', 'p.product_code')
            ->where("{$alias}.available_quantity", '>', 0);

        if ($onlyBatchManaged) {
            $query->where('p.is_batch_managed', true);
        }

        $row = $query->selectRaw(
            "COALESCE(SUM(COALESCE({$alias}.purchase_rate, 0) * {$alias}.available_quantity), 0) AS purchase_value, "
            . "COALESCE(SUM({$salePrice} * {$alias}.available_quantity / {$unitQuantity}), 0) AS sale_value, "
            . "COALESCE(SUM({$mrp} * {$alias}.available_quantity / {$unitQuantity}), 0) AS mrp_value"
        )->first();

        return $this->rowToTotals($row);
    }

    /**
     * @return array{purchase_value: float, sale_value: float, mrp_value: float}
     */
    private function rowToTotals(?object $row): array
    {
        return [
            'purchase_value' => (float) ($row->purchase_value ?? 0),
            'sale_value' => (float) ($row->sale_value ?? 0),
            'mrp_value' => (float) ($row->mrp_value ?? 0),
        ];
    }

    /**
     * @param array{purchase_value: float, sale_value: float, mrp_value: float} $first
     * @param array{purchase_value: float, sale_value: float, mrp_value: float} $second
     * @return array{purchase_value: float, sale_value: float, mrp_value: float}
     */
    private function addTotals(array $first, array $second): array
    {
        return [
            'purchase_value' => $first['purchase_value'] + $second['purchase_value'],
            'sale_value' => $first['sale_value'] + $second['sale_value'],
            'mrp_value' => $first['mrp_value'] + $second['mrp_value'],
        ];
    }

    /**
     * @return array{purchase_value: float, sale_value: float, mrp_value: float}
     */
    private function zeroTotals(): array
    {
        return ['purchase_value' => 0.0, 'sale_value' => 0.0, 'mrp_value' => 0.0];
    }
}
