<?php

namespace Modules\Finance\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Contacts\app\Models\Customer;
use Modules\Contacts\app\Models\Vendor;
use Modules\Finance\app\Models\Balance;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\DailyBank;
use Modules\Finance\app\Models\LedgerBook;
use Modules\Finance\app\Models\Loan;

class LoanController extends Controller
{
    public function index()
    {
        return view('finance::loans.index', [
            'loans' => Loan::with(['vendor', 'customer', 'banking'])->where('status', '1')->latest('id')->get(),
            'page_title' => 'Loan List',
        ]);
    }

    public function create()
    {
        return view('finance::loans.create', [
            'page_title' => 'Create Loan',
            'voucher_no' => Loan::getVoucherCode(),
            'vendors' => Vendor::where('status', '1')->orderBy('cp_name')->get(),
            'customers' => Customer::where('status', '1')->orderBy('customer')->get(),
            'banks' => Banking::orderBy('bk_bank')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'loan_vno' => 'required|unique:loans,loan_vno',
            'loan_date' => 'required|date_format:d/m/Y',
            'loan_type' => 'required|in:liability,asset',
            'vendor_id' => 'required_if:loan_type,liability|nullable|exists:vendor,id',
            'customer_id' => 'required_if:loan_type,asset|nullable|exists:customer,id',
            'bank_id' => 'required|exists:banking,bk_id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();

        try {
            $loanDate = Carbon::createFromFormat('d/m/Y', $validated['loan_date'])->format('Y-m-d');
            $loan = Loan::create([
                'loan_vno' => $validated['loan_vno'],
                'loan_date' => $loanDate,
                'loan_type' => $validated['loan_type'],
                'vendor_id' => $validated['loan_type'] == 'liability' ? $validated['vendor_id'] : null,
                'customer_id' => $validated['loan_type'] == 'asset' ? $validated['customer_id'] : null,
                'bank_id' => $validated['bank_id'],
                'amount' => $validated['amount'],
            ]);

            if ($loan->loan_type == 'liability') {
                LedgerBook::setLoanLedgerBook($loan->amount, $loanDate, $loan->id, $loan->bank_id, $loan->vendor_id, $loan->loan_vno);
                // Balance::updateCreditBalance($loanDate, $loan->bank_id, $loan->amount);
                Banking::updateCreditBanking($loan->bank_id, $loan->amount, $loanDate);
            } else {
                LedgerBook::setCustomerLoanLedgerBook($loan->amount, $loanDate, $loan->id, $loan->bank_id, $loan->customer_id, $loan->loan_vno);
                // Balance::updateDebitBalance($loanDate, $loan->bank_id, $loan->amount);
                Banking::updateDebitBanking($loan->bank_id, $loan->amount, $loanDate);
            }

            DB::commit();
            return redirect()->route('loans.show', $loan->id)->with('success', 'Loan saved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $loan = Loan::with(['vendor', 'customer', 'banking'])->findOrFail($id);
        $loanLedgers = LedgerBook::with('banking')
            ->where('lb_vid', $loan->id)
            ->whereIn('lb_type', ['loan', 'loan_asset'])
            ->orderBy('lb_date')
            ->orderBy('lb_id')
            ->get();

        return view('finance::loans.view', [
            'loan' => $loan,
            'loanLedgers' => $loanLedgers,
            'page_title' => 'View Loan',
        ]);
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            $loan = Loan::findOrFail($id);

            if ($loan->loan_type == 'asset') {
                LedgerBook::releaseCustomerPaymentAllocationsForSource($loan->customer_id, $loan->id, ['loan_asset']);
            }

            LedgerBook::where('lb_vid', $loan->id)
                ->where('lb_type', $loan->loan_type == 'asset' ? 'loan_asset' : 'loan')
                ->update(['status' => '0']);

            if ($loan->loan_type == 'liability') {
                // Balance::updateDebitBalance($loan->loan_date, $loan->bank_id, $loan->amount);
                Banking::updateDebitBanking($loan->bank_id, $loan->amount, $loan->loan_date);
            } else {
                // Balance::updateCreditBalance($loan->loan_date, $loan->bank_id, $loan->amount);
                Banking::updateCreditBanking($loan->bank_id, $loan->amount, $loan->loan_date);
            }
            $loan->status = '0';
            $loan->save();
        });

        return redirect()->route('loans.index')->with('success', 'Loan deleted successfully.');
    }
}
