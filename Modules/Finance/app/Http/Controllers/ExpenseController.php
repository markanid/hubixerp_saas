<?php

namespace Modules\Finance\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\app\Models\Balance;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\DailyBank;
use Modules\Finance\app\Models\Expense;
use Modules\Finance\app\Models\LedgerBook;
use Modules\Finance\app\Models\StockValue;
use Modules\Master\app\Models\Excategory;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $financialYear = session('financial_year') ?: Expense::getFinancialYear(now());
        $query = Expense::with(['excategory', 'banking'])
        ->where('status', '1')
        ->where('financial_year', $financialYear)
        ->select('id', 'exp_vno', 'categoryid', 'exdate', 'amount', 'ex_paymode'); 
        
        // Parse fromtodates to from_date and to_date
        $fromDate = null;
        $toDate = null;
        $excategories   = Excategory::all();
        $banks          = Banking::all();

        if ($request->filled('fromtodates')) {
            $dates = explode(' - ', $request->fromtodates);
            if (count($dates) == 2) {
                try {
                    $fromDate = Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
                    $toDate = Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d');
                } catch (\Exception $e) {
                    // Invalid format; skip filtering
                }
            }
        }
        
        // If no date or voucher is provided, default to current month
        $isSearching = $request->filled('voucher') || $fromDate || $toDate || $request->filled('category') || $request->filled('paymode');

        if (!$isSearching) {
        $query->whereMonth('exdate', Carbon::now()->month)
              ->whereYear('exdate', Carbon::now()->year);
        } else {
            if ($request->filled('category')) {
                $query->where('categoryid', $request->category);
            }

            if ($request->filled('paymode')) {
                $query->where('ex_paymode', $request->paymode);
            }

            if ($request->filled('voucher')) {
                $query->where('exp_vno', 'like', '%' . $request->voucher . '%');
            }
    
            if ($fromDate && $toDate) {
                $query->whereBetween('exdate', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->whereDate('exdate', '>=', $fromDate);
            } elseif ($toDate) {
                $query->whereDate('exdate', '<=', $toDate);
            }
        }
    
        $expenses = $query->latest('id')->get();
        
        return view('finance::expenses.index', [
                'expenses' => $expenses,
                'excategories' => $excategories,
                'banks' => $banks,
                'page_title' => 'Expenses List',
                'is_cancelled' => false,
                'search_category' => $request->category,
                'search_paymode' => $request->paymode,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'financialYear' => $financialYear,
        ]);
    }

    public function cancelled(Request $request)
    {
        return redirect()
            ->route('expenses.index')
            ->with('info', 'Expenses are permanently deleted and do not have a cancelled list.');
    }

    public function createOrEdit($id = null)
    {
        $banks          = Banking::all();
        $defaultPaymode = $banks->first(fn ($bank) => strtolower(trim((string) $bank->bk_bank)) === 'cash')?->bk_id;
        $excategories   = Excategory::all();
        $expense        = null;
        if ($id) {
            $expense = Expense::with(['excategory','user','banking'])->findOrFail($id);
            $voucher_no = $expense->exp_vno;
            $page_title = "Edit Expense";
        } else {
            $voucher_no = Expense::getVoucherCode();
            $page_title = "Create Expense";
        }

        $data = [
            'page_title'    => $page_title,
            'voucher_no'    => $voucher_no,
            'banks'         => $banks,
            'defaultPaymode'=> $defaultPaymode,
            'excategories'  => $excategories,
            'expense'       => $expense,
        ];
        return view('finance::expenses.create', $data);
    }

    public function storeOrUpdate(Request $request)
    {
        $isUpdate = !empty($request->id);  
        $rules = [
            'exp_vno'       => 'required' . ($isUpdate ? '' : '|unique:expence,exp_vno'),
            'categoryid'    => 'required|exists:excategory,id',
            'exdate'        => 'required|date_format:d/m/Y',
            'amount'        => 'required',
            'ex_paymode'    => 'required',
            'remarks'       => 'nullable',
            'docum'         => 'nullable|mimes:jpg,jpeg,png,gif,bmp,webp,pdf|max:10240',
            'exp_user'      => 'required|exists:users,id',
        ];
        
        $request->validate($rules);
        
        try {
            DB::beginTransaction(); 
        
            $expenseDate = Carbon::createFromFormat('d/m/Y', $request->exdate)->format('Y-m-d');
            
            $expenseData = [
                'exdate'        => $expenseDate,
                'categoryid'    => $request->categoryid,
                'amount'        => $request->amount ?? 0,
                'ex_paymode'    => $request->ex_paymode,
                'remarks'       => $request->remarks,
                'exp_user'      => $request->exp_user,
            ];
            
            if ($request->hasFile('docum')) {
                $file = $request->file('docum');
                $filename = uniqid('expense_') . '.' . $file->getClientOriginalExtension();
                $file->storeAs('expense_logos', $filename, 'public'); 
                $expenseData['docum'] = $filename; 
            } 

            if ($isUpdate) {
                $expense = Expense::findOrFail($request->id);
                $oldExpenseDate = $expense->exdate;
                $oldPaymode = $expense->ex_paymode;
                $oldAmount = $expense->amount;
                if ($request->hasFile('docum') && $expense->docum) {
                    Storage::disk('public')->delete('expense_logos/' . $expense->docum);
                }
                $expense->update($expenseData);
                LedgerBook::updateExpenseLedgerBook($expense->amount, $expenseDate, $expense->id,$expense->ex_paymode);
                // Balance::updateCreditBalance($oldExpenseDate, $oldPaymode, $oldAmount);
                Banking::updateCreditBanking($oldPaymode, $oldAmount, $oldExpenseDate);
            } else {
                $expenseData['exp_vno']    = $request->exp_vno;
                $expense = Expense::create($expenseData);
                LedgerBook::setExpenseLedgerBook($expense->amount, $expenseDate, $expense->id, $expense->ex_paymode, $expense->categoryid);
            }
 
            // Balance::updateDebitBalance($expenseDate, $expense->ex_paymode, $expense->amount);
            Banking::updateDebitBanking($expense->ex_paymode, $expense->amount, $expenseDate);
            if (!$expense) {
                throw new \Exception('Failed to save expense.');
            }      
            StockValue::stockClosing();
            DB::commit();
            return redirect()->route('expenses.index')->with('success', 'Expense saved successfully!');
        } catch (\Exception $e) {
            DB::rollBack(); 
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        $expense = $id ? Expense::with(['excategory','user','banking'])->findOrFail($id) : new Expense();
        $data['is_cancelled']   = false;
        $data['page_title']     = "View Expense";
        $data['expense']        = $expense;
        return view('finance::expenses.view',$data);
    }

    public function showCancelled($id)
    {
        return redirect()
            ->route('expenses.index')
            ->with('info', 'Expenses are permanently deleted and do not have a cancelled view.');
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            $expense = Expense::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($expense->ex_paymode) {
                // Balance::updateCreditBalance($expense->exdate, $expense->ex_paymode, $expense->amount);
                Banking::updateCreditBanking($expense->ex_paymode, $expense->amount, $expense->exdate);
            }

            LedgerBook::where('lb_vid', $expense->id)
                ->where('lb_type', 'exp')
                ->delete();
                
            if (!empty($expense->docum) && Storage::disk('public')->exists('expense_logos/' . $expense->docum)) {
                Storage::disk('public')->delete('expense_logos/' . $expense->docum);
            }

            $expense->delete();

        });
        
        return redirect()->route('expenses.index')->with('success', 'Expense permanently deleted successfully.');
    }
}
