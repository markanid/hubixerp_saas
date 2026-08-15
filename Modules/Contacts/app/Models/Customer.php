<?php

namespace Modules\Contacts\app\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\app\Models\LedgerBook;
use Modules\Returns\app\Models\Returns;
use Modules\Sale\app\Models\Sale;

// use Modules\Contacts\Database\Factories\CustomerFactory;

class Customer extends Model
{
    use HasFactory;

    public const WALK_IN_NAME = 'WALK-IN';
    public const WALK_IN_PHONE = '9999999999';
    
    protected $table = 'customer';
    public $timestamps = false;
    
    protected $fillable = ['customer', 'email', 'phone', 'address', 'gstin_no', 'op_balance', 'customer_image', 'status'];

    public static function walkIn(): self
    {
        $values = [
            'customer' => self::WALK_IN_NAME,
            'status' => '1',
        ];

        if (Schema::hasColumn('customer', 'op_balance')) {
            $values['op_balance'] = 0;
        }

        $customer = static::where('customer', self::WALK_IN_NAME)->first();

        if ($customer) {
            $customer->fill($values);

            if ($customer->phone === null || $customer->phone === '') {
                $customer->phone = self::availableWalkInPhone($customer);
            }

            if ($customer->isDirty()) {
                $customer->save();
            }

            return $customer;
        }

        return static::create(array_merge(['phone' => self::availableWalkInPhone()], $values));
    }

    private static function availableWalkInPhone(?self $ignoreCustomer = null): string
    {
        for ($phone = 9999999999; $phone >= 9999999000; $phone--) {
            $exists = static::where('phone', (string) $phone)
                ->when($ignoreCustomer?->exists, function ($query) use ($ignoreCustomer) {
                    $query->where($ignoreCustomer->getKeyName(), '!=', $ignoreCustomer->getKey());
                })
                ->exists();

            if (! $exists) {
                return (string) $phone;
            }
        }

        throw new \RuntimeException('Could not reserve a numeric phone value for the walk-in customer.');
    }
    
    public function sales()
    {
        return $this->hasMany(Sale::class, 'sa_customer', 'id');
    }

    public function returns()
    {
        return $this->hasMany(Returns::class, 'pr_vendor')
            ->where('pr_type', '2');
    }

    public function ledgerbooks()
    {
        return $this->hasMany(LedgerBook::class, 'lb_payee');
    }

    public static function getCustomerLedger($custId, $financialYear, $openingBalance)
    {
        $financialYear = $financialYear ?: LedgerBook::getFinancialYear(now());
        $fyParts = explode('-', $financialYear);

        $fyStart = Carbon::createFromDate($fyParts[0], 4, 1)->startOfDay();
        $fyEnd   = Carbon::createFromDate($fyParts[1], 3, 31)->endOfDay();

        $ledgerQuery = LedgerBook::where('lb_payee', $custId)
            ->with(['banking', 'sale', 'estimation']);

        if (Schema::hasTable('customer_payment_allocations')) {
            $ledgerQuery->with('customerPaymentAllocations.sourceLedger');
        }

        $entries = $ledgerQuery
            ->whereIn('lb_type', ['s', 'sr', 'sv', 'es', 'sp', 'srp', 'svp', 'esp', 'loan_asset'])
            ->where(function($query) use ($financialYear, $fyStart, $fyEnd) {
                $query->where('financial_year', $financialYear)
                    ->orWhere(function($q) use ($fyStart, $fyEnd) {
                        $q->whereNull('financial_year')
                            ->whereBetween('lb_date', [$fyStart, $fyEnd]);
                    });
            })
            ->orderBy('lb_date')
            ->orderBy('lb_id')
            ->get();

        $runningBalance = $openingBalance;

        foreach ($entries as $entry) {

            // Opening balance
            $entry->calc_opbalance = $runningBalance;

            $debit = 0;
            $credit = 0;

            // SAME LOGIC AS BLADE
            if ($entry->status == '0' && $entry->lb_type == 'loan_asset') {
                $entry->calc_clbalance = $runningBalance;
                continue;
            }

            if ($entry->lb_type == 'loan_asset') {
                $debit = $entry->lb_tramount;
                $credit = 0;
            } elseif (in_array($entry->lb_type, ['s', 'sv', 'es', 'sp', 'svp', 'esp'])) {
                $debit  = $entry->lb_tramount;
                $credit = $entry->lb_amount;
            } elseif (in_array($entry->lb_type, ['sr', 'srp'])) {
                $debit  = $entry->lb_amount;
                $credit = $entry->lb_tramount;
            }

            // Ignore cancelled entries
            // if ($entry->status == '0') {
            //     $entry->calc_clbalance = $runningBalance;
            //     continue;
            // }

            // Closing balance calculation
            $runningBalance = $runningBalance - $credit + $debit;

            $entry->calc_clbalance = $runningBalance;
        }

        return $entries;
    }

    public static function getOpeningBalanceForFY($custId, $financialYear, $defaultOpening = 0)
    {
        return LedgerBook::getCustomerOpeningBalanceForFinancialYear(
            $custId,
            $financialYear ?: LedgerBook::getFinancialYear(now()),
            (float) $defaultOpening
        );
    }
}