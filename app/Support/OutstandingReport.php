<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class OutstandingReport
{
    public static function rows(bool $supplier, string $start, string $asOf): array
    {
        $sources = $supplier ? ['p' => 'Purchase', 'loan' => 'Loan']
            : ['s' => 'Sale', 'sv' => 'Service', 'es' => 'Estimation', 'loan_asset' => 'Loan'];
        $types = $supplier ? ['p', 'pr', 'pp', 'prp', 'loan']
            : ['s', 'sr', 'sv', 'es', 'sp', 'srp', 'svp', 'esp', 'loan_asset'];
        $table = $supplier ? 'vendor' : 'customer';
        $name = $supplier ? 'cp_name' : 'customer';

        // Read the authoritative closing balance, ordered by date then ID for backdated entries.
        $latest = DB::table('ledgerbook as ledger')->select('ledger.lb_clbalance')
            ->whereColumn('ledger.lb_payee', 'party.id')->whereIn('ledger.lb_type', $types)
            ->where('ledger.lb_date', '<=', $asOf)
            ->where(function ($q) { $q->where('ledger.status', '1')->orWhereNull('ledger.status'); })
            ->orderByDesc('ledger.lb_date')->orderByDesc('ledger.lb_id')->limit(1);
        $query = DB::table($table . ' as party')
            ->select('party.id', 'party.' . $name . ' as party', 'party.op_balance')
            ->selectSub($latest, 'ledger_balance');
        $parties = DB::query()->fromSub($query, 'balances')
            // Retain opening-only debt only when no ledger closing exists.
            ->selectRaw('id, party, COALESCE(ledger_balance, op_balance, 0) as balance')
            ->whereRaw('COALESCE(ledger_balance, op_balance, 0) > 0')->orderBy('party')->get();

        // Particular describes activity, not a calculated split of the closing balance.
        $activity = DB::table('ledgerbook')->select('lb_payee', 'lb_type')
            ->whereIn('lb_payee', $parties->pluck('id'))->whereIn('lb_type', array_keys($sources))
            ->whereBetween('lb_date', [$start, $asOf])->where('lb_tramount', '>', 0)
            ->where(function ($q) { $q->where('status', '1')->orWhereNull('status'); })
            ->groupBy('lb_payee', 'lb_type')->get()->groupBy('lb_payee');

        return $parties->map(function ($party) use ($activity, $sources, $supplier) {
            $present = $activity->get($party->id, collect())->pluck('lb_type')->all();
            $labels = array_values(array_intersect_key($sources, array_flip($present)));
            return [
                'party' => $party->party,
                'particular' => $labels ? implode(' / ', $labels) : 'Opening Balance',
                'balance' => round((float) $party->balance, 2),
                '_actions' => [[
                    'url' => route($supplier ? 'vendors.show' : 'customers.show', $party->id),
                    'label' => 'View', 'class' => 'btn-info', 'icon' => 'fas fa-eye',
                ]],
            ];
        })->all();
    }
}
