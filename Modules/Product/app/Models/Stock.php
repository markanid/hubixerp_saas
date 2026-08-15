<?php

namespace Modules\Product\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Purchase\Database\Factories\StockFactory;

class Stock extends Model
{
    use HasFactory;

    protected $table        = 'stock';
    protected $primaryKey   = 'stock_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['stock_date', 'stock_product_id', 'stock_qty','status'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'stock_product_id', 'product_code');
    }

    public static function updateStock($stockDate, $stockProductId, $itemQty)
    {
        $latestStock = Stock::where('stock_product_id', $stockProductId)
            ->latest('stock_id')
            ->first();

        if ($latestStock) {
            $latestStock->stock_qty += $itemQty;
            $latestStock->save();
        } else {
            Stock::create([
                'stock_qty'         => $itemQty,
                'stock_date'        => $stockDate,
                'stock_product_id'  => $stockProductId,
            ]);
        }
    }

    public static function deleteStock($stockDate, $stockProductId, $itemQty)
    {
        $latestStock = Stock::where('stock_product_id', $stockProductId)
            ->latest('stock_id')
            ->first();

        if ($latestStock) {
            $latestStock->stock_qty -= $itemQty;
            $latestStock->save();
        } else {
            Stock::create([
                'stock_qty'         => $itemQty,
                'stock_date'        => $stockDate,
                'stock_product_id'  => $stockProductId,
            ]);
        }
    }
}
