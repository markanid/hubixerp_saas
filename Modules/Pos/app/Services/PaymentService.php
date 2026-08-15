<?php

namespace Modules\Pos\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Finance\app\Models\Balance;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\DailyBank;
use Modules\Pos\app\Models\PosSalePayment;
use Modules\Sale\app\Models\Sale;

class PaymentService
{
    public function normalize(array $payments, float $amountPayable): Collection
    {
        $bankIds = Banking::whereIn('bk_id', collect($payments)->pluck('banking_id')->filter()->unique())
            ->pluck('bk_id')
            ->map(fn ($id) => (int) $id);

        $normalized = collect($payments)
            ->filter(fn ($payment) => (float) ($payment['amount'] ?? 0) > 0)
            ->map(function ($payment, $index) use ($bankIds) {
                $bankingId = (int) ($payment['banking_id'] ?? 0);
                if (!$bankIds->contains($bankingId)) {
                    throw ValidationException::withMessages(["payments.$index.banking_id" => 'Invalid payment account.']);
                }

                return [
                    'banking_id' => $bankingId,
                    'method' => strtolower((string) ($payment['method'] ?? 'cash')),
                    'amount' => round((float) $payment['amount'], 2),
                    'reference_no' => $payment['reference_no'] ?? null,
                ];
            })
            ->values();

        if ($normalized->isEmpty()) {
            throw ValidationException::withMessages(['payments' => 'At least one payment is required.']);
        }

        $total = round((float) $normalized->sum('amount'), 2);
        if ($total > round($amountPayable, 2)) {
            throw ValidationException::withMessages(['payments' => 'Paid amount cannot exceed payable amount.']);
        }

        return $normalized;
    }

    public function persist(Sale $sale, ?int $sessionId, Collection $payments): void
    {
        PosSalePayment::where('sale_id', $sale->sa_id)->delete();

        foreach ($payments as $payment) {
            PosSalePayment::create([
                'sale_id' => $sale->sa_id,
                'pos_session_id' => $sessionId,
                'banking_id' => $payment['banking_id'],
                'method' => $payment['method'],
                'amount' => $payment['amount'],
                'reference_no' => $payment['reference_no'],
            ]);
        }
    }

    public function rebalanceTenderAccounts(Sale $sale, Collection $payments): void
    {
        $primaryPaymode = (int) $sale->sa_paymode;
        $paidTotal = round((float) $sale->sa_amount_paid, 2);
        $grouped = $payments
            ->groupBy('banking_id')
            ->map(fn (Collection $rows) => round((float) $rows->sum('amount'), 2));

        if ($grouped->count() === 1 && (int) $grouped->keys()->first() === $primaryPaymode) {
            return;
        }

        $paymodes = $grouped->keys()->push($primaryPaymode)->unique()->values();
        Banking::whereIn('bk_id', $paymodes)->lockForUpdate()->get();
        // Balance::whereIn('balance_pmode', $paymodes)->lockForUpdate()->get();

        // Balance::updateDebitBalance($sale->sa_date, $primaryPaymode, $paidTotal);
        Banking::updateDebitBanking($primaryPaymode, $paidTotal, $sale->sa_date);

        foreach ($grouped as $bankingId => $amount) {
            // Balance::updateCreditBalance($sale->sa_date, (int) $bankingId, (float) $amount);
            Banking::updateCreditBanking((int) $bankingId, (float) $amount, $sale->sa_date);
        }

    }

    public function primaryBankingId(Collection $payments): int
    {
        return (int) $payments->sortByDesc('amount')->first()['banking_id'];
    }
}
