<?php

namespace Modules\Finance\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Modules\Finance\app\Models\Banking;
use Modules\Master\app\Models\Excategory;
use Modules\Settings\app\Models\User;

// use Modules\Finance\Database\Factories\ExpenseFactory;

class Expense extends Model
{
    use HasFactory;
    protected $table = 'expence';
    public $timestamps = false;
    protected $fillable = ['exp_vno', 'categoryid', 'exdate', 'amount', 'ex_paymode', 'remarks', 'docum', 'exp_user', 'status'];

    public function excategory()
    {
        return $this->belongsTo(Excategory::class, 'categoryid');
    }
    
    public function banking()
    {
        return $this->belongsTo(Banking::class, 'ex_paymode','bk_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'exp_user');
    }

    public static function getVoucherCode()
    {
        $year = date('m') >= 4 ? date('y') : date('y') - 1;

        $expense = DB::table('expence')
        ->selectRaw('CAST(SUBSTRING(exp_vno, 5) AS SIGNED) as exp_vno')
        ->whereRaw('SUBSTRING(exp_vno, 2, 2) = ?', [$year])
        ->latest('id')
        ->first();
    
        $nextVoucher = $expense ? $expense->exp_vno + 1 : 1;
        return 'E' . $year . '-' . str_pad($nextVoucher, 4, '0', STR_PAD_LEFT);
    }

    protected static function booted()
    {
        static::creating(function ($expense) {
            $expense->financial_year = self::getFinancialYear($expense->exdate ?? now());
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
