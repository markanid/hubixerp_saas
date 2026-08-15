<?php

namespace Modules\Pos\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Pos\app\Models\PosHeldBill;
use Modules\Pos\app\Models\PosSession;
use Modules\Sale\app\Models\Sale;
use Modules\Sale\app\Models\SaleSetting;
use Modules\Sale\app\Services\SaleCalculator;
use Modules\Sale\app\Services\SaleService;
use Modules\Settings\app\Models\Company;
use Modules\Product\app\Services\MrpInventoryService;

class PosSaleService
{
    public function __construct(
        private readonly SaleCalculator $calculator,
        private readonly SaleService $saleService,
        private readonly StockService $stockService,
        private readonly PaymentService $paymentService,
        private readonly LedgerService $ledgerService,
        private readonly MrpInventoryService $mrpInventory
    ) {
    }

    public function calculate(array $items, string $saleType = '1'): array
    {
        $items = $this->mrpInventory->applySalePricing(
            $items,
            (bool) (SaleSetting::values()['mrp_pricing_mode'] ?? false)
        );
        if (!$this->allowsOutOfStockSale()) {
            $this->stockService->assertAvailable($items);
        }

        return $this->calculator->calculate($items, $saleType, $this->shouldCollectTax($saleType));
    }

    public function create(array $data, PosSession $session): Sale
    {
        return DB::transaction(function () use ($data, $session) {
            $calculation = $this->calculate($data['sale_items'], (string) $data['sa_type']);
            $payments = $this->paymentService->normalize($data['payments'], (float) $calculation['amount_payable']);
            $amountPaid = round((float) $payments->sum('amount'), 2);

            $payload = array_merge($data, [
                'sa_paymode' => $this->paymentService->primaryBankingId($payments),
                'sa_amount_paid' => $amountPaid,
                'sa_date' => Carbon::parse($data['sale_date'] ?? now())->format('d/m/Y'),
                'sa_remark' => trim(($data['sa_remark'] ?? '') . ' POS Counter: ' . $session->counter_code),
                'sale_origin' => 'pos',
            ]);

            $sale = $this->saleService->create($payload);
            $sale->forceFill([
                'pos_session_id' => $session->id,
                'sale_origin' => 'pos',
            ])->save();

            $this->paymentService->persist($sale, $session->id, $payments);
            $this->paymentService->rebalanceTenderAccounts($sale, $payments);
            $this->ledgerService->refreshCustomer($sale);
            $this->refreshSessionCash($session);

            return $sale->fresh(['saleDetails.product', 'customer', 'banking']);
        }, 5);
    }

    public function hold(array $data, PosSession $session): PosHeldBill
    {
        $calculation = $this->calculate($data['sale_items'], (string) ($data['sa_type'] ?? '1'));

        return PosHeldBill::create([
            'pos_session_id' => $session->id,
            'user_id' => $session->user_id,
            'hold_no' => 'H' . now()->format('ymdHis') . random_int(10, 99),
            'customer_id' => $data['sa_customer'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'cart' => [
                'sa_type' => $data['sa_type'] ?? '1',
                'sale_items' => $data['sale_items'],
            ],
            'payments' => $data['payments'] ?? [],
            'amount_payable' => $calculation['amount_payable'],
        ]);
    }

    private function refreshSessionCash(PosSession $session): void
    {
        $cashTotal = $session->payments()
            ->where('method', 'cash')
            ->sum('amount');

        $session->forceFill([
            'expected_cash' => round((float) $session->opening_cash + (float) $cashTotal, 2),
        ])->save();
    }

    private function allowsOutOfStockSale(): bool
    {
        return (bool) (SaleSetting::values()['allow_out_of_stock_sale'] ?? false);
    }

    private function shouldCollectTax(string $saleType): bool
    {
        return Company::taxProfile()['collect_tax'] && $saleType !== '0';
    }
}