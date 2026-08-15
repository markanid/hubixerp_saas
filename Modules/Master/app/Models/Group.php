<?php

namespace Modules\Master\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\app\Models\Product;

// use Modules\Master\Database\Factories\GroupFactory;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['groups'];
    public $timestamps = false;
    
    public function productDetails()
    {
        return $this->hasMany(Product::class, 'groupid');
    }
}
