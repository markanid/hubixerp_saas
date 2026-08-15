<?php

namespace Modules\Finance\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Finance\app\Models\Balance;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\DailyBank;
use Modules\Finance\app\Models\LedgerBook;

class BankingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $banks = Banking::latest('bk_id')->get();
        if ($banks!=null && !$banks->isEmpty()) {
            $data['banks'] = $banks;
            $data['page_title'] = "Banks List";
            return view('finance::banking.index',$data);
        } else {
            return redirect()->route('banks.create');
        } 
    }

    public function createOrEdit($id = null)
    {
        $bank = $id ? Banking::findOrFail($id) : new Banking();
        $hasLedger          = false;
        if ($id) {
            $hasLedger = $this->hasTransactionHistory((int) $id);
        }

        $data['page_title'] = $id ? "Edit Bank" : "Create Bank";
        $data['bank']       = $bank;
        $data['hasLedger']  = $hasLedger;

        return view('finance::banking.create', $data);
    }

    public function storeOrUpdate(Request $request)
    {
        $validated = $request->validate([
            'bk_bank'       => 'required|string|max:255',
            'bk_account'    => 'required|string|max:255',
            'bk_branch'     => 'required|string|max:255',
            'bk_ifsc'       => 'required|string|max:255',
            'bk_opbalance'  => 'required|numeric',
        ]);

        
        if ($request->bk_id) {
            $bank = Banking::findOrFail($request->bk_id);
            $hasLedgerHistory = $this->hasTransactionHistory((int) $bank->bk_id);
            $openingDelta = round((float) $validated['bk_opbalance'] - (float) $bank->bk_opbalance, 2);

            if ($hasLedgerHistory && abs($openingDelta) >= 0.005) {
                return back()
                    ->withErrors(['bk_opbalance' => 'Opening balance cannot be changed after transactions exist.'])
                    ->withInput();
            }

            $bank->bk_bank      = $validated['bk_bank'];
            $bank->bk_account   = $validated['bk_account'];
            $bank->bk_branch    = $validated['bk_branch'];
            $bank->bk_ifsc      = $validated['bk_ifsc'];
            $bank->bk_opbalance = $validated['bk_opbalance'];
            $bank->save();

            if (abs($openingDelta) >= 0.005) {
                $effectiveDate = DailyBank::where('db_bank', $bank->bk_id)->min('db_date')
                    ?: now()->toDateString();

                if ($openingDelta > 0) {
                    Banking::updateCreditBanking($bank->bk_id, $openingDelta, $effectiveDate);
                } else {
                    Banking::updateDebitBanking($bank->bk_id, abs($openingDelta), $effectiveDate);
                }
            }

        } else {
            $bank = new Banking();

            $bank->bk_bank      = $validated['bk_bank'];
            $bank->bk_account   = $validated['bk_account'];
            $bank->bk_branch    = $validated['bk_branch'];
            $bank->bk_ifsc      = $validated['bk_ifsc'];
            $bank->bk_opbalance = $validated['bk_opbalance'];
            $bank->bk_clbalance = $validated['bk_opbalance'];
            $bank->save();
        }
        // Balance::updateDebitBalance(Carbon::today()->toDateString(),$bank->bk_id,$bank->bk_clbalance);
        DailyBank::bankClosing([(int) $bank->bk_id]);
    
        if ($bank) {
            return redirect()->route('banks.show', $bank->bk_id)->with('success', 'Bank details updated successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to update bank details.');
        }
    }

    public function show($id)
    {
        $financialYear = session('financial_year') ?: DailyBank::getFinancialYear(today());
         
        $fyParts = explode('-', $financialYear); // example: '2024-2025' → ['2024', '2025']
        
        $fyStart = Carbon::createFromDate($fyParts[0], 4, 1)->startOfDay(); // Assuming FY starts April 1
        $fyEnd   = Carbon::createFromDate($fyParts[1], 3, 31)->endOfDay();   // Ends March 31

        
        $bank = Banking::findOrFail($id);

        // Find opening balance from the latest snapshot before this FY.
        $openingBalance = DailyBank::where('db_bank', $id)
            ->where('status', '1')
            ->whereDate('db_date', '<', $fyStart->toDateString())
            ->orderByDesc('db_date')
            ->orderByDesc('db_id')
            ->value('db_amount');

        if ($openingBalance === null) {
            $openingBalance = $bank->bk_opbalance ?? 0;
        }

        $data['openingBalance'] = $openingBalance;

        // Fetch closing balance of financial year
        $closingBalance = DailyBank::where('db_bank', $id)
            ->where('status', '1')
            ->whereDate('db_date', '<=', $fyEnd->toDateString())
            ->orderByDesc('db_date')
            ->orderByDesc('db_id')
            ->value('db_amount');

        if ($closingBalance === null) {
            $closingBalance = $bank->bk_clbalance ?? 0;
        }

        $data['closingBalance'] = $closingBalance;
        
        $transactions = LedgerBook::where('status', '1')
            ->whereIn('lb_type', ['pp','prp','sp','svp','esp','srp','exp','tran','loan','loan_asset'])
            ->where(function($q) use ($id) {
                // For bank transfers, match payee
                $q->where(function($t) use ($id) {
                    $t->where('lb_type', 'tran')
                    ->where('lb_payee', $id);
                })
                // For all other types, match paymode
                ->orWhere(function($t) use ($id) {
                    $t->whereIn('lb_type', ['pp','prp','sp','svp','esp','srp','exp','tran','loan','loan_asset'])
                    ->where('lb_paymode', $id);
                });
            })
            ->where(function($query) use ($financialYear, $fyStart, $fyEnd) {
                $query->where('financial_year', $financialYear)
                    ->orWhere(function($q) use ($fyStart, $fyEnd) {
                        $q->whereNull('financial_year')
                            ->whereBetween('lb_date', [$fyStart, $fyEnd]);
                    });
            })
        ->orderBy('lb_date')
        ->orderBy('lb_id')
        ->get();
        
        $data['page_title']     = "View Bank";
        $data['bank']           = $bank;
        $data['transactions']   = $transactions;
        return view('finance::banking.view',$data);
    }

    public function transferForm()
    {
        $banks = Banking::all();
        return view('finance::banking.transfer', ['banks' => $banks, 'page_title' => 'Transfer Amount']);
    }

    public function transferAmount(Request $request)
    {
        $validated = $request->validate([
            'from_bank' => 'required|exists:banking,bk_id',
            'to_bank'   => 'required|exists:banking,bk_id|different:from_bank',
            'amount'    => 'required|numeric|min:0.01',
            'remarks'   => 'nullable|string|max:255',
        ]);

        $amount = (float) $validated['amount'];
        $transferDate = now()->toDateString();

        DB::transaction(function () use ($validated, $amount, $transferDate): void {
            $banks = Banking::whereIn('bk_id', [$validated['from_bank'], $validated['to_bank']])
                ->orderBy('bk_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('bk_id');
            $fromBank = $banks->get((int) $validated['from_bank']);
            $toBank = $banks->get((int) $validated['to_bank']);

            if (!$fromBank || !$toBank) {
                throw new \RuntimeException('One of the selected bank accounts is no longer available.');
            }

            LedgerBook::setBankTransferLedger($fromBank, $toBank, $amount, $transferDate);
            Banking::updateDebitBanking($fromBank->bk_id, $amount, $transferDate);
            Banking::updateCreditBanking($toBank->bk_id, $amount, $transferDate);
        });

        return redirect()->route('banks.index')->with('success', 'Amount transferred successfully.');
    }

    public function destroy($id)
    {
        $bank = Banking::findOrFail($id);

        if ($this->hasTransactionHistory((int) $id)) {
            return redirect()->route('banks.index')
                ->with('error', 'This bank has transaction history and cannot be deleted.');
        }

        DB::transaction(function () use ($bank): void {
            DailyBank::where('db_bank', $bank->bk_id)->delete();
            $bank->delete();
        });

        return redirect()->route('banks.index')->with('success', 'Record deleted successfully');
    }

    private function hasTransactionHistory(int $bankId): bool
    {
        return LedgerBook::where('lb_paymode', $bankId)
            ->orWhere(function ($query) use ($bankId): void {
                $query->where('lb_type', 'tran')->where('lb_payee', $bankId);
            })
            ->exists();
    }
}
