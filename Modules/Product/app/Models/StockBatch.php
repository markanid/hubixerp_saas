<?php

namespace Modules\Product\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'batch_no', 'expiry_date', 'purchase_rate', 'mrp', 'sale_price',
        'quantity', 'available_quantity',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'purchase_rate' => 'decimal:2',
        'mrp' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'available_quantity' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_code');
    }

    public function movements()
    {
        return $this->hasMany(BatchMovement::class);
    }

    public function inventoryLabel()
    {
        return $this->hasOne(InventoryLabel::class, 'stock_batch_id');
    }
}