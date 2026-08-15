<?php

namespace Modules\Finance\app\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\DailyBank;
use Modules\Finance\app\Models\LedgerBook;

class DailyBankService
{
    private const INFLOW_TYPES = ['sp', 'svp', 'esp', 'prp', 'loan'];

    private const OUTFLOW_TYPES = ['pp', 'srp', 'exp', 'loan_asset'];

    /**
     * Propagate a signed bank movement through every existing snapshot from
     * the transaction date forward. If a legacy date has no snapshot, rebuild
     * that history from active ledgers instead of guessing its opening value.
     */
    public function applySignedDelta(
        int $bankId,
        string $transactionDate,
        float $signedAmount,
        ?float $currentBalance = null
    ): void {
        $signedAmount = round($signedAmount, 2);
        $effectiveDate = Carbon::parse($transactionDate)->toDateString();
        $today = now()->toDateString();

        if (abs($signedAmount) < 0.005) {
            $this->syncCurrentBank($bankId, $currentBalance);

            return;
        }

        if ($effectiveDate <= $today) {
            $hasEffectiveSnapshot = DailyBank::where('db_bank', $bankId)
                ->where('status', '1')
                ->whereDate('db_date', $effectiveDate)
                ->exists();

            if (!$hasEffectiveSnapshot && $effectiveDate < $today) {
                $this->rebuildFrom($bankId, $effectiveDate);
            } else {
                DailyBank::where('db_bank', $bankId)
                    ->where('status', '1')
                    ->whereDate('db_date', '>=', $effectiveDate)
                    ->increment('db_amount', $signedAmount, [
                        'updated_at' => now(),
                    ]);
            }
        }

        $this->syncCurrentBank($bankId, $currentBalance);
    }

    /**
     * Snapshot only the requested banks. An empty bank list means all banks
     * and is intended for the nightly closing command.
     */
    public function snapshotCurrent(?array $bankIds = null): void
    {
        $query = Banking::query();

        if ($bankIds !== null) {
            $query->whereIn('bk_id', collect($bankIds)->map(fn ($id) => (int) $id)->unique());
        }

        $query->orderBy('bk_id')->get()->each(function (Banking $bank): void {
            $this->syncCurrentBank((int) $bank->bk_id, (float) $bank->bk_clbalance);
        });
    }

    public function syncCurrentBank(int $bankId, ?float $currentBalance = null): void
    {
        $bank = Banking::whereKey($bankId)->first();

        if (!$bank) {
            return;
        }

        $date = now()->toDateString();
        $amount = round($currentBalance ?? (float) $bank->bk_clbalance, 2);
        $values = [
            'financial_year' => DailyBank::getFinancialYear($date),
            'db_amount' => $amount,
            'status' => '1',
            'updated_at' => now(),
        ];

        $existing = DailyBank::where('db_bank', $bankId)
            ->whereDate('db_date', $date)
            ->orderByDesc('db_id')
            ->first();

        if ($existing) {
            $existing->forceFill($values)->save();

            return;
        }

        DailyBank::create($values + [
            'db_date' => $date,
            'db_bank' => $bankId,
        ]);
    }

    /**
     * Rebuild historical daily closings from the latest snapshot before the
     * requested date and the active, signed bank movements after that anchor.
     * This is intended for an explicit reconciliation command, not normal
     * request-time posting.
     */
    public function rebuildFrom(int $bankId, string $fromDate): array
    {
        $bank = Banking::whereKey($bankId)->firstOrFail();
        $today = now()->toDateString();
        $fromDate = min(Carbon::parse($fromDate)->toDateString(), $today);

        $anchor = DailyBank::where('db_bank', $bankId)
            ->where('status', '1')
            ->whereDate('db_date', '<', $fromDate)
            ->orderByDesc('db_date')
            ->orderByDesc('db_id')
            ->first();

        $startDate = $anchor
            ? Carbon::parse($anchor->db_date)->addDay()->toDateString()
            : $fromDate;
        $opening = (float) ($anchor?->db_amount ?? $bank->bk_opbalance);

        if (!$anchor) {
            $earliestMovement = $this->earliestMovementDate($bankId);
            if ($earliestMovement && $earliestMovement < $startDate) {
                $startDate = $earliestMovement;
            }
        }

        $effects = $this->signedEffects($bankId, $startDate, $today);
        $running = round($opening, 2);
        $rows = [];

        foreach (CarbonPeriod::create($startDate, $today) as $date) {
            $dateString = $date->toDateString();
            $running = round($running + (float) ($effects[$dateString] ?? 0), 2);
            $rows[] = [
                'db_date' => $dateString,
                'financial_year' => DailyBank::getFinancialYear($dateString),
                'db_bank' => $bankId,
                'db_amount' => $running,
                'status' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($bankId, $startDate, $rows): void {
            DailyBank::where('db_bank', $bankId)
                ->whereDate('db_date', '>=', $startDate)
                ->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('daily_bank')->insert($chunk);
            }
        });

        return [
            'bank_id' => $bankId,
            'from_date' => $startDate,
            'to_date' => $today,
            'opening_balance' => round($opening, 2),
            'closing_balance' => round($running, 2),
            'banking_balance' => round((float) $bank->bk_clbalance, 2),
            'difference' => round((float) $bank->bk_clbalance - $running, 2),
            'rows' => count($rows),
        ];
    }

