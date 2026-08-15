<?php

namespace Modules\Returns\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\MrpStockLot;

// use Modules\Returns\Database\Factories\ReturnDetailsFactory;

class ReturnDetail extends Model
{
    use HasFactory;

    protected $table        = 'preturn_details';
    protected $primaryKey   = 'prd_id';
    public $timestamps = false;

    protected $fillable = ['prd_prid','prd_itemid','stock_batch_id','batch_no','mrp_stock_lot_id','stock_mrp','prd_hsn','prd_itemqty','prd_uqty','prd_unit','prd_uprice','prd_gst','prd_total','status'];

    public function return()
    {
        return $this->belongsTo(Returns::class, 'prd_prid', 'pr_id');
    }
    
    public function product()
    {
        return $this->belongsTo(Product::class, 'prd_itemid', 'product_code');
    }

    public function mrpStockLot()
    {
        return $this->belongsTo(MrpStockLot::class, 'mrp_stock_lot_id');
    }

    public static function getProductReturnSummaryBySupplier($productCode)
    {
        return self::where('prd_itemid', $productCode)
            ->whereHas('return', function ($q) {
                $q->where('pr_type', 1); // Only purchase returns
            })
            ->with('return.vendor') // eager load return and vendor
            ->get()
            ->groupBy(function ($item) {
                // Group by vendor id (pr_vendor)
                return $item->return->pr_vendor ?? null;
            })
            ->map(function ($items, $vendorId) {
                $vendorName = $items->first()->return->vendor->cp_name ?? 'N/A';

                return (object)[
                    'pr_vendor' => $vendorId,
                    'vendor_name' => $vendorName,
                    'return_count' => $items->pluck('prd_prid')->unique()->count(),
                    'total_amount' => $items->sum('prd_uprice'),
                ];
            })
            ->values();
    }

    public static function getProductReturnSummaryByCustomer($productCode)
    {
        return self::where('prd_itemid', $productCode)
            ->whereHas('return', function ($q) {
                $q->where('pr_type', 2); // Only sale returns
            })
            ->with('return.customer') // eager load return and customer
            ->get()
            ->groupBy(function ($item) {
                // Group by customer id (pr_vendor)
                return $item->return->pr_vendor ?? null;
            })
            ->map(function ($items, $customerId) {
                $customerName = $items->first()->return->customer->customer ?? 'N/A';

                return (object)[
                    'pr_vendor' => $customerId,
                    'customer_name' => $customerName,
                    'return_count' => $items->pluck('prd_prid')->unique()->count(),
                    'total_amount' => $items->sum('prd_uprice'),
                ];
            })
            ->values();
    }

}