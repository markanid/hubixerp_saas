<?php

namespace Modules\Pos\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosHeldBill extends Model
{
    protected $fillable = [
        'pos_session_id',
        'user_id',
        'hold_no',
        'customer_id',
        'customer_name',
        'cart',
        'payments',
        'amount_payable',
    ];

    protected $casts = [
        'pos_session_id' => 'integer',
        'user_id' => 'integer',
        'customer_id' => 'integer',
        'cart' => 'array',
        'payments' => 'array',
        'amount_payable' => 'decimal:2',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }
}