    private function earliestMovementDate(int $bankId): ?string
    {
        $ledgerDate = LedgerBook::query()
            ->where('status', '1')
            ->where(function ($query) use ($bankId): void {
                $query->where('lb_paymode', $bankId)
                    ->orWhere(function ($transfer) use ($bankId): void {
                        $transfer->where('lb_type', 'tran')->where('lb_payee', $bankId);
                    });
            })
            ->min('lb_date');

        $posDate = null;
        if (Schema::hasTable('pos_sale_payments') && Schema::hasTable('sales')) {
            $posDate = DB::table('pos_sale_payments as p')
                ->join('sales as s', 's.sa_id', '=', 'p.sale_id')
                ->where('p.banking_id', $bankId)
                ->where('s.status', '1')
                ->min('s.sa_date');
        }

        return collect([$ledgerDate, $posDate])->filter()->sort()->first();
    }

    private function signedEffects(int $bankId, string $fromDate, string $toDate): Collection
    {
        $effects = collect();
        $posSaleIds = collect();

        if (Schema::hasTable('pos_sale_payments') && Schema::hasTable('sales')) {
            $posSaleIds = DB::table('pos_sale_payments as p')
                ->join('sales as s', 's.sa_id', '=', 'p.sale_id')
                ->where('s.status', '1')
                ->whereBetween('s.sa_date', [$fromDate, $toDate])
                ->pluck('p.sale_id')
                ->unique()
                ->values();

            $posPayments = DB::table('pos_sale_payments as p')
                ->join('sales as s', 's.sa_id', '=', 'p.sale_id')
                ->where('p.banking_id', $bankId)
                ->where('s.status', '1')
                ->whereBetween('s.sa_date', [$fromDate, $toDate])
                ->select('p.sale_id', 's.sa_date', 'p.amount')
                ->get();

            foreach ($posPayments as $payment) {
                $effects[$payment->sa_date] = round(
                    (float) ($effects[$payment->sa_date] ?? 0) + (float) $payment->amount,
                    2
                );
            }
        }

        $ledgerRows = LedgerBook::query()
            ->where('status', '1')
            ->whereBetween('lb_date', [$fromDate, $toDate])
            ->where(function ($query) use ($bankId): void {
                $query->where('lb_paymode', $bankId)
                    ->orWhere(function ($transfer) use ($bankId): void {
                        $transfer->where('lb_type', 'tran')->where('lb_payee', $bankId);
                    });
            })
            ->whereIn('lb_type', array_merge(self::INFLOW_TYPES, self::OUTFLOW_TYPES, ['tran']))
            ->orderBy('lb_date')
            ->orderBy('lb_id')
            ->get();

        foreach ($ledgerRows as $entry) {
            if ($entry->lb_type === 'sp' && $posSaleIds->contains((int) $entry->lb_vid)) {
                continue;
            }

            $signed = 0.0;
            if (in_array($entry->lb_type, self::INFLOW_TYPES, true) && (int) $entry->lb_paymode === $bankId) {
                $signed = (float) $entry->lb_amount;
            } elseif (in_array($entry->lb_type, self::OUTFLOW_TYPES, true) && (int) $entry->lb_paymode === $bankId) {
                $signed = -(float) $entry->lb_amount;
            } elseif ($entry->lb_type === 'tran') {
                if ((int) $entry->lb_payee === $bankId) {
                    $signed -= (float) $entry->lb_amount;
                }
                if ((int) $entry->lb_paymode === $bankId) {
                    $signed += (float) $entry->lb_amount;
                }
            }

            $effects[$entry->lb_date] = round((float) ($effects[$entry->lb_date] ?? 0) + $signed, 2);
        }

        return $effects;
    }
}
