<?php

namespace Modules\Contacts\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Finance\app\Models\LedgerBook;

class VendorPaymentAllocation extends Model
{
    use HasFactory;

    protected $table = 'vendor_payment_allocations';
    public $timestamps = false;

    protected $fillable = [
        'payment_ledgerbook_id',
        'source_ledgerbook_id',
        'source_type',
        'source_id',
        'amount',
    ];

    public function paymentLedger()
    {
        return $this->belongsTo(LedgerBook::class, 'payment_ledgerbook_id', 'lb_id');
    }

    public function sourceLedger()
    {
        return $this->belongsTo(LedgerBook::class, 'source_ledgerbook_id', 'lb_id');
    }
}
