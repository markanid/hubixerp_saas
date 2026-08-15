<?php

namespace Modules\Finance\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Contacts\app\Models\Customer;
use Modules\Contacts\app\Models\Vendor;

class Loan extends Model
{
    use HasFactory;

    protected $table = 'loans';
    public $timestamps = false;

    protected $fillable = ['loan_vno', 'loan_date', 'loan_type', 'vendor_id', 'customer_id', 'bank_id', 'amount', 'status'];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function banking()
    {
        return $this->belongsTo(Banking::class, 'bank_id', 'bk_id');
    }

    public static function getVoucherCode()
    {
        $year = date('m') >= 4 ? date('y') : date('y') - 1;

        $loan = DB::table('loans')
            ->selectRaw('CAST(SUBSTRING(loan_vno, 5) AS SIGNED) as loan_vno')
            ->whereRaw('SUBSTRING(loan_vno, 2, 2) = ?', [$year])
            ->latest('id')
            ->first();

        $nextVoucher = $loan ? $loan->loan_vno + 1 : 1;
        return 'L' . $year . '-' . str_pad($nextVoucher, 4, '0', STR_PAD_LEFT);
    }

    protected static function booted()
    {
        static::creating(function ($loan) {
            $loan->financial_year = self::getFinancialYear($loan->loan_date ?? now());
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

        return "$start-$end";
    }
}
