<?php

namespace Modules\Sale\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\app\Models\Product;
use Modules\Sale\app\Models\Sale;
use Modules\Product\app\Models\BatchMovement;
use Modules\Product\app\Models\MrpStockLot;
use Modules\Product\app\Models\MrpStockMovement;

// use Modules\Sale\Database\Factories\SaleDetailFactory;

class SaleDetail extends Model
{
    use HasFactory;
    protected $primaryKey   = 'sad_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['sad_sid', 'sad_itemid', 'stock_batch_id', 'batch_no', 'expiry_date', 'mrp_stock_lot_id', 'stock_mrp', 'sad_hsn', 'manufacturing_date', 'sad_itemqty', 'sad_returnqty', 'sad_unit', 'sad_uprice', 'sad_uqty', 'sad_price', 'sad_adisc', 'sad_pdisc', 'sad_gst', 'sad_total', 'status'];

    protected $casts = ['expiry_date' => 'date'];

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sad_sid', 'sa_id');
    }
    
    public function product()
    {
        return $this->belongsTo(Product::class, 'sad_itemid', 'product_code');
    }

    public function batchMovements()
    {
        return $this->hasMany(BatchMovement::class, 'reference_detail_id', 'sad_id')
            ->where('reference_type', 'sale')
            ->where('movement_type', 'sale');
    }

    public function mrpStockLot()
    {
        return $this->belongsTo(MrpStockLot::class, 'mrp_stock_lot_id');
    }

    public function mrpStockMovements()
    {
        return $this->hasMany(MrpStockMovement::class, 'reference_detail_id', 'sad_id')
            ->where('reference_type', 'sale')
            ->where('movement_type', 'sale');
    }

    public static function getProductSaleSummaryByCustomer($productCode)
    {
        return self::where('sad_itemid', $productCode)
            ->with('sale.customer')
            ->get()
            ->groupBy(function ($item) {
                return $item->sale->sa_customer ?? null;
            })
            ->map(function ($items, $customerId) {
                $customerName = $items->first()->sale->customer->customer ?? 'N/A';

                return (object)[
                    'customer_id'   => $customerId,
                    'customer_name' => $customerName,
                    'sale_count'    => $items->pluck('sad_sid')->unique()->count(),
                    'total_amount'  => $items->sum('sad_total'), // assuming sad_total exists
                ];
            })
            ->values();
    }
}