<?php

namespace Modules\Contacts\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Contacts\app\Models\Vendor;
use Modules\Contacts\app\Models\VendorPaymentAllocation;
use Modules\Finance\app\Models\Balance;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\DailyBank;
use Modules\Finance\app\Models\LedgerBook;

class VendorController extends Controller
{
    public function index()
    {
        $vendors = Vendor::latest('id')->get();
        if ($vendors!=null && !$vendors->isEmpty()) {
            $data['vendors']   = $vendors;
            $data['page_title'] = "Vendor List";
            return view('contacts::vendors.index', $data); 
        } else {
            return redirect()->route('vendors.create');
        }
    }

    public function createOrEdit($id = null)
    {
        $vendor             = $id ? Vendor::findOrFail($id) : new Vendor();
        $hasLedger          = false;
        if ($id) {
            // Check if this customer has ledger entries
            $hasLedger =    DB::table('ledgerbook')
                            ->where('lb_payee', $id)
                            ->whereIn('lb_type', ['p', 'pr', 'pp', 'prp', 'loan'])
                            ->orderBy('lb_id', 'desc')
                            ->exists();
        }
        $data['page_title'] = $id ? "Edit Vendor" : "Create Vendor";
        $data['vendor']     = $vendor;
        $data['hasLedger']  = $hasLedger;
        return view('contacts::vendors.create', $data);
    }

    public function storeOrUpdate(Request $request)
    {
        $validated = $request->validate([
            'cp_name'       => 'required|string|max:255',
            'cp_email'      => 'nullable|string|max:255',
            'cp_phone'      => 'required|string|max:255|unique:vendor,cp_phone,' . $request->id,
            'cp_cname'      => 'nullable|string|max:255',
            'cp_cphone'     => 'nullable|string|max:255|unique:vendor,cp_cphone,' . $request->id,
            'cp_address'    => 'nullable|string|max:255',
            'cp_website'    => 'nullable|string|max:255',
            'cp_gst_no'     => 'nullable|string|max:255',
            'op_balance'    => 'nullable',
            'cp_logo'       => 'nullable|image|mimes:jpg,png,jpeg|max:300000', 
        ]);

        $isNew = empty($request->id);
        $vendor = Vendor::find($request->id);
        
        if ($request->hasFile('cp_logo')) {
            if ($vendor && $vendor->cp_logo) {  
                Storage::disk('public')->delete('vendor_logos/' . $vendor->cp_logo);
            }
            $file = $request->file('cp_logo');
            $filename = time() . '_' . $file->getClientOriginalName(); 
            $file->storeAs('vendor_logos', $filename, 'public'); 
            $validated['cp_logo'] = $filename; 
        }  
          
        $vendor = Vendor::updateOrCreate(
            ['id' => $request->id ?? null], 
            $validated
        );
    
        if ($vendor) {
            return $isNew
                ? redirect()->route('vendors.index')->with('success', 'Vendor created successfully.')
                : redirect()->route('vendors.show', $vendor->id)->with('success', 'Vendor details updated successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to update vendor details.');
        }
    }

    public function show($id)
    {
        $financialYear = session('financial_year') ?: LedgerBook::getFinancialYear(now());

        $vendor = Vendor::findOrFail($id);

        // ✅ Get opening balance of this FY
        $openingBalance = Vendor::getOpeningBalanceForFY($id, $financialYear, $vendor->op_balance ?? 0);

        $transactions = Vendor::getVendorLedger(
            $id,
            $financialYear,
            $openingBalance
        );

        $totalClosing = $transactions->last()->calc_clbalance 
            ?? $openingBalance;

        return view('contacts::vendors.view', [
            'page_title'          => "Vendor Details",
            'vendor'              => $vendor,
            'transactions'        => $transactions,
            'totalClosing'        => $totalClosing,
            'payments'            => Banking::all(),
            'allocationEnabled'   => $this->allocationTableExists(),
            'payableTransactions' => $this->getAllocatableTransactions($id, 's'),
            'refundTransactions'  => $this->getAllocatableTransactions($id, 'r'),
            'advancePayments'     => $this->getAvailableAdvancePayments($id),
        ]);
    }

