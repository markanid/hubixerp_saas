<?php

namespace Modules\Estimation\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Modules\Contacts\app\Models\Customer;
use Modules\Finance\app\Models\Banking;
use Modules\Settings\app\Models\User;

// use Modules\Estimation\Database\Factories\EstimationFactory;

class Estimation extends Model
{
    use HasFactory;

    protected $table = 'estimation';
    protected $primaryKey   = 'es_id';
    public $timestamps = false;
    protected $fillable = ['es_vno', 'es_date', 'es_customer', 'es_amount', 'es_discount', 'es_grandtotal', 'es_amount_payable', 'es_round', 'es_paymode', 'es_amount_paid', 'es_balance', 'es_due_days', 'es_due_date', 'es_paid', 'es_type', 'es_account_effect', 'es_user', 'status'];

    
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'es_customer');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'es_user');
    }

    public function banking()
    {
        return $this->belongsTo(Banking::class, 'es_paymode', 'bk_id');
    }
    
    public function estimationDetails()
    {
        return $this->hasMany(EstimationDetail::class, 'esd_sid', 'es_id');
    }

    public static function getVoucherCode()
    {
        $year = date('m') >= 4 ? date('y') : date('y') - 1;
        $prefix = 'ES' . $year . '-';
        $lastNumber = DB::table('estimation')
            ->where('es_vno', 'like', $prefix . '%')
            ->pluck('es_vno')
            ->map(fn ($voucher) => ctype_digit(substr((string) $voucher, strlen($prefix)))
                ? (int) substr((string) $voucher, strlen($prefix))
                : 0)
            ->max() ?? 0;

        return $prefix . str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }

    protected static function booted()
    {
        static::creating(function ($estimation) {
            $estimation->financial_year = self::getFinancialYear($estimation->es_date ?? now());
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
