<?php

namespace Modules\Pos\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Settings\app\Models\User;

class PosSession extends Model
{
    protected $fillable = [
        'user_id',
        'pos_counter_id',
        'company_id',
        'financial_year',
        'counter_code',
        'opening_cash',
        'expected_cash',
        'closing_cash',
        'cash_difference',
        'opened_at',
        'closed_at',
        'status',
        'closing_note',
    ];

    protected $casts = [
        'opening_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(PosCounter::class, 'pos_counter_id');
    }

    public function heldBills(): HasMany
    {
        return $this->hasMany(PosHeldBill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosSalePayment::class);
    }
}
