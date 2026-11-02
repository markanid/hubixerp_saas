<?php

namespace Modules\Product\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Product\app\Models\StockLedger;

class ProductStockLedgerReport
{
    private const TRANSACTIONS = [
        'INIT' => 'Opening stock',
        'OPENING' => 'Opening stock',
        'ADJUST' => 'Stock adjustment',
        'p' => 'Purchase',
        'pd' => 'Purchase reversal',
        's' => 'Sale',
        'sd' => 'Sale reversal',
        'sv' => 'Service sale',
        'svd' => 'Service sale reversal',
        'sr' => 'Sales return',
        'srd' => 'Sales return reversal',
        'pr' => 'Purchase return',
        'prd' => 'Purchase return reversal',
        'c' => 'Consumption',
        'cd' => 'Consumption reversal',
        'es' => 'Estimation',
        'esd' => 'Estimation reversal',
    ];

    private const SOURCES = [
        ['types' => ['p', 'pd'], 'table' => 'purchase', 'id' => 'pu_id', 'voucher' => 'pu_vno', 'status' => 'pu_status', 'route' => 'purchases'],
        ['types' => ['s', 'sd'], 'table' => 'sales', 'id' => 'sa_id', 'voucher' => 'sa_vno', 'status' => 'status', 'route' => 'sales'],
        ['types' => ['sv', 'svd'], 'table' => 'services', 'id' => 'sv_id', 'voucher' => 'sv_vno', 'status' => 'status', 'route' => 'services'],
        ['types' => ['sr', 'srd'], 'table' => 'preturn', 'id' => 'pr_id', 'voucher' => 'pr_vno', 'status' => 'status', 'route' => 'sales-returns', 'return_type' => '2'],
        ['types' => ['pr', 'prd'], 'table' => 'preturn', 'id' => 'pr_id', 'voucher' => 'pr_vno', 'status' => 'status', 'route' => 'purchase-returns', 'return_type' => '1'],
        ['types' => ['c', 'cd'], 'table' => 'consume', 'id' => 'con_id', 'voucher' => 'con_vno', 'status' => 'status', 'route' => 'consumptions'],
        ['types' => ['es', 'esd'], 'table' => 'estimation', 'id' => 'es_id', 'voucher' => 'es_vno', 'status' => 'status', 'route' => 'estimations'],
        ['types' => ['INIT', 'OPENING', 'ADJUST'], 'table' => 'product', 'id' => 'id', 'voucher' => 'product_code', 'status' => 'status', 'route' => 'products'],
    ];

    public function forProduct(string $productCode, string $from, string $to): array
    {
        return DB::transaction(function () use ($productCode, $from, $to): array {
            $query = StockLedger::query()->where('stock_item_id', $productCode);

            // Carry all prior years forward; stored running balances are derived data.
            $opening = (int) round((float) (clone $query)
                ->where('stock_date', '<', $from)
                ->selectRaw('COALESCE(SUM(stock_in - stock_out), 0) AS quantity')
                ->value('quantity') * 100);

            $balance = $opening;
            $totalIn = 0;
            $totalOut = 0;
            $entries = (clone $query)
                ->whereBetween('stock_date', [$from, $to])
                ->orderBy('stock_date')
                ->orderBy('id')
                ->get(['id', 'stock_date', 'stock_type', 'stock_ref_id', 'stock_in', 'stock_out']);
            $references = $this->voucherReferences($entries);
            $rows = $entries->map(function (StockLedger $entry) use (&$balance, &$totalIn, &$totalOut, $references): array {
                    // Quantities are stored to two decimal places; accumulate in hundredths.
                    $in = (int) round((float) $entry->stock_in * 100);
                    $out = (int) round((float) $entry->stock_out * 100);
                    $before = $balance;
                    $balance += $in - $out;
                    $totalIn += $in;
                    $totalOut += $out;

                    return [
                        'id' => $entry->id,
                        'date' => $entry->stock_date,
                        'transaction' => self::TRANSACTIONS[$entry->stock_type] ?? $entry->stock_type,
                        'reference' => $entry->stock_ref_id,
                        'voucher' => $references[$entry->id]['voucher']
                            ?? ($entry->stock_ref_id !== null ? '#'.$entry->stock_ref_id.' (unavailable)' : '—'),
                        'voucher_url' => $references[$entry->id]['url'] ?? null,
                        'opening' => $before / 100,
                        'in' => $in / 100,
                        'out' => $out / 100,
                        'closing' => $balance / 100,
                    ];
                });

            return [
                'from' => $from,
                'to' => $to,
                'opening' => $opening / 100,
                'total_in' => $totalIn / 100,
                'total_out' => $totalOut / 100,
                'closing' => $balance / 100,
                'rows' => $rows,
            ];
        });
    }

    private function voucherReferences(Collection $entries): array
    {
        $references = [];

        foreach (self::SOURCES as $source) {
            $movements = $entries->whereIn('stock_type', $source['types']);
            $ids = $movements->pluck('stock_ref_id')->filter(fn ($id) => $id !== null)->unique();
            if ($ids->isEmpty()) {
                continue;
            }

            // Fetch each source in one batch, including cancelled documents.
            $query = DB::table($source['table'])->whereIn($source['id'], $ids);
            if (isset($source['return_type'])) {
                $query->where('pr_type', $source['return_type']);
            }
            $documents = $query->get([$source['id'], $source['voucher'], $source['status']])
                ->keyBy($source['id']);

            foreach ($movements as $movement) {
                $document = $documents->get($movement->stock_ref_id);
                if (! $document) {
                    continue;
                }

                $cancelled = (string) $document->{$source['status']} === '0' && $source['route'] !== 'products';
                $references[$movement->id] = [
                    'voucher' => $document->{$source['voucher']} ?: '#'.$movement->stock_ref_id,
                    'url' => route($source['route'].($cancelled ? '.showCancelled' : '.show'), $movement->stock_ref_id),
                ];
            }
        }

        return $references;
    }
}
