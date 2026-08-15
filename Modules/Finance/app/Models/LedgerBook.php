<?php

namespace Modules\Finance\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;
use Modules\Contacts\app\Models\Customer;
use Modules\Contacts\app\Models\CustomerPaymentAllocation;
use Modules\Contacts\app\Models\Vendor;
use Modules\Contacts\app\Models\VendorPaymentAllocation;
use Modules\Estimation\app\Models\Estimation;
use Modules\Finance\app\Jobs\RecalculateVendorLedgerJob;
use Modules\Master\app\Models\Excategory;
use Modules\Purchase\app\Models\Purchase;
use Modules\Sale\app\Models\Sale;

// use Modules\Finance\Database\Factories\LedgerBookFactory;

class LedgerBook extends Model
{
    use HasFactory;

    private const CUSTOMER_LEDGER_TYPES = ['s', 'sr', 'sv', 'es', 'sp', 'srp', 'svp', 'esp', 'loan_asset'];
    private const VENDOR_LEDGER_TYPES = ['p', 'pr', 'pp', 'prp', 'loan'];
    
    protected $table        = 'ledgerbook';
    protected $primaryKey   = 'lb_id';
    public $timestamps = false;
    
    protected $fillable = ['lb_vno','lb_vid','lb_date','lb_type','lb_amount','lb_opbalance','lb_tramount','lb_clbalance','lb_paymode','lb_payee','status','financial_year'];
    
    public function banking()
    {
        return $this->belongsTo(Banking::class, 'lb_paymode','bk_id');
    }

    public function payeeBank()
    {
        return $this->belongsTo(Banking::class, 'lb_payee', 'bk_id');
    }
    
