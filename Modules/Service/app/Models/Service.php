<?php

namespace Modules\Service\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Contacts\app\Models\Customer;
use Modules\Contacts\app\Models\CustomerPaymentAllocation;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Models\LedgerBook;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\User;

// use Modules\Service\Database\Factories\ServiceFactory;

class Service extends Model
{
    use HasFactory;

    protected $primaryKey   = 'sv_id';
    public $timestamps = false;
    protected $fillable = ['company_id','financial_year_id','invoice_no','sv_vno','sv_date','sv_type','sv_eway_bill_no','sv_customer','sv_loc','sv_vehicle','sv_state_code','sv_is_igst','sv_remark','sv_amount','sv_gst','sv_discount','sv_grandtotal','sv_amount_payable','sv_round','sv_amount_paid','sv_balance','sv_due_days','sv_due_date','sv_paymode','sv_paid','sv_user','status'];
    
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'sv_customer');
    }

    public function banking()
    {
        return $this->belongsTo(Banking::class, 'sv_paymode', 'bk_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'sv_user');
    }
    
    public function serviceDetails()
    {
        return $this->hasMany(ServiceDetail::class, 'svd_sid', 'sv_id');
    }

    public function dueBalance()
    {
        $serviceLedger = LedgerBook::where('lb_vid', $this->sv_id)
            ->where('lb_type', 'sv')
            ->where('lb_payee', $this->sv_customer)
            ->where('status', '1')
            ->first();

        if (!$serviceLedger) {
            return max((float) $this->sv_balance, 0);
        }

        $directPaid = (float) LedgerBook::where('lb_vid', $this->sv_id)
            ->where('lb_type', 'svp')
            ->where('lb_payee', $this->sv_customer)
            ->where('status', '1')
            ->sum('lb_amount');

        $allocated = 0;
        if (Schema::hasTable('customer_payment_allocations')) {
            $allocated = (float) CustomerPaymentAllocation::where('source_ledgerbook_id', $serviceLedger->lb_id)->sum('amount');
        }

        return round(max((float) $serviceLedger->lb_tramount - $directPaid - $allocated, 0), 2);
    }

    public static function getVoucherCode()
    {
        $year = date('m') >= 4 ? date('y') : date('y') - 1;

        $service = DB::table('services')
        ->selectRaw('CAST(SUBSTRING(sv_vno, 5) AS SIGNED) as sv_vno')
        ->whereRaw('SUBSTRING(sv_vno, 2, 2) = ?', [$year])
        ->latest('sv_id')
        ->first();
    
        $nextVoucher = $service ? $service->sv_vno + 1 : 1;
        return 'R' . $year . '-' . str_pad($nextVoucher, 4, '0', STR_PAD_LEFT);
    }

    protected static function booted()
    {
        static::saving(function ($service) {
            $financialYear = self::getFinancialYear($service->sv_date ?? now());
            $service->financial_year = $financialYear;
            if (Schema::hasColumn('services', 'financial_year_id')) {
                $service->financial_year_id = $service->financial_year_id ?: $financialYear;
            }
            if (Schema::hasColumn('services', 'invoice_no')) {
                $service->invoice_no = $service->invoice_no ?: $service->sv_vno;
            }
            if (Schema::hasColumn('services', 'company_id')) {
                $service->company_id = $service->company_id ?: Company::query()->value('id');
            }
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
