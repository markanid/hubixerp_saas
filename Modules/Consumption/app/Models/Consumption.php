<?php

namespace Modules\Consumption\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Modules\Settings\app\Models\User;

// use Modules\Consumption\Database\Factories\ConsumptionFactory;

class Consumption extends Model
{
    use HasFactory;
    protected $table = 'consume';
    protected $primaryKey   = 'con_id';
    public $timestamps = false;

    protected $fillable = ['con_vno','con_date', 'financial_year', 'con_amount', 'con_user','status'];

    public function user()
    {
        return $this->belongsTo(User::class, 'con_user');
    }
    
    public function consumptionDetails()
    {
        return $this->hasMany(ConsumptionDetail::class, 'cond_cid', 'con_id');
    }

    public static function getVoucherCode()
    {
        $year = date('m') >= 4 ? date('y') : date('y') - 1;

        $consumption = DB::table('consume')
        ->selectRaw('CAST(SUBSTRING(con_vno, 5) AS SIGNED) as con_vno')
        ->whereRaw('SUBSTRING(con_vno, 2, 2) = ?', [$year])
        ->latest('con_id')
        ->first();
    
        $nextVoucher = $consumption ? $consumption->con_vno + 1 : 1;
        return 'C' . $year . '-' . str_pad($nextVoucher, 4, '0', STR_PAD_LEFT);
    }
}
