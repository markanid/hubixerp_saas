<?php

namespace Modules\Product\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MrpStockLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'purchase_id', 'purchase_detail_id', 'purchase_voucher',
        'purchase_date', 'purchase_rate', 'mrp', 'sale_price', 'margin_percentage',
        'margin_amount', 'quantity', 'available_quantity',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_rate' => 'decimal:2',
        'mrp' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'margin_amount' => 'decimal:2',
        'quantity' => 'decimal:2',
        'available_quantity' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_code');
    }

    public function movements()
    {
        return $this->hasMany(MrpStockMovement::class);
    }

    public function inventoryLabel()
    {
        return $this->hasOne(InventoryLabel::class, 'mrp_stock_lot_id');
    }
}