<?php

namespace Modules\Product\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Estimation\app\Models\EstimationDetail;
use Modules\Master\app\Models\Brand;
use Modules\Master\app\Models\Category;
use Modules\Master\app\Models\Group;
use Modules\Master\app\Models\Subcategory;
use Modules\Purchase\app\Models\PurchaseDetail;
use Modules\Returns\app\Models\ReturnDetail;
use Modules\Sale\app\Models\SaleDetail;
use Modules\Service\app\Models\ServiceDetail;

// use Modules\Purchase\Database\Factories\ProductFactory;

class Product extends Model
{
    use HasFactory;

    protected $table = 'product';
    public $timestamps = false;

    protected $fillable = ['product_code', 'product', 'hsn_code', 'mrp', 'margin', 'amt_margin', 'price', 'pprice', 'unit', 'uqty', 'gst', 'maxquantity', 'minquantity', 'brandid', 'categoryid', 'subcategoryid', 'groupid', 'typeid', 'is_batch_managed', 'bcode_image', 'qrcode_image', 'product_image', 'bar_code', 'status'];

    protected $casts = ['is_batch_managed' => 'boolean'];

    public function scopeMatchingSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        // Resolve a scanned barcode before applying partial-name matching or limits.
        foreach (['bar_code', 'product_code'] as $column) {
            if ($term !== '' && (clone $query)->where($column, $term)->exists()) {
                return $query->where($column, $term);
            }
        }

        return $query->where(function (Builder $builder) use ($term) {
            $builder->where('product_code', 'like', "%{$term}%")
                ->orWhere('product', 'like', "%{$term}%")
                ->orWhere('bar_code', 'like', "%{$term}%");
        });
    }
    
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brandid');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'categoryid');
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class, 'subcategoryid');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'groupid');
    }
    
    public function stock()
    {
        return $this->hasOne(Stock::class, 'stock_product_id', 'product_code');
    }
    
    public function stockLedgers()
    {
        return $this->hasMany(StockLedger::class, 'stock_item_id', 'product_code');
    }

    public function stockBatches()
    {
        return $this->hasMany(StockBatch::class, 'product_id', 'product_code');
    }

    public function mrpStockLots()
    {
        return $this->hasMany(MrpStockLot::class, 'product_id', 'product_code');
    }

    public function inventoryLabels()
    {
        return $this->hasMany(InventoryLabel::class, 'product_id', 'product_code');
    }
    
    public function purchaseInDetails()
    {
        return $this->hasMany(PurchaseDetail::class, 'pud_itemid', 'product_code');
    }
    
    public function saleInDetails()
    {
        return $this->hasMany(SaleDetail::class, 'sad_itemid', 'product_code');
    }
    
    public function estimationInDetails()
    {
        return $this->hasMany(EstimationDetail::class, 'esd_itemid', 'product_code');
    }

    public function returnInDetails()
    {
        return $this->hasMany(ReturnDetail::class, 'prd_itemid', 'product_code');
    }
    
    public function serviceInDetails()
    {
        return $this->hasMany(ServiceDetail::class, 'svd_itemid', 'product_code');
    }

    public static function getProductCode()
    {
        $lastProduct = Product::latest('id')->first();
        if (!$lastProduct) {
            return 'PRD_001';
        }
        $lastCode = $lastProduct->product_code;
        $codeParts = explode('_', $lastCode);
        $number = intval(end($codeParts)) + 1;
        return 'PRD_' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
}
