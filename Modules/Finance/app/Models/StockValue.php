<?php

namespace Modules\Finance\app\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Finance\app\Services\InventoryValuationService;

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

    public static function stockClosing()
    {
        $sv_date = Carbon::now()->format('Y-m-d');
        $totals = app(InventoryValuationService::class)->totals();

        StockValue::updateOrCreate(
            ['sv_date' => $sv_date],
            [
                'sv_pvalue' => $totals['purchase_value'],
                'sv_svalue' => $totals['sale_value'],
            ]
        );

        return 0;
    }
}
