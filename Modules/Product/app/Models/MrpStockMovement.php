<?php

namespace Modules\Product\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MrpStockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'mrp_stock_lot_id', 'product_id', 'movement_type', 'reference_type',
        'reference_id', 'reference_detail_id', 'movement_date', 'quantity_in',
        'quantity_out', 'balance_after', 'purchase_rate', 'mrp', 'sale_rate',
        'reversal_of_id',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'quantity_in' => 'decimal:2',
        'quantity_out' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'purchase_rate' => 'decimal:2',
        'mrp' => 'decimal:2',
        'sale_rate' => 'decimal:2',
    ];

    public function lot()
    {
        return $this->belongsTo(MrpStockLot::class, 'mrp_stock_lot_id');
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
