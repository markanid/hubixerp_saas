<?php

namespace Modules\Finance\app\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Finance\app\Models\Banking;
use Modules\Finance\app\Services\DailyBankService;

// use Modules\Finance\Database\Factories\DailyBankFactory;

class DailyBank extends Model
{
    use HasFactory;
    
    protected $table        = 'daily_bank';
    protected $primaryKey   = 'db_id';
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['db_date','financial_year','db_bank','db_amount','status'];

    protected $casts = [
        'db_date' => 'date',
        'db_amount' => 'decimal:2',
    ];
    
    public function banking()
    {
        return $this->belongsTo(Banking::class, 'db_bank','bk_id');
    }

    public static function bankClosing(?array $bankIds = null): void
    {
        app(DailyBankService::class)->snapshotCurrent($bankIds);
    }

    protected static function booted()
    {
        static::creating(function ($dailybank) {
            $dailybank->financial_year = self::getFinancialYear($dailybank->db_date ?? now());
        });
    }

    public static function getFinancialYear($date)
    {
        $value = Carbon::parse($date);
        $year = (int) $value->format('Y');
        $month = (int) $value->format('m');

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
