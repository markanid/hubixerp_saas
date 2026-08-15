<?php

namespace Modules\Master\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Master\app\Models\Subcategory;
use Modules\Product\app\Models\Product;

// use Modules\Master\Database\Factories\CategoryFactory;

class Category extends Model
{
    use HasFactory;

    protected $table = 'category';
    public $timestamps = false;

    protected $fillable = ['category','status'];

    public function subcategoryDetails()
    {
        return $this->hasMany(Subcategory::class, 'categoryid');
    }
    
    public function productDetails()
    {
        return $this->hasMany(Product::class, 'categoryid');
    }
}
