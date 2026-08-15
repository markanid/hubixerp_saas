<?php

namespace Modules\Master\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Finance\app\Models\Expense;
use Modules\Finance\app\Models\LedgerBook;

// use Modules\Master\Database\Factories\ExcategoryFactory;

class Excategory extends Model
{
    use HasFactory;
    protected $table = 'excategory';
    public $timestamps = false;

    protected $fillable = ['category','status'];
    
    public function expenses()
    {
        return $this->hasMany(Expense::class, 'categoryid');
    }

    public function ledgerbooks()
    {
        return $this->hasMany(LedgerBook::class, 'lb_payee');
    }
}
