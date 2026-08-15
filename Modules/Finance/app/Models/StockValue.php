<?php

namespace Modules\Finance\app\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\app\Models\Stock;

// use Modules\Finance\Database\Factories\StockValueFactory;

class StockValue extends Model
{
    use HasFactory;
    
    protected $table        = 'stock_value';
    protected $primaryKey   = 'sv_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['sv_date','sv_pvalue','sv_svalue','status'];

    public static function stockclosing()
    {
        $sv_date = Carbon::now()->format('Y-m-d');
        $sv_pvalue = 0;
        $sv_svalue = 0;

        $stocks = Stock::where('stock_qty', '>', 0)
        ->with('product') 
        ->get();

        if ($stocks->isNotEmpty()) {
            // Perform the calculations for pvalue and svalue
            foreach ($stocks as $value) {
                $sv_pvalue += ($value->product->pprice * ($value->stock_qty / $value->product->uqty));
                $sv_svalue += ($value->product->price * ($value->stock_qty / $value->product->uqty));
            }

            // Prepare data for insertion or update
            $data = [
                'sv_pvalue' => $sv_pvalue,
                'sv_svalue' => $sv_svalue,
                'sv_date'   => $sv_date,
            ];

            // Check if the stock value record already exists for the current date
            $existingStockValue = StockValue::where('sv_date', $sv_date)->first();

            if ($existingStockValue) {
                // Update the existing record if found
                $existingStockValue->update($data);
            } else {
                // Insert a new record if not found
                StockValue::create($data);
            }
        } else {
            // If there are no stocks with quantity greater than 0
            return 0;
        }

        return 0;
    }
}
