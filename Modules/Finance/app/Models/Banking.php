<?php

namespace Modules\Finance\app\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Modules\Finance\app\Services\DailyBankService;
use Modules\Finance\app\Models\DailyBank;
use Modules\Master\app\Models\Brand;
use Modules\Purchase\app\Models\Purchase;

// use Modules\Master\Database\Factories\BankingFactory;

class Banking extends Model
{
    use HasFactory;
    
    protected $table        = 'banking';
    protected $primaryKey   = 'bk_id';
    public $timestamps = false;
    
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['bk_bank', 'bk_account', 'bk_branch', 'bk_ifsc', 'bk_opbalance', 'bk_clbalance', 'bk_status'];

    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'pu_paymode', 'bk_id');
    }

    public function balances()
    {
        return $this->hasMany(Balance::class, 'balance_pmode', 'bk_id');
    }

    public function dailybanks()
    {
        return $this->hasMany(DailyBank::class, 'db_bank', 'bk_id');
    }

    public function ledgerbooks()
    {
        return $this->hasMany(LedgerBook::class, 'lb_paymode', 'bk_id');
    }

    public function expenses()
    {
        return $this->hasMany(Purchase::class, 'ex_paymode', 'bk_id');
    }

    public static function updateDebitBanking($payMode, $amountPaid, $transactionDate = null)
    {
        return self::applyBankMovement($payMode, -(float) $amountPaid, $transactionDate);
    } 
    
    public static function updateCreditBanking($payMode, $amountPaid, $transactionDate = null)
    {
        return self::applyBankMovement($payMode, (float) $amountPaid, $transactionDate);
    }

    private static function applyBankMovement($payMode, float $signedAmount, $transactionDate): ?Banking
    {
        if (!$payMode || abs($signedAmount) < 0.005) {
            return null;
        }

        return DB::transaction(function () use ($payMode, $signedAmount, $transactionDate) {
            $bank = Banking::where('bk_id', $payMode)->lockForUpdate()->first();
            if (!$bank) {
                return null;
            }

            $bank->bk_clbalance = round((float) $bank->bk_clbalance + $signedAmount, 2);
            $bank->save();

            app(DailyBankService::class)->applySignedDelta(
                (int) $bank->bk_id,
                $transactionDate ?: now()->toDateString(),
                $signedAmount,
                (float) $bank->bk_clbalance
            );

            return $bank;
        });
    }
}
