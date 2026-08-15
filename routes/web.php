<?php

use App\Http\Controllers\TenantRegistrationController;
use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains', []) as $domain) {
    Route::domain($domain)
        ->name('central.')
        ->group(function () {
            /*
             * Public central test route.
             * Keep this outside auth temporarily while testing.
             */
            Route::get('/central-check', function () {
                return [
                    'context' => 'central',
                    'host' => request()->getHost(),
                    'tenancy_initialized' => tenancy()->initialized,
                    'database' => config(
                        'database.connections.mysql.database'
                    ),
                ];
            })->name('check');

            Route::get('/', function () {
                return redirect()->route('central.tenants.index');
            })->name('home');

            /*
             * Temporarily keep auth:central removed until the
             * central guard and central login are confirmed working.
             */
            Route::get(
                '/tenants',
                [TenantRegistrationController::class, 'index']
            )->name('tenants.index');

            Route::get(
                '/tenants/create',
                [TenantRegistrationController::class, 'create']
            )->name('tenants.create');

            Route::post(
                '/tenants',
                [TenantRegistrationController::class, 'store']
            )->name('tenants.store');

            Route::prefix('admin')->group(function () {
                Route::get('/tenants', function () {
                    return redirect()->route('central.tenants.index');
                })->name('admin.tenants.index');

                Route::get('/tenants/create', function () {
                    return redirect()->route('central.tenants.create');
                })->name('admin.tenants.create');

                Route::post(
                    '/tenants',
                    [TenantRegistrationController::class, 'store']
                )->name('admin.tenants.store');
            });
        });
}
