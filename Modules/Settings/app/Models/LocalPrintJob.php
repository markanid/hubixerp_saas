<?php

namespace Modules\Settings\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalPrintJob extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_PRINTING = 'printing';
    public const STATUS_PRINTED = 'printed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'uuid', 'print_agent_id', 'document_type', 'document_id', 'status',
        'copies', 'requested_by', 'claim_token_hash', 'claimed_at',
        'printed_at', 'expires_at', 'error', 'request_ip', 'request_user_agent', 'payload',
    ];

    protected $hidden = ['claim_token_hash'];

    protected $casts = [
        'claimed_at' => 'datetime',
        'printed_at' => 'datetime',
        'expires_at' => 'datetime',
        'payload' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PrintAgent::class, 'print_agent_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_PRINTED, self::STATUS_FAILED, self::STATUS_EXPIRED], true);
    }
}
