<?php

namespace Modules\Purchase\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\BatchMovement;
use Modules\Product\app\Models\MrpStockLot;

// use Modules\Purchase\Database\Factories\PurchaseDetailFactory;

class PurchaseDetail extends Model
{
    use HasFactory;

    protected $table        = 'purchase_detail';
    protected $primaryKey   = 'pud_id';
    public $timestamps = false;

    protected $fillable = ['pud_pid','pud_itemid','batch_no','expiry_date','batch_mrp','lot_sale_price','lot_margin_percentage','lot_margin_amount','pud_hsn','pud_itemqty','pud_returnqty','pud_free','pud_unit','pud_uprice','pud_uqty','pud_price','pud_adisc','pud_pdisc','pud_gst','pud_total','status'];

    protected $casts = ['expiry_date' => 'date'];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'pud_pid', 'pu_id');
    }
    
    public function product()
    {
        return $this->belongsTo(Product::class, 'pud_itemid', 'product_code');
    }

    public function mrpStockLot()
    {
        return $this->hasOne(MrpStockLot::class, 'purchase_detail_id', 'pud_id');
    }

    public function batchMovement()
    {
        return $this->hasOne(BatchMovement::class, 'reference_detail_id', 'pud_id')
            ->where('reference_type', 'purchase')
            ->where('movement_type', 'purchase');
    }

    public static function getProductPurchaseSummaryBySupplier($productCode)
    {
        return self::where('pud_itemid', $productCode)
        ->with('purchase.vendor') // eager load purchase and vendor
        ->get()
        ->groupBy(function ($item) {
            // Group by vendor id (pu_vendor)
            return $item->purchase->pu_vendor ?? null;
        })
        ->map(function ($items, $vendorId) {
            $vendorName = $items->first()->purchase->vendor->cp_name ?? 'N/A';

            return (object)[
                'pu_vendor' => $vendorId,
                'vendor_name' => $vendorName,
                'purchase_count' => $items->pluck('pud_pid')->unique()->count(),
                'total_amount' => $items->sum('pud_total'),
            ];
        })
        ->values(); // reset keys
    }
}