<?php

namespace Modules\Sale\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;
use Modules\Contacts\app\Models\Customer;
use Modules\Contacts\app\Models\CustomerPaymentAllocation;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\LedgerBook;
use Modules\Pos\app\Models\PosSalePayment;
use Modules\Sale\app\Models\SaleDetail;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\User;

// use Modules\Sale\Database\Factories\SaleFactory;

class Sale extends Model
{
    use HasFactory;
    protected $primaryKey   = 'sa_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['company_id', 'financial_year_id', 'invoice_no', 'sa_vno', 'sa_eway_bill_no', 'sa_date','sa_customer','sa_loc','sa_vehicle','sa_amount','sa_gst','sa_discount','sa_grandtotal','sa_amount_payable','sa_round','sa_amount_paid','sa_balance','sa_due_days','sa_due_date','sa_paymode','sa_paid','sa_type', 'sa_remark','sa_user','pos_session_id','sale_origin','status', 'sa_state_code', 'sa_is_igst'];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'sa_customer');
    }

    public function banking()
    {
        return $this->belongsTo(Banking::class, 'sa_paymode', 'bk_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'sa_user');
    }
    
    public function saleDetails()
    {
        return $this->hasMany(SaleDetail::class, 'sad_sid', 'sa_id');
    }

    public function posPayments()
    {
        return $this->hasMany(PosSalePayment::class, 'sale_id', 'sa_id');
    }

    public function dueBalance()
    {
        $saleLedger = LedgerBook::where('lb_vid', $this->sa_id)
            ->where('lb_type', 's')
            ->where('lb_payee', $this->sa_customer)
            ->where('status', '1')
            ->first();

        if (!$saleLedger) {
            return max((float) $this->sa_balance, 0);
        }

        $directPaid = (float) LedgerBook::where('lb_vid', $this->sa_id)
            ->where('lb_type', 'sp')
            ->where('lb_payee', $this->sa_customer)
            ->where('status', '1')
            ->sum('lb_amount');

        $allocated = 0;
        if (Schema::hasTable('customer_payment_allocations')) {
            $allocated = (float) CustomerPaymentAllocation::where('source_ledgerbook_id', $saleLedger->lb_id)->sum('amount');
        }

        return round(max((float) $saleLedger->lb_tramount - $directPaid - $allocated, 0), 2);
    }

    public static function overduePaymentNotifications($limit = null)
    {
        if (!Schema::hasColumn('sales', 'sa_due_date')) {
            return collect();
        }

        $sales = static::with('customer')
            ->where('status', '1')
            ->whereNotNull('sa_due_date')
            ->whereDate('sa_due_date', '<=', now()->toDateString())
            ->orderBy('sa_due_date')
            ->orderBy('sa_id')
            ->get()
            ->map(function ($sale) {
                $sale->due_balance = $sale->dueBalance();
                return $sale;
            })
            ->filter(fn ($sale) => $sale->due_balance > 0)
            ->values();

        return $limit ? $sales->take($limit) : $sales;
    }

    protected static function booted()
    {
        static::saving(function ($sale) {
            $financialYear = self::getFinancialYear($sale->sa_date ?? now());
            $sale->financial_year = $financialYear;
            $sale->financial_year_id = $sale->financial_year_id ?: $financialYear;
            $sale->invoice_no = $sale->invoice_no ?: $sale->sa_vno;
            $sale->company_id = $sale->company_id ?: Company::query()->value('id');
        });
    }

    public static function getFinancialYear($date)
    {
        $year = date('Y', strtotime($date));
        $month = date('m', strtotime($date));

        if ($month >= 4) {
            $start = $year;
            $end = $year + 1;
        } else {
            $start = $year - 1;
            $end = $year;
        }

        return "$start-$end"; // Format: "2024-2025"
    }
}
