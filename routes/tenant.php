<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Stancl\Tenancy\Middleware\ScopeSessions;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    ScopeSessions::class,
])->group(function () {
    Route::get('/tenant-check', function () {
        return [
            'context' => 'tenant',
            'host' => request()->getHost(),
            'tenant_id' => tenant('id'),
            'database' => config('database.connections.tenant.database'),
        ];
    })->name('tenant.check');

    /*
     * Put only tenant-specific routes here.
     *
     * Do not put central tenant-management routes here.
     * Do not manually reload your module routes if they already auto-load.
     */
});