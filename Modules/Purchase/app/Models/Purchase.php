<?php

namespace Modules\Purchase\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Modules\Contacts\app\Models\Vendor;
use Modules\Contacts\app\Models\VendorPaymentAllocation;
use Modules\Finance\app\Models\Banking;
use Modules\Settings\app\Models\User;
use Modules\Finance\app\Models\LedgerBook;

// use Modules\Purchase\Database\Factories\PurchaseFactory;

class Purchase extends Model
{
    use HasFactory;

    protected $table        = 'purchase';
    protected $primaryKey   = 'pu_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['pu_vno','pu_date','financial_year', 'pu_type', 'pu_bill_number','pu_vendor','pu_amount','pu_gst','pu_discount','pu_grandtotal','pu_amount_payable','pu_round','pu_amount_paid','pu_balance','pu_due_days','pu_due_date','pu_paymode','pu_paid','pu_user','pu_status'];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'pu_vendor');
    }

    public function banking()
    {
        return $this->belongsTo(Banking::class, 'pu_paymode', 'bk_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'pu_user');
    }
    
    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class, 'pud_pid', 'pu_id');
    }

    public function purchaseLedger()
    {
        return $this->hasOne(LedgerBook::class, 'lb_vid', 'pu_id')->where('lb_type', 'p');
    }

    public function dueBalance()
    {
        $purchaseLedger = LedgerBook::where('lb_vid', $this->pu_id)
            ->where('lb_type', 'p')
            ->where('lb_payee', $this->pu_vendor)
            ->where('status', '1')
            ->first();

        if (!$purchaseLedger) {
            return max((float) $this->pu_balance, 0);
        }

        $directPaid = (float) LedgerBook::where('lb_vid', $this->pu_id)
            ->where('lb_type', 'pp')
            ->where('lb_payee', $this->pu_vendor)
            ->where('status', '1')
            ->sum('lb_amount');

        $allocated = 0;
        if (Schema::hasTable('vendor_payment_allocations')) {
            $allocated = (float) VendorPaymentAllocation::where('source_ledgerbook_id', $purchaseLedger->lb_id)->sum('amount');
        }

        return round(max((float) $purchaseLedger->lb_tramount - $directPaid - $allocated, 0), 2);
    }

    public static function overduePaymentNotifications($limit = null)
    {
        if (!Schema::hasColumn('purchase', 'pu_due_date')) {
            return collect();
        }

        $purchases = static::with('vendor')
            ->where('pu_status', '1')
            ->whereNotNull('pu_due_date')
            ->whereDate('pu_due_date', '<=', now()->toDateString())
            ->orderBy('pu_due_date')
            ->orderBy('pu_id')
            ->get()
            ->map(function ($purchase) {
                $purchase->due_balance = $purchase->dueBalance();
                return $purchase;
            })
            ->filter(fn ($purchase) => $purchase->due_balance > 0)
            ->values();

        return $limit ? $purchases->take($limit) : $purchases;
    }

    public static function getVoucherCode($pu_type)
    {
        $date = now()->toDateString();
        $financialYear = self::getFinancialYear($date);
        $year = substr($financialYear, 2, 2);

        // Define prefix based on type
        $prefix = $pu_type == '2' ? "P{$year}/6B-" : "P{$year}-";

        if (Schema::hasTable('purchase_voucher_sequences')) {
            $sequence = DB::table('purchase_voucher_sequences')
                ->where('financial_year', $financialYear)
                ->where('purchase_type', (string) $pu_type)
                ->value('last_number') ?? 0;

            return $prefix . str_pad(((int) $sequence) + 1, 4, '0', STR_PAD_LEFT);
        }

        // Query latest voucher number for this prefix
        $purchase = DB::table('purchase')
            ->selectRaw("CAST(REPLACE(pu_vno, ?, '') AS UNSIGNED) as series", [$prefix])
            ->where('pu_vno', 'like', $prefix . '%')
            ->latest('pu_id')
            ->first();

        $nextSeries = $purchase ? $purchase->series + 1 : 1;

        return $prefix . str_pad($nextSeries, 4, '0', STR_PAD_LEFT);
    }


    protected static function booted()
    {
        static::creating(function ($purchase) {
            $purchase->financial_year = self::getFinancialYear($purchase->pu_date ?? now());
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
