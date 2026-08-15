<?php

namespace Modules\Master\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Master\app\Models\Category;
use Modules\Product\app\Models\Product;

// use Modules\Master\Database\Factories\SubcategoryFactory;

class Subcategory extends Model
{
    use HasFactory;

    protected $table = 'subcategory';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['subcategory','categoryid','status'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'categoryid');
    }
    
    public function productDetails()
    {
        return $this->hasMany(Product::class, 'subcategoryid');
    }
}
