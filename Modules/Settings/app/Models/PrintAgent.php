<?php

namespace Modules\Settings\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintAgent extends Model
{
    public const BROWSER_COOKIE = 'hubix_print_agent';
    public const BROWSER_BINDING_MINUTES = 60 * 24 * 400;
    public const BROWSER_BINDING_MIN_VERSION = '1.0.4';
    public const BARCODE_MIN_VERSION = '1.0.3';

    protected $fillable = [
        'uuid', 'name', 'machine_name', 'token_hash', 'pairing_code_hash',
        'pairing_expires_at', 'paired_by', 'enabled', 'is_default', 'version',
        'printers', 'default_printer', 'last_seen_at', 'last_error',
        'browser_link_code_hash', 'browser_link_expires_at', 'browser_binding_token_hash',
    ];

    protected $hidden = [
        'token_hash',
        'pairing_code_hash',
        'browser_link_code_hash',
        'browser_binding_token_hash',
    ];

    protected $casts = [
        'printers' => 'array',
        'pairing_expires_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'browser_link_expires_at' => 'datetime',
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

    public static function parseBrowserBinding(?string $binding): ?array
    {
        if (!$binding || !str_contains($binding, '.')) {
            return null;
        }

        [$uuid, $token] = explode('.', $binding, 2);
        if ($uuid === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return null;
        }

        return [$uuid, $token];
    }

    public function matchesBrowserBinding(?string $binding): bool
    {
        $parts = self::parseBrowserBinding($binding);

        return $parts !== null
            && hash_equals($this->uuid, $parts[0])
            && $this->browser_binding_token_hash
            && hash_equals($this->browser_binding_token_hash, hash('sha256', $parts[1]));
    }
}