    public function excategory()
    {
        return $this->belongsTo(Excategory::class, 'lb_payee');
    }
    
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'lb_payee');
    }
    
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'lb_payee');
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'lb_vid', 'pu_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'lb_vid', 'sa_id');
    }

    public function estimation()
    {
        return $this->belongsTo(Estimation::class, 'lb_vid', 'es_id');
    }

    public function customerPaymentAllocations()
    {
        return $this->hasMany(CustomerPaymentAllocation::class, 'payment_ledgerbook_id', 'lb_id');
    }

    public function customerSourceAllocations()
    {
        return $this->hasMany(CustomerPaymentAllocation::class, 'source_ledgerbook_id', 'lb_id');
    }

    public function vendorPaymentAllocations()
    {
        return $this->hasMany(VendorPaymentAllocation::class, 'payment_ledgerbook_id', 'lb_id');
    }

    public function vendorSourceAllocations()
    {
        return $this->hasMany(VendorPaymentAllocation::class, 'source_ledgerbook_id', 'lb_id');
    }

    public static function releaseCustomerPaymentAllocationsForSource($customerId, $entryId, array $sourceTypes): void
    {
        if (!Schema::hasTable('customer_payment_allocations')) {
            return;
        }

        $sourceLedgerIds = static::where('lb_payee', $customerId)
            ->where('lb_vid', $entryId)
            ->whereIn('lb_type', $sourceTypes)
            ->pluck('lb_id');

        if ($sourceLedgerIds->isEmpty()) {
            return;
        }

        CustomerPaymentAllocation::whereIn('source_ledgerbook_id', $sourceLedgerIds)->delete();
    }

    public static function releaseVendorPaymentAllocationsForSource($vendorId, $entryId, array $sourceTypes): void
    {
        if (!Schema::hasTable('vendor_payment_allocations')) {
            return;
        }

        $sourceLedgerIds = static::where('lb_payee', $vendorId)
            ->where('lb_vid', $entryId)
            ->whereIn('lb_type', $sourceTypes)
            ->pluck('lb_id');

        if ($sourceLedgerIds->isEmpty()) {
            return;
        }

        VendorPaymentAllocation::whereIn('source_ledgerbook_id', $sourceLedgerIds)->delete();
    }

    protected static function activeFinancialYear($date = null): string
    {
        return session('financial_year') ?: self::getFinancialYear($date ?? now());
    }

    protected static function financialYearBounds(string $financialYear): array
    {
        $parts = explode('-', $financialYear);
        $startYear = (int) ($parts[0] ?? date('Y'));
        $endYear = (int) ($parts[1] ?? ($startYear + 1));

        return [
            sprintf('%04d-04-01', $startYear),
            sprintf('%04d-03-31', $endYear),
        ];
    }

    protected static function scopeFinancialYear($query, string $financialYear)
    {
        [$startDate, $endDate] = self::financialYearBounds($financialYear);

        return $query->where(function ($q) use ($financialYear, $startDate, $endDate) {
            $q->where('financial_year', $financialYear)
                ->orWhere(function ($legacy) use ($startDate, $endDate) {
                    $legacy->whereNull('financial_year')
                        ->whereBetween('lb_date', [$startDate, $endDate]);
                });
        });
    }

    protected static function openingBalanceForFinancialYear($payeeId, array $types, string $financialYear, float $defaultOpening): float
    {
        [$startDate] = self::financialYearBounds($financialYear);

        $previousEntry = static::where('lb_payee', $payeeId)
            ->whereIn('lb_type', $types)
            ->where('lb_date', '<', $startDate)
            ->orderByDesc('lb_date')
            ->orderByDesc('lb_id')
            ->first();

        return $previousEntry ? (float) $previousEntry->lb_clbalance : $defaultOpening;
    }

    protected static function openingBalanceBeforeEntry($payeeId, array $types, $entryDate, string $financialYear, float $defaultOpening, $beforeId = null): float
    {
        $previousQuery = static::where('lb_payee', $payeeId)
            ->whereIn('lb_type', $types);

        self::scopeFinancialYear($previousQuery, $financialYear);

        $previousQuery->where(function ($query) use ($entryDate, $beforeId) {
            $query->where('lb_date', '<', $entryDate)
                ->orWhere(function ($sameDate) use ($entryDate, $beforeId) {
                    $sameDate->where('lb_date', '=', $entryDate);

                    if ($beforeId) {
                        $sameDate->where('lb_id', '<', $beforeId);
                    }
                });
        });

        $previousEntry = $previousQuery
            ->orderByDesc('lb_date')
            ->orderByDesc('lb_id')
            ->first();

        return $previousEntry
            ? (float) $previousEntry->lb_clbalance
            : self::openingBalanceForFinancialYear($payeeId, $types, $financialYear, $defaultOpening);
    }

    public static function getCustomerOpeningBalanceForFinancialYear($custId, ?string $financialYear = null, float $defaultOpening = 0): float
    {
        $financialYear = $financialYear ?: self::activeFinancialYear();

        return self::openingBalanceForFinancialYear($custId, self::CUSTOMER_LEDGER_TYPES, $financialYear, $defaultOpening);
    }

    public static function getVendorOpeningBalanceForFinancialYear($vendorId, ?string $financialYear = null, float $defaultOpening = 0): float
    {
        $financialYear = $financialYear ?: self::activeFinancialYear();

        return self::openingBalanceForFinancialYear($vendorId, self::VENDOR_LEDGER_TYPES, $financialYear, $defaultOpening);
    }

    public static function setPurchaseLedgerBook($amountPayable, $amountPaid, $purchaseDate, $purchaseId, $puPaymode, $vendId, $purchaseVoucher)
    {
        // 1) Generate voucher number
        $latestVno = static::whereIn('lb_type', ['exp', 'pp', 'srp'])
            ->latest('lb_id')
            ->first();

        $vno = $latestVno ? 'V' . str_pad((int)substr($latestVno->lb_vno, 1) + 1, 4, '0', STR_PAD_LEFT) : 'V0001';

        // 🔹 Get previous closing balance
        $financialYear = self::activeFinancialYear($purchaseDate);
        $openingBalance = self::openingBalanceBeforeEntry(
            $vendId,
            self::VENDOR_LEDGER_TYPES,
            $purchaseDate,
            $financialYear,
            (float) (Vendor::find($vendId)->op_balance ?? 0)
        );
        
        // 🔹 1. Insert PURCHASE entry
        $purchaseEntry = static::create([
            'lb_vno'        => $purchaseVoucher,
            'lb_vid'        => $purchaseId,
            'lb_date'       => $purchaseDate,
            'lb_type'       => 'p',
            'lb_amount'     => 0,
            'lb_opbalance'  => $openingBalance,
            'lb_tramount'   => $amountPayable,
            'lb_clbalance'  => $openingBalance + $amountPayable, // direct calc
            'lb_paymode'    => $puPaymode,
            'lb_payee'      => $vendId,
            'financial_year'=> $financialYear
        ]);

        $lastInsertedId = $purchaseEntry->lb_id;
        
        // 🔹 2. Insert PAYMENT entry (if exists)
        if ($amountPaid > 0) {
    
            $paymentOpening = $purchaseEntry->lb_clbalance;
    
            $paymentEntry = static::create([
                'lb_vno'        => $vno,
                'lb_vid'        => $purchaseId,
                'lb_date'       => $purchaseDate,
                'lb_type'       => 'pp',
                'lb_amount'     => $amountPaid,
                'lb_tramount'   => 0,
                'lb_opbalance'  => $paymentOpening,
                'lb_clbalance'  => $paymentOpening - $amountPaid,
                'lb_paymode'    => $puPaymode,
                'lb_payee'      => $vendId,
                'financial_year'=> $financialYear
            ]);
    
            $lastInsertedId = $paymentEntry->lb_id;
        }

        // 🔥 3. TALLY STYLE: Recalculate ONLY forward entries (if backdated)
        // self::recalculateVendorLedger($vendId, $purchaseDate, $lastInsertedId);
    }

    public static function updateVendorLedgerBook($amountPayable, $entryDate, $entryId, $paymode, $vendId, array $lbTypes)
    {
        // 🔹 1. Fetch existing entry
        $mainType = $lbTypes[0]; // 'p' or 'pr'
        $paymentType = match ($mainType) {
            'p' => 'pp',
            'pr' => 'prp',
            default => null,
        };

        $mainEntry = static::where('lb_vid', $entryId)
            ->where('lb_type', $mainType)
            ->first();
            
        if (!$mainEntry) {
            return; // Nothing to update
        }
            
        $financialYear = self::activeFinancialYear($entryDate);
        $openingBalance = self::openingBalanceBeforeEntry(
            $vendId,
            self::VENDOR_LEDGER_TYPES,
            $entryDate,
            $financialYear,
            (float) (Vendor::find($vendId)->op_balance ?? 0),
            $mainEntry->lb_id
        );
        $mainEntry->financial_year = $financialYear;

        // 3️⃣ Update the main entry
        $mainEntry->update([
            'lb_date'      => $entryDate,
            'lb_tramount'  => $amountPayable,
            'lb_opbalance' => $openingBalance,
            'lb_clbalance' => in_array($mainType, ['pr','prp'])
                        ? $openingBalance - $amountPayable // return → subtract
                        : $openingBalance + $amountPayable, // purchase → add
            'lb_paymode'   => $paymode
        ]);

        // 🔹 4. Update PAYMENT entry (if exists)
        if ($paymentType) {
            $paymentEntry = static::where('lb_vid', $entryId)
                ->where('lb_type', $paymentType)
                ->first();

            if ($paymentEntry) {
                $paymentEntry->financial_year = $financialYear;
                $paymentOpening = $mainEntry->lb_clbalance;
                $paymentEntry->update([
                    'lb_date'       => $entryDate,
                    'lb_opbalance'  => $paymentOpening,
                    'lb_clbalance'  => ($mainType == 'pr')
                        ? $paymentOpening + $paymentEntry->lb_amount
                        : $paymentOpening - $paymentEntry->lb_amount,
                    'lb_paymode'    => $paymode
                ]);
            }
        }
    
        // 5️⃣ Recalculate forward ledger entries
        // self::recalculateVendorLedger($vendId, $entryDate, $mainEntry->lb_id);
    }

    public static function setSaleLedgerBook($amountPayable, $amountPaid, $saleDate, $saleId, $saPaymode, $custId, $saleVoucher)
    {
        $latestVno = static::whereIn('lb_type', ['sp', 'prp', 'svp'])
            ->latest('lb_id')
            ->first();

        $vno = $latestVno ? 'R' . str_pad((int)substr($latestVno->lb_vno, 1) + 1, 4, '0', STR_PAD_LEFT) : 'R0001';
        
        // 🔹 Get previous closing balance
        $financialYear = self::activeFinancialYear($saleDate);
        $openingBalance = self::openingBalanceBeforeEntry(
            $custId,
            self::CUSTOMER_LEDGER_TYPES,
            $saleDate,
            $financialYear,
            (float) (Customer::find($custId)->op_balance ?? 0)
        );
        
        // 🔹 1. Insert SALE entry
        $saleEntry = static::create([
            'lb_vno'        => $saleVoucher,
            'lb_vid'        => $saleId,
            'lb_date'       => $saleDate,
            'lb_type'       => 's',
            'lb_amount'     => 0,
            'lb_opbalance'  => $openingBalance,
            'lb_tramount'   => $amountPayable,
            'lb_clbalance'  => $openingBalance + $amountPayable, // direct calc
            'lb_paymode'    => $saPaymode,
            'lb_payee'      => $custId,
            'financial_year'=> $financialYear
        ]);
        
        $lastInsertedId = $saleEntry->lb_id;

        // 🔹 2. Insert PAYMENT entry (if exists)
        if ($amountPaid > 0) {
    
            $paymentOpening = $saleEntry->lb_clbalance;
    
            $paymentEntry = static::create([
                'lb_vno'        => $vno,
                'lb_vid'        => $saleId,
                'lb_date'       => $saleDate,
                'lb_type'       => 'sp',
                'lb_amount'     => $amountPaid,
                'lb_tramount'   => 0,
                'lb_opbalance'  => $paymentOpening,
                'lb_clbalance'  => $paymentOpening - $amountPaid,
                'lb_paymode'    => $saPaymode,
                'lb_payee'      => $custId,
                'financial_year'=> $financialYear
            ]);
    
            $lastInsertedId = $paymentEntry->lb_id;
        }
        
         // 🔥 3. TALLY STYLE: Recalculate ONLY forward entries (if backdated)
        // self::recalculateCustomerLedger($custId, $saleDate, $lastInsertedId);

    }

    public static function updateCustomerLedgerBook($amountPayable, $entryDate, $entryId, $paymode, $custId, array $lbTypes)
    {
        // 🔹 1. Fetch existing entry
        $mainType = $lbTypes[0]; // 's' or 'sv'
        $paymentType = match ($mainType) {
            's' => 'sp',
            'sv' => 'svp',
            'sr' => 'srp',
            default => null,
        };

        $mainEntry = static::where('lb_vid', $entryId)
            ->where('lb_type', $mainType)
            ->first();
            
        if (!$mainEntry) {
            return; // Nothing to update
        }
            
        $financialYear = self::activeFinancialYear($entryDate);
        $openingBalance = self::openingBalanceBeforeEntry(
            $custId,
            self::CUSTOMER_LEDGER_TYPES,
            $entryDate,
            $financialYear,
            (float) (Customer::find($custId)->op_balance ?? 0),
            $mainEntry->lb_id
        );
        $mainEntry->financial_year = $financialYear;

        // 3️⃣ Update the main entry
        $mainEntry->update([
            'lb_date'      => $entryDate,
            'lb_tramount'  => $amountPayable,
            'lb_opbalance' => $openingBalance,
            'lb_clbalance' => in_array($mainType, ['sr','srp'])
                        ? $openingBalance - $amountPayable // return → subtract
                        : $openingBalance + $amountPayable, // sale/service → add
            'lb_paymode'   => $paymode
        ]);

        // 🔹 4. Update PAYMENT entry (if exists)
        if ($paymentType) {
            $paymentEntry = static::where('lb_vid', $entryId)
                ->where('lb_type', $paymentType)
                ->first();

            if ($paymentEntry) {
                $paymentEntry->financial_year = $financialYear;

                $paymentOpening = $mainEntry->lb_clbalance;
                $paymentEntry->update([
                    'lb_date'       => $entryDate,
                    'lb_opbalance'  => $paymentOpening,
                    'lb_clbalance'  => ($mainType == 'sr')
                        ? $paymentOpening + $paymentEntry->lb_amount
                        : $paymentOpening - $paymentEntry->lb_amount,
                    'lb_paymode'    => $paymode
                ]);
            }
        }
    
        // 5️⃣ Recalculate forward ledger entries
        // self::recalculateCustomerLedger($custId, $entryDate, $mainEntry->lb_id);
        
    }

    public static function setPurchaseReturnLedgerBook($amountPayable, $amountPaid, $returnDate, $returnId, $prePaymode, $vendId, $returnVoucher)
    {
        $latestVno = static::whereIn('lb_type', ['sp', 'prp', 'svp'])
            ->latest('lb_id')
            ->first();

        $vno = $latestVno ? 'R' . str_pad((int)substr($latestVno->lb_vno, 1) + 1, 4, '0', STR_PAD_LEFT) : 'R0001';

        // 🔹 Get previous closing balance
        $financialYear = self::activeFinancialYear($returnDate);
        $openingBalance = self::openingBalanceBeforeEntry(
            $vendId,
            self::VENDOR_LEDGER_TYPES,
            $returnDate,
            $financialYear,
            (float) (Vendor::find($vendId)->op_balance ?? 0)
        );

        // 🔹 1. Insert PURCHASE entry
        $returnEntry = static::create([
            'lb_vno'        => $returnVoucher,
            'lb_vid'        => $returnId,
            'lb_date'       => $returnDate,
            'lb_type'       => 'pr',
            'lb_amount'     => 0,
            'lb_opbalance'  => $openingBalance,
            'lb_tramount'   => $amountPayable,
            'lb_clbalance'  => $openingBalance - $amountPayable, // direct calc
            'lb_paymode'    => $prePaymode,
            'lb_payee'      => $vendId,
            'financial_year'=> $financialYear
        ]);

        $lastInsertedId = $returnEntry->lb_id;
        
        // 🔹 2. Insert PAYMENT entry (if exists)
        if ($amountPaid > 0) {
    
            $paymentOpening = $returnEntry->lb_clbalance;
    
            $paymentEntry = static::create([
                'lb_vno'        => $vno,
                'lb_vid'        => $returnId,
                'lb_date'       => $returnDate,
                'lb_type'       => 'prp',
                'lb_amount'     => $amountPaid,
                'lb_tramount'   => 0,
                'lb_opbalance'  => $paymentOpening,
                'lb_clbalance'  => $paymentOpening + $amountPaid,
                'lb_paymode'    => $prePaymode,
                'lb_payee'      => $vendId,
                'financial_year'=> $financialYear
            ]);
    
            $lastInsertedId = $paymentEntry->lb_id;
        }

        // 🔥 3. TALLY STYLE: Recalculate ONLY forward entries (if backdated)
        // self::recalculateVendorLedger($vendId, $returnDate, $lastInsertedId);

    }

    public static function setSaleReturnLedgerBook($amountPayable, $amountPaid, $returnDate, $returnId, $prePaymode, $custId, $returnVoucher)
    {
        $latestVno = static::whereIn('lb_type', ['exp', 'pp', 'srp'])
            ->latest('lb_id')
            ->first();

        $vno = $latestVno ? 'V' . str_pad((int)substr($latestVno->lb_vno, 1) + 1, 4, '0', STR_PAD_LEFT) : 'V0001';
        
        // 🔹 Get previous closing balance
        $financialYear = self::activeFinancialYear($returnDate);
        $openingBalance = self::openingBalanceBeforeEntry(
            $custId,
            self::CUSTOMER_LEDGER_TYPES,
            $returnDate,
            $financialYear,
            (float) (Customer::find($custId)->op_balance ?? 0)
        );
        // 🔹 1. Insert SALE entry
        $saleEntry = static::create([
            'lb_vno'        => $returnVoucher,
            'lb_vid'        => $returnId,
            'lb_date'       => $returnDate,
            'lb_type'       => 'sr',
            'lb_amount'     => 0,
            'lb_opbalance'  => $openingBalance,
            'lb_tramount'   => $amountPayable,
            'lb_clbalance'  => $openingBalance - $amountPayable, // direct calc
            'lb_paymode'    => $prePaymode,
            'lb_payee'      => $custId,
            'financial_year'=> $financialYear
        ]);
        
        $lastInsertedId = $saleEntry->lb_id;

        // 🔹 2. Insert PAYMENT entry (if exists)
        if ($amountPaid > 0) {
    
            $paymentOpening = $saleEntry->lb_clbalance;
    
            $paymentEntry = static::create([
                'lb_vno'        => $vno,
                'lb_vid'        => $returnId,
                'lb_date'       => $returnDate,
                'lb_type'       => 'srp',
                'lb_amount'     => $amountPaid,
                'lb_tramount'   => 0,
                'lb_opbalance'  => $paymentOpening,
                'lb_clbalance'  => $paymentOpening + $amountPaid,
                'lb_paymode'    => $prePaymode,
                'lb_payee'      => $custId,
                'financial_year'=> $financialYear
            ]);
    
            $lastInsertedId = $paymentEntry->lb_id;
        }
        
         // 🔥 3. TALLY STYLE: Recalculate ONLY forward entries (if backdated)
        // self::recalculateCustomerLedger($custId, $returnDate, $lastInsertedId);
    }

    public static function setServiceLedgerBook($amountPayable, $amountPaid, $serviceDate, $serviceId, $svPaymode, $custId, $serviceVoucher)
    {
        $latestVno = static::whereIn('lb_type', ['sp', 'prp', 'svp'])
            ->latest('lb_id')
            ->first();

        $vno = $latestVno ? 'R' . str_pad((int)substr($latestVno->lb_vno, 1) + 1, 4, '0', STR_PAD_LEFT) : 'R0001';
        
        // 🔹 Get previous closing balance
        $financialYear = self::activeFinancialYear($serviceDate);
        $openingBalance = self::openingBalanceBeforeEntry(
            $custId,
            self::CUSTOMER_LEDGER_TYPES,
            $serviceDate,
            $financialYear,
            (float) (Customer::find($custId)->op_balance ?? 0)
        );
        
        // 🔹 1. Insert SERVICE entry
        $serviceEntry = static::create([
            'lb_vno'        => $serviceVoucher,
            'lb_vid'        => $serviceId,
            'lb_date'       => $serviceDate,
            'lb_type'       => 'sv',
            'lb_amount'     => 0,
            'lb_opbalance'  => $openingBalance,
            'lb_tramount'   => $amountPayable,
            'lb_clbalance'  => $openingBalance + $amountPayable, // direct calc
            'lb_paymode'    => $svPaymode,
            'lb_payee'      => $custId,
            'financial_year'=> $financialYear
        ]);
        
        $lastInsertedId = $serviceEntry->lb_id;

        // 🔹 2. Insert PAYMENT entry (if exists)
        if ($amountPaid > 0) {
    
            $paymentOpening = $serviceEntry->lb_clbalance;
    
            $paymentEntry = static::create([
                'lb_vno'        => $vno,
                'lb_vid'        => $serviceId,
                'lb_date'       => $serviceDate,
                'lb_type'       => 'svp',
                'lb_amount'     => $amountPaid,
                'lb_tramount'   => 0,
                'lb_opbalance'  => $paymentOpening,
                'lb_clbalance'  => $paymentOpening - $amountPaid,
                'lb_paymode'    => $svPaymode,
                'lb_payee'      => $custId,
                'financial_year'=> $financialYear
            ]);
    
            $lastInsertedId = $paymentEntry->lb_id;
        }
        
         // 🔥 3. TALLY STYLE: Recalculate ONLY forward entries (if backdated)
        // self::recalculateCustomerLedger($custId, $serviceDate, $lastInsertedId);
    }

    public static function setEstimationLedgerBook($amountPayable, $amountPaid, $estimationDate, $estimationId, $paymode, $custId, $estimationVoucher)
    {
        $latestVno = static::whereIn('lb_type', ['sp', 'prp', 'svp', 'esp'])
            ->latest('lb_id')
            ->first();

        $vno = $latestVno ? 'R' . str_pad((int)substr($latestVno->lb_vno, 1) + 1, 4, '0', STR_PAD_LEFT) : 'R0001';

        $financialYear = self::activeFinancialYear($estimationDate);
        $openingBalance = self::openingBalanceBeforeEntry(
            $custId,
            self::CUSTOMER_LEDGER_TYPES,
            $estimationDate,
            $financialYear,
            (float) (Customer::find($custId)->op_balance ?? 0)
        );

        $estimationEntry = static::create([
            'lb_vno'        => $estimationVoucher,
            'lb_vid'        => $estimationId,
            'lb_date'       => $estimationDate,
            'lb_type'       => 'es',
            'lb_amount'     => 0,
            'lb_opbalance'  => $openingBalance,
            'lb_tramount'   => $amountPayable,
            'lb_clbalance'  => $openingBalance + $amountPayable,
            'lb_paymode'    => $paymode,
            'lb_payee'      => $custId,
            'financial_year'=> $financialYear
        ]);

        if ($amountPaid > 0) {
            $paymentOpening = $estimationEntry->lb_clbalance;

            static::create([
                'lb_vno'        => $vno,
                'lb_vid'        => $estimationId,
                'lb_date'       => $estimationDate,
                'lb_type'       => 'esp',
                'lb_amount'     => $amountPaid,
                'lb_tramount'   => 0,
                'lb_opbalance'  => $paymentOpening,
                'lb_clbalance'  => $paymentOpening - $amountPaid,
                'lb_paymode'    => $paymode,
                'lb_payee'      => $custId,
                'financial_year'=> $financialYear
            ]);
        }
    }

    public static function setExpenseLedgerBook($amount, $expenseDate, $expenseId, $expPaymode, $catId)
    {
        $latestVno = static::whereIn('lb_type', ['p', 'sr', 'exp', 'pp', 'srp'])
            ->latest('lb_id')
            ->first();

        $vno = $latestVno ? 'V' . str_pad((int)substr($latestVno->lb_vno, 1) + 1, 4, '0', STR_PAD_LEFT) : 'V0001';

         // Insert without op/cl balance first
        static::create([
            'lb_vno'        => $vno,
            'lb_vid'        => $expenseId,
            'lb_date'       => $expenseDate,
            'lb_type'       => 'exp',
            'lb_amount'     => $amount,
            'lb_opbalance'  => 0,
            'lb_tramount'   => $amount,
            'lb_clbalance'  => 0,
            'lb_paymode'    => $expPaymode,
            'lb_payee'      => $catId
        ]);
    }

    public static function setLoanLedgerBook($amount, $loanDate, $loanId, $bankId, $vendorId, $loanVoucher)
    {
        $financialYear = self::activeFinancialYear($loanDate);
        $openingBalance = self::openingBalanceBeforeEntry(
            $vendorId,
            self::VENDOR_LEDGER_TYPES,
            $loanDate,
            $financialYear,
            (float) (Vendor::find($vendorId)->op_balance ?? 0)
        );

        return static::create([
            'lb_vno'        => $loanVoucher,
            'lb_vid'        => $loanId,
            'lb_date'       => $loanDate,
            'lb_type'       => 'loan',
            'lb_amount'     => $amount,
            'lb_opbalance'  => $openingBalance,
            'lb_tramount'   => $amount,
            'lb_clbalance'  => $openingBalance + $amount,
            'lb_paymode'    => $bankId,
            'lb_payee'      => $vendorId,
            'financial_year'=> $financialYear
        ]);
    }

    public static function setCustomerLoanLedgerBook($amount, $loanDate, $loanId, $bankId, $customerId, $loanVoucher)
    {
        $financialYear = self::activeFinancialYear($loanDate);
        $openingBalance = self::openingBalanceBeforeEntry(
            $customerId,
            self::CUSTOMER_LEDGER_TYPES,
            $loanDate,
            $financialYear,
            (float) (Customer::find($customerId)->op_balance ?? 0)
        );

        return static::create([
            'lb_vno'        => $loanVoucher,
            'lb_vid'        => $loanId,
            'lb_date'       => $loanDate,
            'lb_type'       => 'loan_asset',
            'lb_amount'     => $amount,
            'lb_opbalance'  => $openingBalance,
            'lb_tramount'   => $amount,
            'lb_clbalance'  => $openingBalance + $amount,
            'lb_paymode'    => $bankId,
            'lb_payee'      => $customerId,
            'financial_year'=> $financialYear
        ]);
    }

    public static function updateExpenseLedgerBook($amount, $expenseDate, $expenseId, $expPaymode)
    {
        // 1. Find the original ledger entry for this purchase
        $originalEntry  = static::whereIn('lb_type', ['exp'])
            ->where('lb_vid', $expenseId)
            ->latest('lb_date')
            ->latest('lb_id')
            ->first();

        if (!$originalEntry) {
            return; // No entry found, exit the function
        }

        // 2. Update original entry fields
        $originalEntry->lb_date     = $expenseDate;
        $originalEntry->lb_amount   = $amount; // Adjust this if needed based on the new amount
        $originalEntry->lb_tramount = $amount;
        $originalEntry->lb_paymode  = $expPaymode;
        $originalEntry->status      = 1; // Assuming status remains 1 after editing
        $originalEntry->save();
    }

    public static function setCustomerLedgerBook($amountPaid, $payDate, $payMode, $payType, $custId)
    {
        if($payType == 'r') {
            $latestVno = static::whereIn('lb_type', ['s', 'pr', 'sv', 'sp', 'prp', 'svp'])
            ->latest('lb_id')
            ->first();
            $vno = $latestVno ? 'R' . str_pad((int)substr($latestVno->lb_vno, 1) + 1, 4, '0', STR_PAD_LEFT) : 'R0001';
            $lbType = 'sp';
        }
        else {
            $latestVno = static::whereIn('lb_type', ['p', 'sr', 'pp', 'srp', 'exp'])
            ->latest('lb_id')
            ->first();
            $vno = $latestVno ? 'V' . str_pad((int)substr($latestVno->lb_vno, 1) + 1, 4, '0', STR_PAD_LEFT) : 'V0001';
            $lbType = 'srp';
        }

        $customer = Customer::find($custId);
        $financialYear = self::activeFinancialYear($payDate);
        $openingBalance = self::openingBalanceBeforeEntry(
            $custId,
            self::CUSTOMER_LEDGER_TYPES,
            $payDate,
            $financialYear,
            (float) ($customer ? $customer->op_balance : 0)
        );

        // Insert without op/cl balance first
        $entry = static::create([
            'lb_vno'        => $vno,
            'lb_vid'        => 0,
            'lb_date'       => $payDate,
            'lb_type'       => $lbType,
            'lb_amount'     => $amountPaid,
            'lb_opbalance'  => $openingBalance,
            'lb_tramount'   => 0,
            'lb_clbalance'  => $payType == 'r' ? $openingBalance - $amountPaid : $openingBalance + $amountPaid,
            'lb_paymode'    => $payMode,
            'lb_payee'      => $custId,
            'financial_year'=> $financialYear
        ]);
        
        // 4️⃣ Use recalculateCustomerLedger to adjust all entries forward
        // self::recalculateCustomerLedger($custId, $payDate, $entry->lb_id);
        return $entry;
        
    }

    public static function setVendorLedgerBook($amountPaid, $payDate, $payMode, $payType, $vendorId)
    {
        if($payType == 's') {
            $latestVno = static::whereIn('lb_type', ['p', 'sr', 'pp', 'srp', 'exp'])
            ->latest('lb_id')
            ->first();
            // Generate the next receipt number
            $vno = $latestVno ? 'V' . str_pad((int)substr($latestVno->lb_vno, 1) + 1, 4, '0', STR_PAD_LEFT) : 'V0001';
            $lbType = 'pp';
        }
        else {
            $latestVno = static::whereIn('lb_type', ['s', 'pr', 'sv', 'sp', 'prp', 'svp'])
            ->latest('lb_id')
            ->first();
            // Generate the next voucher number
            $vno = $latestVno ? 'R' . str_pad((int)substr($latestVno->lb_vno, 1) + 1, 4, '0', STR_PAD_LEFT) : 'R0001';
            $lbType = 'prp';
        }

        $vendor = Vendor::find($vendorId);
        $financialYear = self::activeFinancialYear($payDate);
        $openingBalance = self::openingBalanceBeforeEntry(
            $vendorId,
            self::VENDOR_LEDGER_TYPES,
            $payDate,
            $financialYear,
            (float) ($vendor ? $vendor->op_balance : 0)
        );

        // Insert without op/cl balance first
        $entry = static::create([
            'lb_vno'        => $vno,
            'lb_vid'        => 0,
            'lb_date'       => $payDate,
            'lb_type'       => $lbType,
            'lb_amount'     => $amountPaid,
            'lb_opbalance'  => $openingBalance,
            'lb_tramount'   => 0,
            'lb_clbalance'  => $payType == 's' ? $openingBalance - $amountPaid : $openingBalance + $amountPaid,
            'lb_paymode'    => $payMode,
            'lb_payee'      => $vendorId,
            'financial_year'=> $financialYear
        ]);
        
        // 4️⃣ Use recalculateVendorLedger to adjust all entries forward
        // self::recalculateVendorLedger($vendorId, $payDate, $entry->lb_id);
        return $entry;
    }

    public static function recalculateVendorLedger($vendorId, $fromDate = null, $fromId = null, ?string $financialYear = null)
    {
        $vendor = Vendor::find($vendorId);
        $financialYear = $financialYear ?: self::activeFinancialYear($fromDate);
        $baseOpening = self::getVendorOpeningBalanceForFinancialYear(
            $vendorId,
            $financialYear,
            (float) ($vendor ? $vendor->op_balance : 0)
        );
    
        $entries = static::where('lb_payee', $vendorId)
            ->whereIn('lb_type', self::VENDOR_LEDGER_TYPES);

        self::scopeFinancialYear($entries, $financialYear);

        $entries = $entries->orderBy('lb_date')
            ->orderBy('lb_id')
            ->get();
        
        $runningBalance = $baseOpening;
    
        foreach ($entries as $entry) {
    
            // Skip until we reach affected entry (optimization)
            if ($fromDate && $fromId) {
                if ($entry->lb_date < $fromDate) {
                    $runningBalance = $entry->lb_clbalance;
                    continue;
                }
    
                if ($entry->lb_date == $fromDate && $entry->lb_id < $fromId) {
                    $runningBalance = $entry->lb_clbalance;
                    continue;
                }
            }
    
            // Set opening balance
            $entry->lb_opbalance = $runningBalance;
    
            // Apply correct formula
            if (in_array($entry->lb_type, ['p', 'loan'])) {
                $entry->lb_clbalance = $entry->lb_opbalance + $entry->lb_tramount;
            }
            elseif ($entry->lb_type == 'pp') {
                $entry->lb_clbalance = $entry->lb_opbalance - $entry->lb_amount;
            }
            elseif ($entry->lb_type == 'pr') {
                // Purchase return → decrease payable
                $entry->lb_clbalance = $entry->lb_opbalance - $entry->lb_tramount;
            }
            elseif ($entry->lb_type == 'prp') {
                // Refund paid → increase payable
                $entry->lb_clbalance = $entry->lb_opbalance + $entry->lb_amount;
            }
    
            $entry->save();
    
            $runningBalance = $entry->lb_clbalance;
        }
    }
    
    public static function recalculateCustomerLedger($custId, $fromDate = null, $fromId = null, ?string $financialYear = null)
    {
        $customer = Customer::find($custId);
        $financialYear = $financialYear ?: self::activeFinancialYear($fromDate);
        $baseOpening = self::getCustomerOpeningBalanceForFinancialYear(
            $custId,
            $financialYear,
            (float) ($customer ? $customer->op_balance : 0)
        );
    
        $entries = static::where('lb_payee', $custId)
            ->whereIn('lb_type', self::CUSTOMER_LEDGER_TYPES);

        self::scopeFinancialYear($entries, $financialYear);

        $entries = $entries->orderBy('lb_date')
            ->orderBy('lb_id')
            ->get();
    
        $runningBalance = $baseOpening;
    
        foreach ($entries as $entry) {
    
            // Skip until we reach affected entry (optimization)
            if ($fromDate && $fromId) {
                if ($entry->lb_date < $fromDate) {
                    $runningBalance = $entry->lb_clbalance;
                    continue;
                }
    
                if ($entry->lb_date == $fromDate && $entry->lb_id < $fromId) {
                    $runningBalance = $entry->lb_clbalance;
                    continue;
                }
            }
    
            // Set opening balance
            $entry->lb_opbalance = $runningBalance;
    
            // Apply correct formula
            if (in_array($entry->lb_type, ['s', 'sv', 'es', 'loan_asset'])) {
                $entry->lb_clbalance = $entry->lb_opbalance + $entry->lb_tramount;
            }
            elseif (in_array($entry->lb_type, ['sp', 'svp', 'esp'])) {
                $entry->lb_clbalance = $entry->lb_opbalance - $entry->lb_amount;
            }
            elseif ($entry->lb_type == 'sr') {
                // Sales return → decrease receivable
                $entry->lb_clbalance = $entry->lb_opbalance - $entry->lb_tramount;
            }
            elseif ($entry->lb_type == 'srp') {
                // Refund paid → increase receivable
                $entry->lb_clbalance = $entry->lb_opbalance + $entry->lb_amount;
            }
    
            $entry->save();
    
            $runningBalance = $entry->lb_clbalance;
        }
    }

    protected static function booted()
    {
        static::creating(function ($ledgerbook) {
            $ledgerbook->financial_year = $ledgerbook->financial_year
                ?: self::activeFinancialYear($ledgerbook->lb_date ?? now());
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

    public static function setBankTransferLedger($fromBankId, $toBankId, $amount, $transferDate)
    {
        // 1) Generate new voucher number for bank transfers (T0xxx)
        $latest = static::where('lb_type', 'tran')->latest('lb_id')->first();

        if ($latest) {
            $lastNumber = (int) substr($latest->lb_vno, 1);  // remove "T"
            $vno = 'T' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $vno = 'T0001';
        }

        // 3) Create Ledger Entry (Money going OUT from fromBank)
        $entry = static::create([
            'lb_vno'        => $vno,
            'lb_vid'        => 0,
            'lb_date'       => $transferDate,
            'lb_type'       => 'tran',             // tran = transfer
            'lb_amount'     => $amount,            // Paid OUT
            'lb_opbalance'  => 0.00,
            'lb_tramount'   => 0.00,
            'lb_clbalance'  => 0.00,
            'lb_paymode'    => $toBankId->bk_id,
            'lb_payee'      => $fromBankId->bk_id        // who is affected? source bank
        ]);

        return $entry;
    }

}
