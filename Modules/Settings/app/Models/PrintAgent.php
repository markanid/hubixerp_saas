<?php

namespace Modules\Settings\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintAgent extends Model
{
    protected $fillable = [
        'uuid', 'name', 'machine_name', 'token_hash', 'pairing_code_hash',
        'pairing_expires_at', 'paired_by', 'enabled', 'is_default', 'version',
        'printers', 'default_printer', 'last_seen_at', 'last_error',
    ];

    protected $hidden = ['token_hash', 'pairing_code_hash'];

    protected $casts = [
        'printers' => 'array',
        'pairing_expires_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'enabled' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function mappings(): HasMany
    {
        return $this->hasMany(PrintAgentPrinterMapping::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(LocalPrintJob::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at?->greaterThanOrEqualTo(now()->subMinutes(2)) ?? false;
    }
}
