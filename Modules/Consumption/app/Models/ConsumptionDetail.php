<?php

namespace Modules\Consumption\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\app\Models\BatchMovement;
use Modules\Product\app\Models\Product;
use Modules\Product\app\Models\MrpStockLot;

// use Modules\Consumption\Database\Factories\ConsumptionDetailFactory;

class ConsumptionDetail extends Model
{
    use HasFactory;
    protected $table = 'consume_detail';
    protected $primaryKey   = 'cond_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['cond_cid', 'cond_itemid', 'stock_batch_id', 'batch_no', 'expiry_date', 'mrp_stock_lot_id', 'stock_mrp', 'cond_hsn', 'cond_qty', 'cond_unit', 'cond_uprice', 'cond_uqty', 'cond_total', 'cond_remark', 'status'];

    protected $casts = ['expiry_date' => 'date'];

    public function consumption()
    {
        return $this->belongsTo(Consumption::class, 'cond_cid', 'con_id');
    }
    
    public function product()
    {
        return $this->belongsTo(Product::class, 'cond_itemid', 'product_code');
    }

    public function batchMovements()
    {
        return $this->hasMany(BatchMovement::class, 'reference_detail_id', 'cond_id')
            ->where('reference_type', 'consumption')
            ->where('movement_type', 'consumption');
    }

    public function mrpStockLot()
    {
        return $this->belongsTo(MrpStockLot::class, 'mrp_stock_lot_id');
    }
}