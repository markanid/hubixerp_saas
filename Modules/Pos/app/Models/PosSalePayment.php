<?php

namespace Modules\Pos\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Finance\app\Models\Banking;
use Modules\Sale\app\Models\Sale;

class PosSalePayment extends Model
{
    protected $fillable = [
        'sale_id',
        'pos_session_id',
        'banking_id',
        'method',
        'amount',
        'reference_no',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id', 'sa_id');
    }

    public function banking(): BelongsTo
    {
        return $this->belongsTo(Banking::class, 'banking_id', 'bk_id');
    }
}
