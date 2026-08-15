<?php

namespace Modules\Contacts\app\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\app\Models\LedgerBook;
use Modules\Purchase\app\Models\Purchase;
use Modules\Returns\app\Models\Returns;

// use Modules\Purchase\Database\Factories\VendorFactory;

class Vendor extends Model
{
    use HasFactory;

    protected $table = 'vendor';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['cp_name','cp_email', 'cp_phone', 'cp_cname', 'cp_cphone', 'cp_address', 'cp_logo', 'cp_website', 'cp_gst_no', 'op_balance', 'status'];
    
    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'pu_vendor', 'id');
    }

    public function returns()
    {
        return $this->hasMany(Returns::class, 'pr_vendor')
            ->where('pr_type', '1');
    }

    public function ledgerbooks()
    {
        return $this->hasMany(LedgerBook::class, 'lb_payee');
    }
    

    public static function getVendorLedger($vendorId, $financialYear, $openingBalance)
    {
        $financialYear = $financialYear ?: LedgerBook::getFinancialYear(now());
        $fyParts = explode('-', $financialYear);

        $fyStart = Carbon::createFromDate($fyParts[0], 4, 1)->startOfDay();
        $fyEnd   = Carbon::createFromDate($fyParts[1], 3, 31)->endOfDay();

        $ledgerQuery = LedgerBook::where('lb_payee', $vendorId)
            ->with(['banking', 'purchase']);

        if (Schema::hasTable('vendor_payment_allocations')) {
            $ledgerQuery->with('vendorPaymentAllocations.sourceLedger');
        }

        $entries = $ledgerQuery
            ->whereIn('lb_type', ['p', 'pr', 'pp', 'prp', 'loan'])
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

        $runningBalance = $openingBalance;

        foreach ($entries as $entry) {

            // Opening balance
            $entry->calc_opbalance = $runningBalance;

            $debit = 0;
            $credit = 0;

            // SAME LOGIC AS BLADE
            if ($entry->status == '0' && $entry->lb_type == 'loan') {
                $entry->calc_clbalance = $runningBalance;
                continue;
            }

            if ($entry->lb_type == 'loan') {
                $debit = $entry->lb_tramount;
                $credit = 0;
            } elseif (in_array($entry->lb_type, ['p', 'pp'])) {
                $debit  = $entry->lb_tramount;
                $credit = $entry->lb_amount;
            } elseif (in_array($entry->lb_type, ['pr', 'prp'])) {
                $debit  = $entry->lb_amount;
                $credit = $entry->lb_tramount;
            }

            // Ignore cancelled entries
            // if ($entry->status == '0') {
            //     $entry->calc_clbalance = $runningBalance;
            //     continue;
            // }

            // Closing balance calculation
            $runningBalance = $runningBalance - $credit + $debit;

            $entry->calc_clbalance = $runningBalance;
        }

        return $entries;
    }

    public static function getOpeningBalanceForFY($vendorId, $financialYear, $defaultOpening = 0)
    {
        return LedgerBook::getVendorOpeningBalanceForFinancialYear(
            $vendorId,
            $financialYear ?: LedgerBook::getFinancialYear(now()),
            (float) $defaultOpening
        );
    }
}