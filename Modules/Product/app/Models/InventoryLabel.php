<?php

namespace Modules\Product\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLabel extends Model
{
    use HasFactory;

    public const TYPE_MRP = 'mrp';
    public const TYPE_BATCH = 'batch';

    protected $fillable = [
        'product_id', 'label_type', 'mrp_stock_lot_id', 'stock_batch_id', 'barcode', 'signature',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_code');
    }

    public function mrpStockLot()
    {
        return $this->belongsTo(MrpStockLot::class, 'mrp_stock_lot_id');
    }

    public function stockBatch()
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }
}
