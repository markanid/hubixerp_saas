<?php

namespace Modules\Reports\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Consumption\app\Models\Consumption;
use Modules\Estimation\app\Models\Estimation;
use Modules\Finance\app\Models\DailyBank;
use Modules\Finance\app\Models\Expense;
use Modules\Finance\app\Models\LedgerBook;
use Modules\Pos\app\Models\PosSalePayment;
use Modules\Pos\app\Models\PosSession;
use Modules\Purchase\app\Models\Purchase;
use Modules\Returns\app\Models\Returns;
use Modules\Sale\app\Models\Sale;
use Modules\Service\app\Models\Service;

class ReportsController extends Controller
{
    public function center()
    {
        return view('reports::reports.index', [
            'page_title' => 'Reports',
            'reports' => $this->reportCatalog(),
        ]);
    }

    public function index()
    {
        return view('reports::reports.view', array_merge(
            ['page_title' => 'Daily Report'],
            $this->dailyReportData($this->clampDateToFinancialYear(today()->format('Y-m-d')))
        ));
    }

    public function search(Request $request)
    {
        $fdate = $this->clampDateToFinancialYear(
            Carbon::createFromFormat('d/m/Y', $request->fdate)->format('Y-m-d')
        );

        return view('reports::reports.view', array_merge(
            ['page_title' => 'Daily Report'],
            $this->dailyReportData($fdate)
        ));
    }

    public function report(Request $request, string $type = 'business')
    {
        $catalog = $this->reportCatalog();

        abort_unless(isset($catalog[$type]), 404);

        $dates = $this->reportDates($request);

        $data = match ($type) {
            'sales' => $this->salesReport($dates),
            'purchases' => $this->purchasesReport($dates),
            'services' => $this->servicesReport($dates),
            'returns' => $this->returnsReport($dates),
            'stock' => $this->stockReport($dates),
            'outstanding' => $this->outstandingReport($dates),
            'tax' => $this->taxReport($dates),
            'expenses' => $this->expensesReport($dates),
            'estimations' => $this->estimationsReport($dates),
            'consumption' => $this->consumptionReport($dates),
            'finance' => $this->financeReport($dates),
            'pos' => $this->posReport($dates),
            default => $this->businessReport($dates),
        };

        return view('reports::reports.summary', array_merge($data, [
            'page_title' => $catalog[$type]['label'],
            'report' => $catalog[$type],
            'type' => $type,
            'from_date' => $dates['from'],
            'to_date' => $dates['to'],
            'fy_start' => $dates['fy_start'],
            'fy_end' => $dates['fy_end'],
            'reports' => $catalog,
        ]));
    }

    private function reportCatalog(): array
    {
        return [
            'business' => ['label' => 'Business Summary', 'icon' => 'fas fa-chart-pie', 'color' => 'primary', 'description' => 'Sales, purchase, service, returns, expense and stock movement totals.'],
            'sales' => ['label' => 'Sales Report', 'icon' => 'fas fa-cash-register', 'color' => 'success', 'description' => 'Sale register, customer summary and product-wise sales.'],
            'purchases' => ['label' => 'Purchase Report', 'icon' => 'fas fa-cart-arrow-down', 'color' => 'info', 'description' => 'Purchase register, supplier summary and product-wise purchases.'],
            'services' => ['label' => 'Service Report', 'icon' => 'fas fa-wrench', 'color' => 'warning', 'description' => 'Service invoices with service and product item breakup.'],
            'returns' => ['label' => 'Returns Report', 'icon' => 'fas fa-undo-alt', 'color' => 'danger', 'description' => 'Sale return and purchase return values from the common return table.'],
            'stock' => ['label' => 'Stock Report', 'icon' => 'fas fa-boxes', 'color' => 'secondary', 'description' => 'Current stock, stock value and stock ledger movements.'],
            'outstanding' => ['label' => 'Outstanding Report', 'icon' => 'fas fa-balance-scale', 'color' => 'navy', 'description' => 'Pending receivables and payables from sale, service and purchase balances.'],
            'tax' => ['label' => 'Tax Report', 'icon' => 'fas fa-percent', 'color' => 'purple', 'description' => 'GST/VAT tax totals across sales, services, purchases and returns.'],
            'expenses' => ['label' => 'Expense Report', 'icon' => 'fas fa-rupee-sign', 'color' => 'maroon', 'description' => 'Expense register and category-wise expense totals.'],
            'estimations' => ['label' => 'Estimation Report', 'icon' => 'fas fa-receipt', 'color' => 'teal', 'description' => 'Estimation register and product summary without account effect.'],
            'consumption' => ['label' => 'Consumption Report', 'icon' => 'fas fa-clipboard-list', 'color' => 'olive', 'description' => 'Consumption register and consumed product summary.'],
            'finance' => ['label' => 'Finance Report', 'icon' => 'fas fa-university', 'color' => 'indigo', 'description' => 'Ledger movement, payment mode and bank balance summaries.'],
            'pos' => ['label' => 'POS Report', 'icon' => 'fas fa-store', 'color' => 'orange', 'description' => 'POS sessions, payments and POS sale totals.'],
        ];
    }

    private function reportDates(Request $request): array
    {
        $financialYear = $this->financialYearRange();
        $from = $request->filled('from_date')
            ? Carbon::parse($request->from_date)
            : $financialYear['start']->copy();

        $to = $request->filled('to_date')
            ? Carbon::parse($request->to_date)
            : $financialYear['end']->copy();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->lt($financialYear['start'])) {
            $from = $financialYear['start']->copy();
        }

