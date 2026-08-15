<?php

namespace Modules\Finance\app\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Balance extends Model
{
    use HasFactory;
    
    protected $table        = 'balance';
    protected $primaryKey   = 'balance_id';
    public $timestamps = false;
    
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['balance_date','balance_pmode','balance_close'];
    
    public function banking()
    {
        return $this->belongsTo(Banking::class, 'balance_pmode','bk_id');
    }

    public static function updateDebitBalance($purchaseDate, $payMode, $amountPaid)
    {
        $purchaseDate   = Carbon::parse($purchaseDate)->format('Y-m-d');
        $lastBalance    = Balance::where('balance_pmode', $payMode)
                        ->whereDate('balance_date', '<=', $purchaseDate)
                        ->latest('balance_date')
                        ->latest('balance_id')
                        ->first();

        if ($lastBalance) {
            $lastDate = Carbon::parse($lastBalance->balance_date)->format('Y-m-d');

            if ($lastDate === $purchaseDate) {
                $lastBalance->balance_close -= $amountPaid;
                $lastBalance->save();
            } else {
                Balance::create([
                    'balance_date'  => $purchaseDate,
                    'balance_pmode' => $payMode,
                    'balance_close' => $lastBalance->balance_close - $amountPaid,
                ]);
            }
        } else {
            Balance::create([
                'balance_date'  => $purchaseDate,
                'balance_pmode' => $payMode,
                'balance_close' => $amountPaid,
            ]);
        }
    } 

    public static function updateCreditBalance($saleDate, $payMode, $amountPaid)
    {
        $saleDate       = Carbon::parse($saleDate)->format('Y-m-d');
        $lastBalance    = Balance::where('balance_pmode', $payMode)
                        ->whereDate('balance_date', '<=', $saleDate)
                        ->latest('balance_date')
                        ->latest('balance_id')
                        ->first();

        if ($lastBalance) {
            $lastDate = Carbon::parse($lastBalance->balance_date)->format('Y-m-d');

            if ($lastDate === $saleDate) {
                $lastBalance->balance_close += $amountPaid;
                $lastBalance->save();
            } else {
                Balance::create([
                    'balance_date'  => $saleDate,
                    'balance_pmode' => $payMode,
                    'balance_close' => $lastBalance->balance_close + $amountPaid,
                ]);
            }
        } else {
            Balance::create([
                'balance_date'  => $saleDate,
                'balance_pmode' => $payMode,
                'balance_close' => $amountPaid,
            ]);
        }
    } 

    protected static function booted()
    {
        static::creating(function ($balance) {
            $balance->financial_year = self::getFinancialYear($balance->balance_date ?? now());
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
