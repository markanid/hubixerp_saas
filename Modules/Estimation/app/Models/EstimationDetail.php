<?php

namespace Modules\Estimation\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\MrpStockLot;

// use Modules\Estimation\Database\Factories\EstimationDetailFactory;

class EstimationDetail extends Model
{
    use HasFactory;

    protected $primaryKey   = 'esd_id';
    public $timestamps = false;
    protected $fillable = ['esd_sid', 'esd_itemid', 'stock_batch_id', 'batch_no', 'expiry_date', 'mrp_stock_lot_id', 'stock_mrp', 'esd_hsn', 'esd_itemqty', 'esd_uqty', 'esd_unit', 'esd_uprice', 'esd_price', 'esd_adisc', 'esd_pdisc', 'esd_total', 'status'];

    protected $casts = ['expiry_date' => 'date'];
    
    public function estimation()
    {
        return $this->belongsTo(Estimation::class, 'esd_sid', 'es_id');
    }
    
    public function product()
    {
        return $this->belongsTo(Product::class, 'esd_itemid', 'product_code');
    }

    public function mrpStockLot()
    {
        return $this->belongsTo(MrpStockLot::class, 'mrp_stock_lot_id');
    }
}