    public function payment(Request $request)
    {
        $request->validate([
            'vend_id'           => 'required|exists:vendor,id',
            'pay_date'          => 'required|date_format:d/m/Y',
            'total_amount_paid' => 'required|numeric|min:0.01',
            'paytype'           => 'required|in:r,s',
            'paymode'           => 'required|integer|exists:banking,bk_id',
            'allocations'        => 'nullable|array',
            'allocations.*'      => 'nullable|numeric|min:0',
        ]);

        $vendorId   = $request->vend_id;
        $amount     = $request->total_amount_paid;
        $payMode    = $request->paymode;
        $paytype    = $request->paytype;
        $payDate    = Carbon::createFromFormat('d/m/Y', $request->pay_date)->format('Y-m-d');
        $allocations = $this->preparePaymentAllocations($request, $vendorId, $paytype, $amount);

        DB::beginTransaction();

        try {
            $paymentLedger = LedgerBook::setVendorLedgerBook($amount, $payDate, $payMode, $paytype, $vendorId);
            foreach ($allocations as $allocation) {
                VendorPaymentAllocation::create([
                    'payment_ledgerbook_id' => $paymentLedger->lb_id,
                    'source_ledgerbook_id'  => $allocation['ledger']->lb_id,
                    'source_type'           => $allocation['ledger']->lb_type,
                    'source_id'             => $allocation['ledger']->lb_vid,
                    'amount'                => $allocation['amount'],
                ]);
            }
            if ($paytype === 'r') {
                // Balance::updateCreditBalance($payDate, $payMode, $amount);
                Banking::updateCreditBanking($payMode, $amount, $payDate);
            } else {
                // Balance::updateDebitBalance($payDate, $payMode, $amount);
                Banking::updateDebitBanking($payMode, $amount, $payDate);
            }
            DB::commit();
            return back()->with('success', 'Vendor payment success.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error occurred: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $vendor = Vendor::findOrFail($id);
        if (!empty($vendor->cp_logo) && Storage::disk('public')->exists('vendor_logos/' . $vendor->cp_logo)) {
            Storage::disk('public')->delete('vendor_logos/' . $vendor->cp_logo);
        }
        Storage::delete('public/vendor_logos/' . $vendor->cp_logo);
        $vendor->delete();
        return redirect()->route('vendors.index')->with('success', 'Record deleted successfully');
    }

    public function allocateAdvance(Request $request)
    {
        $request->validate([
            'vend_id'           => 'required|exists:vendor,id',
            'advance_ledger_id' => 'required|integer',
            'source_ledger_id'  => 'required|integer',
            'amount'            => 'required|numeric|min:0.01',
        ]);

        if (!$this->allocationTableExists()) {
            return back()->with('error', 'Payment allocation is not ready. Please run the latest database migration.');
        }

        $vendorId = (int) $request->vend_id;
        $amount = round((float) $request->amount, 2);

        $advanceLedger = LedgerBook::where('lb_payee', $vendorId)
            ->where('lb_id', $request->advance_ledger_id)
            ->where('status', '1')
            ->whereIn('lb_type', ['pp'])
            ->where('lb_vid', 0)
            ->firstOrFail();

        $sourceLedger = LedgerBook::where('lb_payee', $vendorId)
            ->where('lb_id', $request->source_ledger_id)
            ->where('status', '1')
            ->whereIn('lb_type', $this->allocationTypesForPayment('s'))
            ->firstOrFail();

        if ($amount > $this->advanceLedgerBalance($advanceLedger)) {
            return back()->with('error', 'Advance allocation amount cannot be greater than available advance.');
        }

        if ($amount > $this->sourceLedgerBalance($sourceLedger)) {
            return back()->with('error', 'Advance allocation amount cannot be greater than selected transaction balance.');
        }

        VendorPaymentAllocation::create([
            'payment_ledgerbook_id' => $advanceLedger->lb_id,
            'source_ledgerbook_id'  => $sourceLedger->lb_id,
            'source_type'           => $sourceLedger->lb_type,
            'source_id'             => $sourceLedger->lb_vid,
            'amount'                => $amount,
        ]);

        return back()->with('success', 'Advance amount applied successfully.');
    }

    public function fixLedger(Request $request)
    {
        $request->validate([
            'vend_id' => 'required|exists:vendor,id',
        ]);

        $vendId = $request->vend_id;
        $currentFY = session('financial_year') ?: LedgerBook::getFinancialYear(now());

        LedgerBook::recalculateVendorLedger($vendId, null, null, $currentFY);

        return response()->json([
            'status' => true,
            'message' => 'Ledger auto-fixed successfully!'
        ]);
    }
    
    private function preparePaymentAllocations(Request $request, int $vendorId, string $paytype, float $paymentAmount): array
    {
        $inputAllocations = collect($request->input('allocations', []))
            ->map(fn ($amount) => round((float) $amount, 2))
            ->filter(fn ($amount) => $amount > 0);

        if ($inputAllocations->isEmpty()) {
            return [];
        }

        if (!$this->allocationTableExists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'allocations' => 'Payment allocation is not ready. Please run the latest database migration.',
            ]);
        }

        $sourceLedgers = LedgerBook::where('lb_payee', $vendorId)
            ->where('status', '1')
            ->whereIn('lb_type', $this->allocationTypesForPayment($paytype))
            ->whereIn('lb_id', $inputAllocations->keys()->all())
            ->get()
            ->keyBy('lb_id');

        $prepared = [];
        $totalAllocated = 0;

        foreach ($inputAllocations as $ledgerId => $allocationAmount) {
            $ledger = $sourceLedgers->get((int) $ledgerId);
            if (!$ledger) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocations' => 'Selected transaction is not valid for this supplier payment.',
                ]);
            }

            if ($allocationAmount > $this->sourceLedgerBalance($ledger)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocations' => 'Payment amount cannot be greater than selected transaction balance.',
                ]);
            }

            $prepared[] = [
                'ledger' => $ledger,
                'amount' => $allocationAmount,
            ];
            $totalAllocated += $allocationAmount;
        }

        if (round($totalAllocated, 2) > round($paymentAmount, 2)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'allocations' => 'Selected allocation total cannot be greater than the payment amount.',
            ]);
        }

        return $prepared;
    }

    private function getAllocatableTransactions(int $vendorId, string $paytype)
    {
        if (!$this->allocationTableExists()) {
            return collect();
        }

        $allocatedByLedger = VendorPaymentAllocation::select('source_ledgerbook_id', DB::raw('SUM(amount) as allocated_amount'))
            ->groupBy('source_ledgerbook_id')
            ->pluck('allocated_amount', 'source_ledgerbook_id');

        return LedgerBook::with('banking')
            ->where('lb_payee', $vendorId)
            ->where('status', '1')
            ->whereIn('lb_type', $this->allocationTypesForPayment($paytype))
            ->where('lb_vid', '!=', 0)
            ->orderBy('lb_date')
            ->orderBy('lb_id')
            ->get()
            ->map(function ($ledger) use ($allocatedByLedger) {
                $amount = $this->sourceLedgerAmount($ledger);
                $directPaid = $this->sourceLedgerDirectPayment($ledger);
                $allocated = (float) ($allocatedByLedger[$ledger->lb_id] ?? 0);
                $ledger->allocation_amount = $amount;
                $ledger->allocation_paid = $directPaid + $allocated;
                $ledger->allocation_balance = round($amount - $directPaid - $allocated, 2);
                $ledger->allocation_label = $this->allocationLabel($ledger);
                return $ledger;
            })
            ->filter(fn ($ledger) => $ledger->allocation_balance > 0)
            ->values();
    }

    private function getAvailableAdvancePayments(int $vendorId)
    {
        if (!$this->allocationTableExists()) {
            return collect();
        }

        $allocatedByPayment = VendorPaymentAllocation::select('payment_ledgerbook_id', DB::raw('SUM(amount) as allocated_amount'))
            ->groupBy('payment_ledgerbook_id')
            ->pluck('allocated_amount', 'payment_ledgerbook_id');

        return LedgerBook::with('banking')
            ->where('lb_payee', $vendorId)
            ->where('status', '1')
            ->whereIn('lb_type', ['pp'])
            ->where('lb_vid', 0)
            ->orderBy('lb_date')
            ->orderBy('lb_id')
            ->get()
            ->map(function ($ledger) use ($allocatedByPayment) {
                $allocated = (float) ($allocatedByPayment[$ledger->lb_id] ?? 0);
                $ledger->advance_balance = round((float) $ledger->lb_amount - $allocated, 2);
                return $ledger;
            })
            ->filter(fn ($ledger) => $ledger->advance_balance > 0)
            ->values();
    }

    private function allocationTypesForPayment(string $paytype): array
    {
        return $paytype === 'r'
            ? ['pr']
            : ['p', 'loan'];
    }

    private function sourceLedgerAmount(LedgerBook $ledger): float
    {
        return (float) $ledger->lb_tramount;
    }

    private function sourceLedgerBalance(LedgerBook $ledger): float
    {
        $allocated = VendorPaymentAllocation::where('source_ledgerbook_id', $ledger->lb_id)->sum('amount');

        return round($this->sourceLedgerAmount($ledger) - $this->sourceLedgerDirectPayment($ledger) - $allocated, 2);
    }

    private function advanceLedgerBalance(LedgerBook $ledger): float
    {
        $allocated = VendorPaymentAllocation::where('payment_ledgerbook_id', $ledger->lb_id)->sum('amount');

        return round((float) $ledger->lb_amount - $allocated, 2);
    }

    private function sourceLedgerDirectPayment(LedgerBook $ledger): float
    {
        $paymentTypes = match ($ledger->lb_type) {
            'p' => ['pp'],
            'pr' => ['prp'],
            default => [],
        };

        if (empty($paymentTypes)) {
            return 0;
        }

        return (float) LedgerBook::where('lb_payee', $ledger->lb_payee)
            ->where('lb_vid', $ledger->lb_vid)
            ->where('status', '1')
            ->whereIn('lb_type', $paymentTypes)
            ->sum('lb_amount');
    }

    private function allocationLabel(LedgerBook $ledger): string
    {
        return match ($ledger->lb_type) {
            'p' => 'Purchase',
            'pr' => 'Purchase Return',
            'loan' => 'Loan',
            default => ucfirst($ledger->lb_type),
        };
    }

    private function allocationTableExists(): bool
    {
        return Schema::hasTable('vendor_payment_allocations');
    }
}
