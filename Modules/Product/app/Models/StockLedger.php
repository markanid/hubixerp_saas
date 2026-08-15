<?php

namespace Modules\Product\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

// use Modules\Purchase\Database\Factories\StockFactory;

class StockLedger extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['stock_item_id', 'stock_date', 'stock_type', 'stock_ref_id', 'stock_in', 'stock_out', 'stock_balance'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_code');
    }

    public static function updateStockLedger($date, $itemId, $qty, $refId = null, $trans_type)
    {
        // 1. Find the latest balance before the new entry
        $previous = StockLedger::where('stock_item_id', $itemId)
            ->where('stock_date', '<=', $date)
            ->latest('stock_date')
            ->latest('id')
            ->first();

        $startingBalance = $previous ? $previous->stock_balance : 0;

        // 2. Add the new entry
        $stock = StockLedger::create([
            'stock_item_id'   => $itemId,
            'stock_date'      => $date,
            'stock_type'      => $trans_type,
            'stock_ref_id'    => $refId,
            'stock_in'        => $qty > 0 ? $qty : 0,
            'stock_out'       => $qty < 0 ? abs($qty) : 0,
            'stock_balance'   => 0, // will be updated below
        ]);

        // 3. Recalculate only from this point forward
        $entries = StockLedger::where('stock_item_id', $itemId)
        ->where(function ($q) use ($date, $stock) {
            $q->where('stock_date', '>', $date)
            ->orWhere(function ($q2) use ($date, $stock) {
                $q2->where('stock_date', $date)
                    ->where('id', '>', $stock->id); // strictly after the new entry
            });
        })
        ->orderBy('stock_date')
        ->orderBy('id')
        ->get();


        // 4. Include the newly added one first
        $runningBalance = $startingBalance + $stock->stock_in - $stock->stock_out;
        $stock->stock_balance = $runningBalance;
        $stock->save();

        foreach ($entries as $entry) {
            $entry->stock_balance = $runningBalance + $entry->stock_in - $entry->stock_out;
            $runningBalance = $entry->stock_balance;
            $entry->save();
        }
    }

    public static function deleteStockLedgerEntry($itemId, $refId, $fromDate)
    {
        // Step 1: Delete the specific stock ledger entry using the reference ID
        self::where('stock_item_id', $itemId)
        ->where('stock_ref_id', $refId)
        ->where('stock_date', $fromDate)
        ->delete();

        // Step 2: Get all stock ledger entries from the given date onward
        $entries = self::where('stock_item_id', $itemId)
            ->where('stock_date', '>=', $fromDate)
            ->orderBy('stock_date')
            ->orderBy('id')
            ->get();

        // Step 3: Find the balance just before the deleted date
        $prevBalance = self::where('stock_item_id', $itemId)
            ->where('stock_date', '<', $fromDate)
            ->latest('stock_date')
            ->latest('id')
            ->first()?->stock_balance ?? 0;

        // Step 4: Recalculate stock balances for the remaining entries
        foreach ($entries as $entry) {
            $entry->stock_balance = $prevBalance + $entry->stock_in - $entry->stock_out;
            $entry->save();

            $prevBalance = $entry->stock_balance;
        }
    }   

    protected static function booted()
    {
        static::creating(function ($stockledger) {
            $stockledger->financial_year = self::getFinancialYear($stockledger->stock_date ?? now());
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
