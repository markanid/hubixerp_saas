<?php

namespace Modules\Master\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\app\Models\Product;

// use Modules\Master\Database\Factories\BrandFactory;

class Brand extends Model
{

    use HasFactory;

    protected $table = 'brand';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['brand','brand_image'];
    

    public function productDetails()
    {
        return $this->hasMany(Product::class, 'brandid');
    }

}