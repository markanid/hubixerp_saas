<?php

namespace Modules\Service\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\BatchMovement;
use Modules\Product\app\Models\MrpStockLot;

class ServiceDetail extends Model
{
    use HasFactory;

    protected $primaryKey   = 'svd_id';
    public $timestamps = false;
    protected $fillable = ['svd_sid', 'svd_itemid', 'stock_batch_id', 'batch_no', 'expiry_date', 'mrp_stock_lot_id', 'stock_mrp', 'svd_hsn', 'manufacturing_date', 'svd_itemqty', 'svd_unit', 'svd_uprice', 'svd_uqty', 'svd_price', 'svd_adisc', 'svd_pdisc', 'svd_gst', 'svd_total', 'svd_type', 'status', 'svd_remark'];

    protected $casts = ['expiry_date' => 'date'];

    public function service()
    {
        return $this->belongsTo(Service::class, 'svd_sid', 'sv_id');
    }
    
    public function product()
    {
        return $this->belongsTo(Product::class, 'svd_itemid', 'product_code');
    }

    public function batchMovements()
    {
        return $this->hasMany(BatchMovement::class, 'reference_detail_id', 'svd_id')
            ->where('reference_type', 'service')
            ->where('movement_type', 'service');
    }

    public function mrpStockLot()
    {
        return $this->belongsTo(MrpStockLot::class, 'mrp_stock_lot_id');
    }

    public static function getProductServiceSummaryByCustomer($productCode)
    {
        return self::where('svd_itemid', $productCode)
            ->with('service.customer')
            ->get()
            ->groupBy(function ($item) {
                return $item->service->sv_customer ?? null;
            })
            ->map(function ($items, $customerId) {
                $customerName = $items->first()->service->customer->customer ?? 'N/A';

                return (object)[
                    'customer_id'   => $customerId,
                    'customer_name' => $customerName,
                    'service_count' => $items->pluck('svd_sid')->unique()->count(),
                    'total_amount'  => $items->sum('svd_total'),
                ];
            })
            ->values();
    }
}