        if ($to->gt($financialYear['end'])) {
            $to = $financialYear['end']->copy();
        }

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'fy_start' => $financialYear['start']->format('Y-m-d'),
            'fy_end' => $financialYear['end']->format('Y-m-d'),
        ];
    }

    private function financialYear(): ?string
    {
        return session('financial_year');
    }

    private function financialYearRange(): array
    {
        $financialYear = $this->financialYear();

        if ($financialYear && preg_match('/^(\d{4})-(\d{4})$/', $financialYear, $matches)) {
            return [
                'start' => Carbon::createFromDate((int) $matches[1], 4, 1)->startOfDay(),
                'end' => Carbon::createFromDate((int) $matches[2], 3, 31)->endOfDay(),
            ];
        }

        $startYear = now()->month >= 4 ? now()->year : now()->year - 1;

        return [
            'start' => Carbon::createFromDate($startYear, 4, 1)->startOfDay(),
            'end' => Carbon::createFromDate($startYear + 1, 3, 31)->endOfDay(),
        ];
    }

    private function clampDateToFinancialYear(string $date): string
    {
        $range = $this->financialYearRange();
        $value = Carbon::parse($date);

        if ($value->lt($range['start'])) {
            return $range['start']->format('Y-m-d');
        }

        if ($value->gt($range['end'])) {
            return $range['end']->format('Y-m-d');
        }

        return $value->format('Y-m-d');
    }

    private function dailyReportData(string $fdate): array
    {
        $fdate = $this->clampDateToFinancialYear($fdate);
        $banks = $this->dailyBankBalancesAsOf($fdate);
        $openingBanks = $this->dailyBankBalancesAsOf(
            Carbon::parse($fdate)->subDay()->toDateString()
        );
        $openingBalance = (float) $openingBanks->sum('db_amount');
        $snapshotClosing = (float) $banks->sum('db_amount');
        $snapshotDates = $banks->pluck('db_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();
        $bankBalanceDate = $snapshotDates->count() === 1 ? $snapshotDates->first() : null;
        $bankBalanceFinancialYear = $bankBalanceDate
            ? DailyBank::getFinancialYear($bankBalanceDate)
            : null;

        $incomeTransactions = LedgerBook::with(['banking', 'payeeBank'])
            ->where('status', '1')
            ->whereDate('lb_date', $fdate)
            ->whereIn('lb_type', ['sp', 'svp', 'esp', 'prp', 'loan'])
            ->get();

        $expenseTransactions = LedgerBook::with(['banking', 'payeeBank'])
            ->where('status', '1')
            ->whereDate('lb_date', $fdate)
            ->whereIn('lb_type', ['pp', 'srp', 'exp', 'loan_asset'])
            ->get();

        $incomeTransactions->each(function (LedgerBook $entry): void {
            $entry->setAttribute('daily_report_url', $this->dailyLedgerTransactionUrl($entry));
        });

        $expenseTransactions->each(function (LedgerBook $entry): void {
            $entry->setAttribute('daily_report_url', $this->dailyLedgerTransactionUrl($entry));
        });

        $bankTransactions = LedgerBook::with(['banking', 'payeeBank'])
            ->where('status', '1')
            ->whereDate('lb_date', $fdate)
            ->where('lb_type', 'tran')
            ->get();

        $incomeTotal = $incomeTransactions->sum('lb_amount');
        $expenseTotal = $expenseTransactions->sum('lb_amount');
        $transferTotal = $bankTransactions->sum('lb_amount');
        $closingBalance = $openingBalance + $incomeTotal - $expenseTotal;

        return [
            'banks' => $banks,
            'opening_balance' => $openingBalance,
            'incomeTransactions' => $incomeTransactions,
            'expenseTransactions' => $expenseTransactions,
            'bankTransactions' => $bankTransactions,
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'transferTotal' => $transferTotal,
            'closingBalance' => $closingBalance,
            'snapshotClosing' => $snapshotClosing,
            'reconciliationDifference' => $snapshotClosing - $closingBalance,
            'bankBalanceDate' => $bankBalanceDate,
            'bankBalanceFinancialYear' => $bankBalanceFinancialYear,
            'fdate' => $fdate,
        ];
    }

    private function dailyBankBalancesAsOf(string $date): \Illuminate\Support\Collection
    {
        $latestDates = DB::table('daily_bank as snapshot_dates')
            ->whereNotNull('snapshot_dates.db_bank')
            ->where('snapshot_dates.status', '1')
            ->whereDate('snapshot_dates.db_date', '<=', $date)
            ->groupBy('snapshot_dates.db_bank')
            ->select(
                'snapshot_dates.db_bank',
                DB::raw('MAX(snapshot_dates.db_date) as latest_date')
            );

        return DailyBank::with('banking')
            ->joinSub($latestDates, 'latest_snapshot', function ($join): void {
                $join->on('daily_bank.db_bank', '=', 'latest_snapshot.db_bank')
                    ->on('daily_bank.db_date', '=', 'latest_snapshot.latest_date');
            })
            ->where('daily_bank.status', '1')
            ->select('daily_bank.*')
            ->orderBy('daily_bank.db_bank')
            ->orderByDesc('daily_bank.db_id')
            ->get()
            ->unique('db_bank')
            ->values();
    }

    private function businessReport(array $dates): array
    {
        $sales = $this->totals(Sale::query(), 'sa_date', $dates, ['tax' => 'sa_gst', 'payable' => 'sa_amount_payable', 'paid' => 'sa_amount_paid', 'balance' => 'sa_balance']);
        $purchases = $this->totals(Purchase::query(), 'pu_date', $dates, ['tax' => 'pu_gst', 'payable' => 'pu_amount_payable', 'paid' => 'pu_amount_paid', 'balance' => 'pu_balance']);
        $services = $this->totals(Service::query(), 'sv_date', $dates, ['tax' => 'sv_gst', 'payable' => 'sv_amount_payable', 'paid' => 'sv_amount_paid', 'balance' => 'sv_balance']);
        $saleReturns = $this->totals(Returns::where('pr_type', '2'), 'pr_date', $dates, ['tax' => 'pr_gst', 'payable' => 'pr_amount_payable', 'paid' => 'pr_amount_paid', 'balance' => 'pr_balance']);
        $purchaseReturns = $this->totals(Returns::where('pr_type', '1'), 'pr_date', $dates, ['tax' => 'pr_gst', 'payable' => 'pr_amount_payable', 'paid' => 'pr_amount_paid', 'balance' => 'pr_balance']);
        $expenses = $this->totals(Expense::query(), 'exdate', $dates, ['amount' => 'amount']);
        $estimations = $this->totals(Estimation::query(), 'es_date', $dates, ['payable' => 'es_amount_payable', 'paid' => 'es_amount_paid', 'balance' => 'es_balance']);
        $consumptions = $this->totals(Consumption::query(), 'con_date', $dates, ['amount' => 'con_amount']);

        return [
            'cards' => [
                ['label' => 'Sales', 'value' => $sales['payable'], 'type' => 'amount'],
                ['label' => 'Purchases', 'value' => $purchases['payable'], 'type' => 'amount'],
                ['label' => 'Services', 'value' => $services['payable'], 'type' => 'amount'],
                ['label' => 'Expenses', 'value' => $expenses['amount'], 'type' => 'amount'],
                ['label' => 'Receivable Balance', 'value' => $sales['balance'] + $services['balance'] + $estimations['balance'], 'type' => 'amount'],
                ['label' => 'Payable Balance', 'value' => $purchases['balance'], 'type' => 'amount'],
            ],
            'sections' => [[
                'title' => 'Module Summary',
                'columns' => $this->summaryColumns(),
                'rows' => [
                    $this->summaryRow('Sales', $sales),
                    $this->summaryRow('Purchases', $purchases),
                    $this->summaryRow('Services', $services),
                    $this->summaryRow('Sale Return', $saleReturns),
                    $this->summaryRow('Purchase Return', $purchaseReturns),
                    $this->summaryRow('Expenses', $expenses),
                    $this->summaryRow('Estimations', $estimations),
                    $this->summaryRow('Consumption', $consumptions),
                ],
            ]],
        ];
    }

    private function salesReport(array $dates): array
    {
        $totals = $this->totals(Sale::query(), 'sa_date', $dates, ['tax' => 'sa_gst', 'payable' => 'sa_amount_payable', 'paid' => 'sa_amount_paid', 'balance' => 'sa_balance']);
        $sales = Sale::with(['customer', 'banking'])
            ->where('financial_year', $this->financialYear())
            ->where('status', '1')
            ->whereBetween('sa_date', [$dates['from'], $dates['to']])
            ->latest('sa_date')
            ->latest('sa_id')
            ->limit(500)
            ->get();

        return [
            'cards' => $this->transactionCards($totals),
            'sections' => [
                [
                    'title' => 'Sale Register',
                    'columns' => $this->registerColumns('Customer'),
                    'rows' => $sales->map(fn ($sale) => [
                        'date' => $sale->sa_date,
                        'vno' => $sale->sa_vno,
                        'party' => $sale->customer->customer ?? '',
                        'payable' => $sale->sa_amount_payable,
                        'paid' => $sale->sa_amount_paid,
                        'balance' => $sale->sa_balance,
                        'paymode' => $sale->banking->bk_bank ?? '',
                        '_actions' => $this->transactionActions('sales', $sale->sa_id, ['show', 'edit', 'print']),
                    ]),
                ],
                [
                    'title' => 'Customer Summary',
                    'columns' => $this->partyColumns('Customer'),
                    'rows' => $this->partySummary('sales', 'sa_customer', 'customer', 'customer', 'sa_date', 'sa_amount_payable', $dates),
                ],
                [
                    'title' => 'Product Sales',
                    'columns' => $this->itemColumns(),
                    'rows' => $this->itemSummary('sale_details', 'sales', 'sad_sid', 'sa_id', 'sad_itemid', 'sad_itemqty', 'sad_total', 'sad_gst', 'sa_date', $dates),
                ],
            ],
        ];
    }

    private function purchasesReport(array $dates): array
    {
        $totals = $this->totals(Purchase::query(), 'pu_date', $dates, ['tax' => 'pu_gst', 'payable' => 'pu_amount_payable', 'paid' => 'pu_amount_paid', 'balance' => 'pu_balance']);
        $purchases = Purchase::with(['vendor', 'banking'])
            ->where('financial_year', $this->financialYear())
            ->where('pu_status', '1')
            ->whereBetween('pu_date', [$dates['from'], $dates['to']])
            ->latest('pu_date')
            ->latest('pu_id')
            ->limit(500)
            ->get();

        return [
            'cards' => $this->transactionCards($totals),
            'sections' => [
                [
                    'title' => 'Purchase Register',
                    'columns' => $this->registerColumns('Supplier'),
                    'rows' => $purchases->map(fn ($purchase) => [
                        'date' => $purchase->pu_date,
                        'vno' => $purchase->pu_vno,
                        'party' => $purchase->vendor->cp_name ?? '',
                        'payable' => $purchase->pu_amount_payable,
                        'paid' => $purchase->pu_amount_paid,
                        'balance' => $purchase->pu_balance,
                        'paymode' => $purchase->banking->bk_bank ?? '',
                        '_actions' => $this->transactionActions('purchases', $purchase->pu_id, ['show', 'edit', 'print']),
                    ]),
                ],
                [
                    'title' => 'Supplier Summary',
                    'columns' => $this->partyColumns('Supplier'),
                    'rows' => $this->partySummary('purchase', 'pu_vendor', 'vendor', 'cp_name', 'pu_date', 'pu_amount_payable', $dates, 'pu_status'),
                ],
                [
                    'title' => 'Product Purchases',
                    'columns' => $this->itemColumns(),
                    'rows' => $this->itemSummary('purchase_detail', 'purchase', 'pud_pid', 'pu_id', 'pud_itemid', 'pud_itemqty', 'pud_total', 'pud_gst', 'pu_date', $dates, null, 'pu_status'),
                ],
            ],
        ];
    }

    private function servicesReport(array $dates): array
    {
        $totals = $this->totals(Service::query(), 'sv_date', $dates, ['tax' => 'sv_gst', 'payable' => 'sv_amount_payable', 'paid' => 'sv_amount_paid', 'balance' => 'sv_balance']);
        $services = Service::with(['customer', 'banking'])
            ->where('financial_year', $this->financialYear())
            ->where('status', '1')
            ->whereBetween('sv_date', [$dates['from'], $dates['to']])
            ->latest('sv_date')
            ->latest('sv_id')
            ->limit(500)
            ->get();

        return [
            'cards' => $this->transactionCards($totals),
            'sections' => [
                [
                    'title' => 'Service Register',
                    'columns' => $this->registerColumns('Customer'),
                    'rows' => $services->map(fn ($service) => [
                        'date' => $service->sv_date,
                        'vno' => $service->sv_vno,
                        'party' => $service->customer->customer ?? '',
                        'payable' => $service->sv_amount_payable,
                        'paid' => $service->sv_amount_paid,
                        'balance' => $service->sv_balance,
                        'paymode' => $service->banking->bk_bank ?? '',
                        '_actions' => $this->transactionActions('services', $service->sv_id, ['show', 'edit', 'print']),
                    ]),
                ],
                [
                    'title' => 'Service Item Summary',
                    'columns' => $this->itemColumns(),
                    'rows' => $this->itemSummary('service_details', 'services', 'svd_sid', 'sv_id', 'svd_itemid', 'svd_itemqty', 'svd_total', 'svd_gst', 'sv_date', $dates, ['svd_type', 'service']),
                ],
                [
                    'title' => 'Product Item Summary',
                    'columns' => $this->itemColumns(),
                    'rows' => $this->itemSummary('service_details', 'services', 'svd_sid', 'sv_id', 'svd_itemid', 'svd_itemqty', 'svd_total', 'svd_gst', 'sv_date', $dates, ['svd_type', 'sale']),
                ],
            ],
        ];
    }

    private function returnsReport(array $dates): array
    {
        $saleReturns = $this->totals(Returns::where('pr_type', '2'), 'pr_date', $dates, ['tax' => 'pr_gst', 'payable' => 'pr_amount_payable', 'paid' => 'pr_amount_paid', 'balance' => 'pr_balance']);
        $purchaseReturns = $this->totals(Returns::where('pr_type', '1'), 'pr_date', $dates, ['tax' => 'pr_gst', 'payable' => 'pr_amount_payable', 'paid' => 'pr_amount_paid', 'balance' => 'pr_balance']);
        $returns = Returns::with(['customer', 'vendor', 'banking'])
            ->where('financial_year', $this->financialYear())
            ->where('status', '1')
            ->whereBetween('pr_date', [$dates['from'], $dates['to']])
            ->latest('pr_date')
            ->latest('pr_id')
            ->limit(500)
            ->get();

        return [
            'cards' => [
                ['label' => 'Sale Return', 'value' => $saleReturns['payable'], 'type' => 'amount'],
                ['label' => 'Purchase Return', 'value' => $purchaseReturns['payable'], 'type' => 'amount'],
                ['label' => 'Sale Return Paid', 'value' => $saleReturns['paid'], 'type' => 'amount'],
                ['label' => 'Purchase Return Paid', 'value' => $purchaseReturns['paid'], 'type' => 'amount'],
            ],
            'sections' => [
                [
                    'title' => 'Return Register',
                    'columns' => $this->registerColumns('Party', ['type' => 'Type']),
                    'rows' => $returns->map(fn ($return) => [
                        'date' => $return->pr_date,
                        'vno' => $return->pr_vno,
                        'type' => $return->pr_type == '1' ? 'Purchase Return' : 'Sale Return',
                        'party' => $return->pr_type == '1' ? ($return->vendor->cp_name ?? '') : ($return->customer->customer ?? ''),
                        'payable' => $return->pr_amount_payable,
                        'paid' => $return->pr_amount_paid,
                        'balance' => $return->pr_balance,
                        'paymode' => $return->banking->bk_bank ?? '',
                        '_actions' => $this->transactionActions($return->pr_type == '1' ? 'purchase-returns' : 'sales-returns', $return->pr_id, ['show', 'edit', 'print']),
                    ]),
                ],
                [
                    'title' => 'Returned Products',
                    'columns' => $this->itemColumns(['return_type' => 'Return Type']),
                    'rows' => $this->returnItemSummary($dates),
                ],
            ],
        ];
    }

    private function stockReport(array $dates): array
    {
        $currentStock = DB::table('stock as s')
            ->leftJoin('product as p', 'p.product_code', '=', 's.stock_product_id')
            ->where('s.status', '1')
            ->select('s.stock_product_id as code', 'p.product', 's.stock_qty', 'p.pprice', 'p.price')
            ->orderBy('p.product')
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'item' => $row->product ?? $row->code,
                'qty' => $row->stock_qty,
                'purchase_value' => (float) $row->stock_qty * (float) $row->pprice,
                'sale_value' => (float) $row->stock_qty * (float) $row->price,
            ]);

        $movements = DB::table('stock_ledgers as l')
            ->leftJoin('product as p', 'p.product_code', '=', 'l.stock_item_id')
            ->where('l.financial_year', $this->financialYear())
            ->whereBetween('l.stock_date', [$dates['from'], $dates['to']])
            ->select('l.stock_date', 'p.product', 'l.stock_item_id', 'l.stock_type', 'l.stock_ref_id', 'l.stock_in', 'l.stock_out', 'l.stock_balance')
            ->orderByDesc('l.stock_date')
            ->orderByDesc('l.id')
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                'date' => $row->stock_date,
                'item' => $row->product ?? $row->stock_item_id,
                'type' => $row->stock_type,
                'ref' => $row->stock_ref_id,
                'in' => $row->stock_in,
                'out' => $row->stock_out,
                'balance' => $row->stock_balance,
            ]);

        return [
            'cards' => [
                ['label' => 'Stock Items', 'value' => $currentStock->count(), 'type' => 'number'],
                ['label' => 'Current Qty', 'value' => $currentStock->sum('qty'), 'type' => 'number'],
                ['label' => 'Purchase Value', 'value' => $currentStock->sum('purchase_value'), 'type' => 'amount'],
                ['label' => 'Sale Value', 'value' => $currentStock->sum('sale_value'), 'type' => 'amount'],
            ],
            'sections' => [
                [
                    'title' => 'Current Stock',
                    'columns' => [
                        ['key' => 'code', 'label' => 'Code'],
                        ['key' => 'item', 'label' => 'Product'],
                        ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
                        ['key' => 'purchase_value', 'label' => 'Purchase Value', 'type' => 'amount'],
                        ['key' => 'sale_value', 'label' => 'Sale Value', 'type' => 'amount'],
                    ],
                    'rows' => $currentStock,
                ],
                [
                    'title' => 'Stock Ledger',
                    'columns' => [
                        ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                        ['key' => 'item', 'label' => 'Product'],
                        ['key' => 'type', 'label' => 'Type'],
                        ['key' => 'ref', 'label' => 'Reference'],
                        ['key' => 'in', 'label' => 'In', 'type' => 'number'],
                        ['key' => 'out', 'label' => 'Out', 'type' => 'number'],
                        ['key' => 'balance', 'label' => 'Balance', 'type' => 'number'],
                    ],
                    'rows' => $movements,
                ],
            ],
        ];
    }

    private function outstandingReport(array $dates): array
    {
        $sales = Sale::with('customer')
            ->where('financial_year', $this->financialYear())
            ->where('status', '1')
            ->where('sa_balance', '>', 0)
            ->whereBetween('sa_date', [$dates['from'], $dates['to']])
            ->latest('sa_date')
            ->limit(300)
            ->get();

        $services = Service::with('customer')
            ->where('financial_year', $this->financialYear())
            ->where('status', '1')
            ->where('sv_balance', '>', 0)
            ->whereBetween('sv_date', [$dates['from'], $dates['to']])
            ->latest('sv_date')
            ->limit(300)
            ->get();

        $estimations = Estimation::with('customer')
            ->where('financial_year', $this->financialYear())
            ->where('status', '1')
            ->where('es_account_effect', true)
            ->where('es_balance', '>', 0)
            ->whereBetween('es_date', [$dates['from'], $dates['to']])
            ->latest('es_date')
            ->limit(300)
            ->get();

        $purchases = Purchase::with('vendor')
            ->where('financial_year', $this->financialYear())
            ->where('pu_status', '1')
            ->where('pu_balance', '>', 0)
            ->whereBetween('pu_date', [$dates['from'], $dates['to']])
            ->latest('pu_date')
            ->limit(300)
            ->get();

        return [
            'cards' => [
                ['label' => 'Sale Receivable', 'value' => $sales->sum('sa_balance'), 'type' => 'amount'],
                ['label' => 'Service Receivable', 'value' => $services->sum('sv_balance'), 'type' => 'amount'],
                ['label' => 'Estimation Receivable', 'value' => $estimations->sum('es_balance'), 'type' => 'amount'],
                ['label' => 'Purchase Payable', 'value' => $purchases->sum('pu_balance'), 'type' => 'amount'],
            ],
            'sections' => [
                ['title' => 'Sale Outstanding', 'columns' => $this->outstandingColumns('Customer'), 'rows' => $sales->map(fn ($sale) => [
                    'date' => $sale->sa_date, 'vno' => $sale->sa_vno, 'party' => $sale->customer->customer ?? '', 'due_date' => $sale->sa_due_date, 'payable' => $sale->sa_amount_payable, 'paid' => $sale->sa_amount_paid, 'balance' => $sale->sa_balance, '_actions' => $this->transactionActions('sales', $sale->sa_id, ['show', 'edit', 'print']),
                ])],
                ['title' => 'Service Outstanding', 'columns' => $this->outstandingColumns('Customer'), 'rows' => $services->map(fn ($service) => [
                    'date' => $service->sv_date, 'vno' => $service->sv_vno, 'party' => $service->customer->customer ?? '', 'due_date' => $service->sv_due_date, 'payable' => $service->sv_amount_payable, 'paid' => $service->sv_amount_paid, 'balance' => $service->sv_balance, '_actions' => $this->transactionActions('services', $service->sv_id, ['show', 'edit', 'print']),
                ])],
                ['title' => 'Estimation Outstanding', 'columns' => $this->outstandingColumns('Customer'), 'rows' => $estimations->map(fn ($estimation) => [
                    'date' => $estimation->es_date, 'vno' => $estimation->es_vno, 'party' => $estimation->customer->customer ?? '', 'due_date' => $estimation->es_due_date, 'payable' => $estimation->es_amount_payable, 'paid' => $estimation->es_amount_paid, 'balance' => $estimation->es_balance, '_actions' => $this->transactionActions('estimations', $estimation->es_id, ['show', 'edit', 'print']),
                ])],
                ['title' => 'Purchase Outstanding', 'columns' => $this->outstandingColumns('Supplier'), 'rows' => $purchases->map(fn ($purchase) => [
                    'date' => $purchase->pu_date, 'vno' => $purchase->pu_vno, 'party' => $purchase->vendor->cp_name ?? '', 'due_date' => $purchase->pu_due_date, 'payable' => $purchase->pu_amount_payable, 'paid' => $purchase->pu_amount_paid, 'balance' => $purchase->pu_balance, '_actions' => $this->transactionActions('purchases', $purchase->pu_id, ['show', 'edit', 'print']),
                ])],
            ],
        ];
    }

    private function taxReport(array $dates): array
    {
        $sales = $this->totals(Sale::query(), 'sa_date', $dates, ['tax' => 'sa_gst']);
        $purchases = $this->totals(Purchase::query(), 'pu_date', $dates, ['tax' => 'pu_gst']);
        $services = $this->totals(Service::query(), 'sv_date', $dates, ['tax' => 'sv_gst']);
        $saleReturns = $this->totals(Returns::where('pr_type', '2'), 'pr_date', $dates, ['tax' => 'pr_gst']);
        $purchaseReturns = $this->totals(Returns::where('pr_type', '1'), 'pr_date', $dates, ['tax' => 'pr_gst']);

        return [
            'cards' => [
                ['label' => 'Output Tax', 'value' => $sales['tax'] + $services['tax'] - $saleReturns['tax'], 'type' => 'amount'],
                ['label' => 'Input Tax', 'value' => $purchases['tax'] - $purchaseReturns['tax'], 'type' => 'amount'],
                ['label' => 'Net Tax', 'value' => ($sales['tax'] + $services['tax'] - $saleReturns['tax']) - ($purchases['tax'] - $purchaseReturns['tax']), 'type' => 'amount'],
            ],
            'sections' => [[
                'title' => 'Tax Summary',
                'columns' => [
                    ['key' => 'module', 'label' => 'Module'],
                    ['key' => 'tax_type', 'label' => 'Tax Type'],
                    ['key' => 'tax', 'label' => 'Tax Amount', 'type' => 'amount'],
                ],
                'rows' => [
                    ['module' => 'Sales', 'tax_type' => 'Output', 'tax' => $sales['tax']],
                    ['module' => 'Services', 'tax_type' => 'Output', 'tax' => $services['tax']],
                    ['module' => 'Sale Return', 'tax_type' => 'Output Reversal', 'tax' => $saleReturns['tax']],
                    ['module' => 'Purchases', 'tax_type' => 'Input', 'tax' => $purchases['tax']],
                    ['module' => 'Purchase Return', 'tax_type' => 'Input Reversal', 'tax' => $purchaseReturns['tax']],
                ],
            ]],
        ];
    }

    private function expensesReport(array $dates): array
    {
        $expenses = Expense::with(['excategory', 'banking'])
            ->where('financial_year', $this->financialYear())
            ->where('status', '1')
            ->whereBetween('exdate', [$dates['from'], $dates['to']])
            ->latest('exdate')
            ->latest('id')
            ->limit(500)
            ->get();

        $categoryRows = DB::table('expence as e')
            ->leftJoin('excategory as c', 'c.id', '=', 'e.categoryid')
            ->where('e.financial_year', $this->financialYear())
            ->where('e.status', '1')
            ->whereBetween('e.exdate', [$dates['from'], $dates['to']])
            ->groupBy('e.categoryid', 'c.category')
            ->select('c.category', DB::raw('COUNT(*) as count'), DB::raw('SUM(e.amount) as amount'))
            ->orderByDesc('amount')
            ->get();

        return [
            'cards' => [
                ['label' => 'Expenses', 'value' => $expenses->sum('amount'), 'type' => 'amount'],
                ['label' => 'Entries', 'value' => $expenses->count(), 'type' => 'number'],
            ],
            'sections' => [
                [
                    'title' => 'Expense Register',
                    'columns' => [
                        ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                        ['key' => 'vno', 'label' => 'Voucher No.'],
                        ['key' => 'category', 'label' => 'Category'],
                        ['key' => 'paymode', 'label' => 'Payment Mode'],
                        ['key' => 'amount', 'label' => 'Amount', 'type' => 'amount'],
                        ['key' => 'remarks', 'label' => 'Remarks'],
                    ],
                    'rows' => $expenses->map(fn ($expense) => [
                        'date' => $expense->exdate,
                        'vno' => $expense->exp_vno,
                        'category' => $expense->excategory->category ?? '',
                        'paymode' => $expense->banking->bk_bank ?? '',
                        'amount' => $expense->amount,
                        'remarks' => $expense->remarks,
                        '_actions' => $this->transactionActions('expenses', $expense->id, ['show', 'edit']),
                    ]),
                ],
                [
                    'title' => 'Category Summary',
                    'columns' => [
                        ['key' => 'category', 'label' => 'Category'],
                        ['key' => 'count', 'label' => 'Entries', 'type' => 'number'],
                        ['key' => 'amount', 'label' => 'Amount', 'type' => 'amount'],
                    ],
                    'rows' => $categoryRows,
                ],
            ],
        ];
    }

    private function estimationsReport(array $dates): array
    {
        $totals = $this->totals(Estimation::query(), 'es_date', $dates, ['payable' => 'es_amount_payable', 'paid' => 'es_amount_paid', 'balance' => 'es_balance']);
        $estimations = Estimation::with(['customer', 'banking'])
            ->where('financial_year', $this->financialYear())
            ->where('status', '1')
            ->whereBetween('es_date', [$dates['from'], $dates['to']])
            ->latest('es_date')
            ->latest('es_id')
            ->limit(500)
            ->get();

        return [
            'cards' => [
                ['label' => 'Estimations', 'value' => $totals['payable'], 'type' => 'amount'],
                ['label' => 'Bills', 'value' => $totals['count'], 'type' => 'number'],
                ['label' => 'Amount Paid', 'value' => $totals['paid'], 'type' => 'amount'],
                ['label' => 'Balance', 'value' => $totals['balance'], 'type' => 'amount'],
            ],
            'sections' => [
                [
                    'title' => 'Estimation Register',
                    'columns' => $this->registerColumns('Customer'),
                    'rows' => $estimations->map(fn ($estimation) => [
                        'date' => $estimation->es_date,
                        'vno' => $estimation->es_vno,
                        'party' => $estimation->customer->customer ?? '',
                        'payable' => $estimation->es_amount_payable,
                        'paid' => $estimation->es_amount_paid,
                        'balance' => $estimation->es_balance,
                        'paymode' => $estimation->banking->bk_bank ?? '',
                        '_actions' => $this->transactionActions('estimations', $estimation->es_id, ['show', 'edit', 'print']),
                    ]),
                ],
                [
                    'title' => 'Estimated Products',
                    'columns' => $this->itemColumns(),
                    'rows' => $this->itemSummary('estimation_details', 'estimation', 'esd_sid', 'es_id', 'esd_itemid', 'esd_itemqty', 'esd_total', null, 'es_date', $dates),
                ],
            ],
        ];
    }

    private function consumptionReport(array $dates): array
    {
        $totals = $this->totals(Consumption::query(), 'con_date', $dates, ['amount' => 'con_amount']);
        $consumptions = Consumption::with('user')
            ->where('financial_year', $this->financialYear())
            ->where('status', '1')
            ->whereBetween('con_date', [$dates['from'], $dates['to']])
            ->latest('con_date')
            ->latest('con_id')
            ->limit(500)
            ->get();

        return [
            'cards' => [
                ['label' => 'Consumption Value', 'value' => $totals['amount'], 'type' => 'amount'],
                ['label' => 'Entries', 'value' => $totals['count'], 'type' => 'number'],
            ],
            'sections' => [
                [
                    'title' => 'Consumption Register',
                    'columns' => [
                        ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                        ['key' => 'vno', 'label' => 'Voucher No.'],
                        ['key' => 'user', 'label' => 'User'],
                        ['key' => 'amount', 'label' => 'Amount', 'type' => 'amount'],
                    ],
                    'rows' => $consumptions->map(fn ($consumption) => [
                        'date' => $consumption->con_date,
                        'vno' => $consumption->con_vno,
                        'user' => $consumption->user->name ?? '',
                        'amount' => $consumption->con_amount,
                        '_actions' => $this->transactionActions('consumptions', $consumption->con_id, ['show', 'edit', 'print']),
                    ]),
                ],
                [
                    'title' => 'Consumed Products',
                    'columns' => $this->itemColumns(),
                    'rows' => $this->itemSummary('consume_detail', 'consume', 'cond_cid', 'con_id', 'cond_itemid', 'cond_qty', 'cond_total', null, 'con_date', $dates),
                ],
            ],
        ];
    }

    private function financeReport(array $dates): array
    {
        $ledgerRows = LedgerBook::with(['banking', 'payeeBank'])
            ->where('financial_year', $this->financialYear())
            ->whereBetween('lb_date', [$dates['from'], $dates['to']])
            ->orderByDesc('lb_date')
            ->orderByDesc('lb_id')
            ->limit(500)
            ->get()
            ->map(fn ($entry) => [
                'date' => $entry->lb_date,
                'type' => $this->ledgerTypeLabel($entry->lb_type),
                'vno' => $entry->lb_vno,
                'paymode' => $entry->banking->bk_bank ?? '',
                'payee' => $entry->payeeBank->bk_bank ?? $entry->lb_payee,
                'amount' => $entry->lb_amount ?: $entry->lb_tramount,
                '_actions' => $this->ledgerTransactionActions($entry),
            ]);

        $typeRows = LedgerBook::where('financial_year', $this->financialYear())
            ->whereBetween('lb_date', [$dates['from'], $dates['to']])
            ->groupBy('lb_type')
            ->select('lb_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(lb_amount) as amount'), DB::raw('SUM(lb_tramount) as transaction_amount'))
            ->get()
            ->map(fn ($row) => [
                'type' => $this->ledgerTypeLabel($row->lb_type),
                'count' => $row->count,
                'amount' => (float) $row->amount + (float) $row->transaction_amount,
            ]);

        $bankRows = $this->dailyBankBalancesAsOf($dates['to'])
            ->map(fn ($bank) => [
                'date' => $bank->db_date,
                'bank' => $bank->banking->bk_bank ?? '',
                'amount' => $bank->db_amount,
            ]);

        return [
            'cards' => [
                ['label' => 'Ledger Movement', 'value' => $typeRows->sum('amount'), 'type' => 'amount'],
                ['label' => 'Bank Balance', 'value' => $bankRows->sum('amount'), 'type' => 'amount'],
                ['label' => 'Entries', 'value' => $ledgerRows->count(), 'type' => 'number'],
            ],
            'sections' => [
                [
                    'title' => 'Ledger Type Summary',
                    'columns' => [
                        ['key' => 'type', 'label' => 'Type'],
                        ['key' => 'count', 'label' => 'Entries', 'type' => 'number'],
                        ['key' => 'amount', 'label' => 'Amount', 'type' => 'amount'],
                    ],
                    'rows' => $typeRows,
                ],
                [
                    'title' => 'Bank Balances',
                    'columns' => [
                        ['key' => 'date', 'label' => 'As On', 'type' => 'date'],
                        ['key' => 'bank', 'label' => 'Bank'],
                        ['key' => 'amount', 'label' => 'Balance', 'type' => 'amount'],
                    ],
                    'rows' => $bankRows,
                ],
                [
                    'title' => 'Ledger Register',
                    'columns' => [
                        ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                        ['key' => 'type', 'label' => 'Type'],
                        ['key' => 'vno', 'label' => 'Voucher No.'],
                        ['key' => 'paymode', 'label' => 'Payment Mode'],
                        ['key' => 'payee', 'label' => 'Payee'],
                        ['key' => 'amount', 'label' => 'Amount', 'type' => 'amount'],
                    ],
                    'rows' => $ledgerRows,
                ],
            ],
        ];
    }

    private function posReport(array $dates): array
    {
        $sessionRows = collect();
        $paymentRows = collect();

        if (Schema::hasTable('pos_sessions')) {
            $sessionRows = PosSession::with(['user', 'counter'])
                ->where('financial_year', $this->financialYear())
                ->whereDate('opened_at', '>=', $dates['from'])
                ->whereDate('opened_at', '<=', $dates['to'])
                ->latest('opened_at')
                ->limit(300)
                ->get()
                ->map(fn ($session) => [
                    'opened_at' => optional($session->opened_at)->format('d-m-Y H:i'),
                    'counter' => $session->counter->name ?? $session->counter_code,
                    'user' => $session->user->name ?? '',
                    'opening_cash' => $session->opening_cash,
                    'expected_cash' => $session->expected_cash,
                    'closing_cash' => $session->closing_cash,
                    'difference' => $session->cash_difference,
                    'status' => ucfirst((string) $session->status),
                ]);
        }

        if (Schema::hasTable('pos_sale_payments')) {
            $paymentRows = PosSalePayment::query()
                ->select('method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as amount'))
                ->whereHas('sale', function ($query) use ($dates) {
                    $query->where('financial_year', $this->financialYear())
                        ->whereBetween('sa_date', [$dates['from'], $dates['to']]);
                })
                ->groupBy('method')
                ->orderBy('method')
                ->get();
        }

        $posSales = Sale::where('financial_year', $this->financialYear())
            ->where('status', '1')
            ->where('sale_origin', 'pos')
            ->whereBetween('sa_date', [$dates['from'], $dates['to']]);

        $posSaleRows = (clone $posSales)
            ->with(['customer', 'banking'])
            ->latest('sa_date')
            ->latest('sa_id')
            ->limit(300)
            ->get();

        return [
            'cards' => [
                ['label' => 'POS Sales', 'value' => (clone $posSales)->sum('sa_amount_payable'), 'type' => 'amount'],
                ['label' => 'POS Bills', 'value' => (clone $posSales)->count(), 'type' => 'number'],
                ['label' => 'Payment Total', 'value' => $paymentRows->sum('amount'), 'type' => 'amount'],
            ],
            'sections' => [
                [
                    'title' => 'Payment Summary',
                    'columns' => [
                        ['key' => 'method', 'label' => 'Method'],
                        ['key' => 'count', 'label' => 'Payments', 'type' => 'number'],
                        ['key' => 'amount', 'label' => 'Amount', 'type' => 'amount'],
                    ],
                    'rows' => $paymentRows,
                ],
                [
                    'title' => 'POS Sale Register',
                    'columns' => $this->registerColumns('Customer'),
                    'rows' => $posSaleRows->map(fn ($sale) => [
                        'date' => $sale->sa_date,
                        'vno' => $sale->sa_vno,
                        'party' => $sale->customer->customer ?? '',
                        'payable' => $sale->sa_amount_payable,
                        'paid' => $sale->sa_amount_paid,
                        'balance' => $sale->sa_balance,
                        'paymode' => $sale->banking->bk_bank ?? '',
                        '_actions' => $this->transactionActions('sales', $sale->sa_id, ['show', 'edit', 'print']),
                    ]),
                ],
                [
                    'title' => 'POS Sessions',
                    'columns' => [
                        ['key' => 'opened_at', 'label' => 'Opened At'],
                        ['key' => 'counter', 'label' => 'Counter'],
                        ['key' => 'user', 'label' => 'User'],
                        ['key' => 'opening_cash', 'label' => 'Opening Cash', 'type' => 'amount'],
                        ['key' => 'expected_cash', 'label' => 'Expected Cash', 'type' => 'amount'],
                        ['key' => 'closing_cash', 'label' => 'Closing Cash', 'type' => 'amount'],
                        ['key' => 'difference', 'label' => 'Difference', 'type' => 'amount'],
                        ['key' => 'status', 'label' => 'Status'],
                    ],
                    'rows' => $sessionRows,
                ],
            ],
        ];
    }

    private function totals(Builder $query, string $dateField, array $dates, array $fields): array
    {
        $statusField = $query->getModel() instanceof Purchase ? 'pu_status' : 'status';
        $base = $query->where('financial_year', $this->financialYear())
            ->where($statusField, '1')
            ->whereBetween($dateField, [$dates['from'], $dates['to']]);

        $totals = ['count' => (clone $base)->count()];

        foreach ($fields as $key => $field) {
            $totals[$key] = (float) (clone $base)->sum($field);
        }

        return $totals;
    }

    private function transactionCards(array $totals): array
    {
        return [
            ['label' => 'Bills', 'value' => $totals['count'], 'type' => 'number'],
            ['label' => 'Amount Payable', 'value' => $totals['payable'] ?? 0, 'type' => 'amount'],
            ['label' => 'Tax', 'value' => $totals['tax'] ?? 0, 'type' => 'amount'],
            ['label' => 'Amount Paid', 'value' => $totals['paid'] ?? 0, 'type' => 'amount'],
            ['label' => 'Balance', 'value' => $totals['balance'] ?? 0, 'type' => 'amount'],
        ];
    }

    private function summaryColumns(): array
    {
        return [
            ['key' => 'module', 'label' => 'Module'],
            ['key' => 'count', 'label' => 'Entries', 'type' => 'number'],
            ['key' => 'tax', 'label' => 'Tax', 'type' => 'amount'],
            ['key' => 'payable', 'label' => 'Amount Payable', 'type' => 'amount'],
            ['key' => 'paid', 'label' => 'Paid', 'type' => 'amount'],
            ['key' => 'balance', 'label' => 'Balance', 'type' => 'amount'],
        ];
    }

    private function summaryRow(string $module, array $totals): array
    {
        return [
            'module' => $module,
            'count' => $totals['count'] ?? 0,
            'tax' => $totals['tax'] ?? 0,
            'payable' => $totals['payable'] ?? $totals['amount'] ?? 0,
            'paid' => $totals['paid'] ?? 0,
            'balance' => $totals['balance'] ?? 0,
        ];
    }

    private function registerColumns(string $partyLabel, array $extra = [], bool $includePaid = true): array
    {
        $columns = [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'vno', 'label' => 'Bill No.'],
        ];

        foreach ($extra as $key => $label) {
            $columns[] = ['key' => $key, 'label' => $label];
        }

        $columns[] = ['key' => 'party', 'label' => $partyLabel];
        $columns[] = ['key' => 'payable', 'label' => 'Amount Payable', 'type' => 'amount'];

        if ($includePaid) {
            $columns[] = ['key' => 'paid', 'label' => 'Paid', 'type' => 'amount'];
            $columns[] = ['key' => 'balance', 'label' => 'Balance', 'type' => 'amount'];
            $columns[] = ['key' => 'paymode', 'label' => 'Payment Mode'];
        }

        return $columns;
    }

    private function outstandingColumns(string $partyLabel): array
    {
        return [
            ['key' => 'date', 'label' => 'Bill Date', 'type' => 'date'],
            ['key' => 'vno', 'label' => 'Bill No.'],
            ['key' => 'party', 'label' => $partyLabel],
            ['key' => 'due_date', 'label' => 'Due Date', 'type' => 'date'],
            ['key' => 'payable', 'label' => 'Amount Payable', 'type' => 'amount'],
            ['key' => 'paid', 'label' => 'Paid', 'type' => 'amount'],
            ['key' => 'balance', 'label' => 'Balance', 'type' => 'amount'],
        ];
    }

    private function partyColumns(string $partyLabel): array
    {
        return [
            ['key' => 'party', 'label' => $partyLabel],
            ['key' => 'count', 'label' => 'Bills', 'type' => 'number'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'amount'],
        ];
    }

    private function itemColumns(array $extra = []): array
    {
        $columns = [];

        foreach ($extra as $key => $label) {
            $columns[] = ['key' => $key, 'label' => $label];
        }

        return array_merge($columns, [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'item', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'tax', 'label' => 'Tax', 'type' => 'amount'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'amount'],
        ]);
    }

    private function partySummary(string $table, string $partyField, string $partyTable, string $nameField, string $dateField, string $amountField, array $dates, string $statusField = 'status')
    {
        $partyAlias = $partyTable === 'customer' ? 'c' : 'v';
        $nameColumn = $partyAlias . '.' . $nameField;

        return DB::table($table . ' as h')
            ->leftJoin($partyTable . ' as ' . $partyAlias, $partyAlias . '.id', '=', 'h.' . $partyField)
            ->where('h.financial_year', $this->financialYear())
            ->where('h.' . $statusField, '1')
            ->whereBetween('h.' . $dateField, [$dates['from'], $dates['to']])
            ->groupBy('h.' . $partyField, $nameColumn)
            ->select($nameColumn . ' as party', DB::raw('COUNT(*) as count'), DB::raw('SUM(h.' . $amountField . ') as amount'))
            ->orderByDesc('amount')
            ->limit(300)
            ->get();
    }

    private function itemSummary(
        string $detailTable,
        string $headerTable,
        string $headerForeignKey,
        string $headerPrimaryKey,
        string $itemField,
        string $qtyField,
        string $amountField,
        ?string $taxField,
        string $dateField,
        array $dates,
        ?array $detailFilter = null,
        string $headerStatusField = 'status'
    ) {
        $query = DB::table($detailTable . ' as d')
            ->join($headerTable . ' as h', 'h.' . $headerPrimaryKey, '=', 'd.' . $headerForeignKey)
            ->leftJoin('product as p', 'p.product_code', '=', 'd.' . $itemField)
            ->where('h.financial_year', $this->financialYear())
            ->where('h.' . $headerStatusField, '1')
            ->where('d.status', '1')
            ->whereBetween('h.' . $dateField, [$dates['from'], $dates['to']]);

        if ($detailFilter) {
            $query->where('d.' . $detailFilter[0], $detailFilter[1]);
        }

        $taxExpression = $taxField ? 'SUM(d.' . $taxField . ')' : '0';

        return $query
            ->groupBy('d.' . $itemField, 'p.product')
            ->select(
                'd.' . $itemField . ' as code',
                DB::raw('COALESCE(p.product, d.' . $itemField . ') as item'),
                DB::raw('SUM(d.' . $qtyField . ') as qty'),
                DB::raw($taxExpression . ' as tax'),
                DB::raw('SUM(d.' . $amountField . ') as amount')
            )
            ->orderByDesc('amount')
            ->limit(300)
            ->get();
    }

    private function returnItemSummary(array $dates)
    {
        return DB::table('preturn_details as d')
            ->join('preturn as h', 'h.pr_id', '=', 'd.prd_prid')
            ->leftJoin('product as p', 'p.product_code', '=', 'd.prd_itemid')
            ->where('h.financial_year', $this->financialYear())
            ->where('h.status', '1')
            ->where('d.status', '1')
            ->whereBetween('h.pr_date', [$dates['from'], $dates['to']])
            ->groupBy('h.pr_type', 'd.prd_itemid', 'p.product')
            ->select(
                DB::raw("CASE WHEN h.pr_type = '1' THEN 'Purchase Return' ELSE 'Sale Return' END as return_type"),
                'd.prd_itemid as code',
                DB::raw('COALESCE(p.product, d.prd_itemid) as item'),
                DB::raw('SUM(d.prd_itemqty) as qty'),
                DB::raw('SUM(d.prd_gst) as tax'),
                DB::raw('SUM(d.prd_total) as amount')
            )
            ->orderByDesc('amount')
            ->limit(300)
            ->get();
    }

    private function transactionActions(string $resource, $id, array $actions = ['show', 'edit', 'print']): array
    {
        if (empty($id)) {
            return [];
        }

        $meta = [
            'show' => ['label' => 'View', 'icon' => 'far fa-eye', 'class' => 'btn-info'],
            'edit' => ['label' => 'Edit', 'icon' => 'far fa-edit', 'class' => 'btn-primary'],
            'print' => ['label' => 'Print', 'icon' => 'fas fa-print', 'class' => 'btn-warning'],
        ];

        return collect($actions)
            ->map(function ($action) use ($resource, $id, $meta) {
                $route = $resource . '.' . $action;

                if (! Route::has($route) || ! isset($meta[$action])) {
                    return null;
                }

                return array_merge($meta[$action], [
                    'url' => route($route, $id),
                ]);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function ledgerTransactionActions(LedgerBook $entry): array
    {
        if (empty($entry->lb_vid)) {
            return [];
        }

        $resource = $this->ledgerTransactionResource($entry);

        if (! $resource) {
            return [];
        }

        $actions = $resource === 'loans'
            ? ['show']
            : ['show', 'edit', 'print'];

        return $this->transactionActions($resource, $entry->lb_vid, $actions);
    }

    private function dailyLedgerTransactionUrl(LedgerBook $entry): ?string
    {
        if (! empty($entry->lb_vid)) {
            $resource = $this->ledgerTransactionResource($entry);
            $routeName = $resource ? $resource . '.show' : null;

            return $routeName && Route::has($routeName)
                ? route($routeName, $entry->lb_vid)
                : null;
        }

        $partyResource = match ($entry->lb_type) {
            'sp', 'svp', 'esp', 'srp', 'loan_asset' => 'customers',
            'pp', 'prp', 'loan' => 'vendors',
            default => null,
        };

        if (! $partyResource || empty($entry->lb_payee)) {
            return null;
        }

        $routeName = $partyResource . '.show';

        return Route::has($routeName)
            ? route($routeName, $entry->lb_payee)
            : null;
    }

    private function ledgerTransactionResource(LedgerBook $entry): ?string
    {
        return match ($entry->lb_type) {
            's', 'sp' => 'sales',
            'p', 'pp' => 'purchases',
            'sv', 'svp' => 'services',
            'es', 'esp' => 'estimations',
            'sr', 'srp' => 'sales-returns',
            'pr', 'prp' => 'purchase-returns',
            'exp' => 'expenses',
            'loan', 'loan_asset' => 'loans',
            default => null,
        };
    }

    private function ledgerTypeLabel(string $type): string
    {
        return [
            's' => 'Sale',
            'sp' => 'Sale Payment',
            'sr' => 'Sale Return',
            'srp' => 'Sale Return Payment',
            'p' => 'Purchase',
            'pp' => 'Purchase Payment',
            'pr' => 'Purchase Return',
            'prp' => 'Purchase Return Payment',
            'sv' => 'Service',
            'svp' => 'Service Payment',
            'es' => 'Estimation',
            'esp' => 'Estimation Payment',
            'exp' => 'Expense',
            'loan' => 'Loan',
            'loan_asset' => 'Loan Asset',
            'tran' => 'Bank Transfer',
        ][$type] ?? strtoupper($type);
    }
}