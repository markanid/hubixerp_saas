<?php

namespace Modules\Returns\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Contacts\app\Models\Customer;
use Modules\Contacts\app\Models\Vendor;
use Modules\Finance\app\Models\Banking;
use Modules\Settings\app\Models\User;
use Illuminate\Support\Facades\DB;

// use Modules\Returns\Database\Factories\ReturnsFactory;

class Returns extends Model
{
    use HasFactory;
    protected $table        = 'preturn';
    protected $primaryKey   = 'pr_id';
    public $timestamps = false;

    protected $fillable = ['pr_vno','pr_pvno','pr_date','pr_vendor','pr_state_code','pr_is_igst','pr_amount','pr_gst','pr_amount_payable','pr_round','pr_amount_paid','pr_balance','pr_paymode','pr_type','pr_paid','pr_user','status'];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'pr_vendor');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'pr_vendor');
    }
    
    public function banking()
    {
        return $this->belongsTo(Banking::class, 'pr_paymode', 'bk_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'pr_user');
    }
    
    public function returnDetails()
    {
        return $this->hasMany(ReturnDetail::class, 'prd_prid', 'pr_id');
    }

    public static function getVoucherCode($pr_type)
    {
        $year = date('m') >= 4 ? date('y') : date('y') - 1;
        $prefix = $pr_type == 1 ? 'PR' : 'SR';

        $return = Returns::selectRaw('CAST(SUBSTRING(pr_vno, 6) AS SIGNED) as pr_vno')
        ->where('pr_type', $pr_type)
        ->whereRaw('SUBSTRING(pr_vno, 3, 2) = ?', [$year])
        ->latest('pr_id')
        ->first();
    
        $nextVoucher = $return ? $return->pr_vno + 1 : 1;
        return $prefix . $year . '-' . str_pad($nextVoucher, 4, '0', STR_PAD_LEFT);
    }

    protected static function booted()
    {
        static::creating(function ($return) {
            $return->financial_year = self::getFinancialYear($return->pr_date ?? now());
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
