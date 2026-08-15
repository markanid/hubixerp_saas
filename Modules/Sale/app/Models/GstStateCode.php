<?php

namespace Modules\Sale\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Sale\Database\Factories\GstStateCodeFactory;

class GstStateCode extends Model
{
    use HasFactory;

    protected $table = 'gst_state_codes';
    public $timestamps = false;
    protected $primaryKey = 'state_code';
    public $incrementing = false;
    protected $keyType = 'string';
}
