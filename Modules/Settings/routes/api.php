<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\app\Http\Controllers\PrintAgentApiController;
use Modules\Settings\app\Middleware\AuthenticatePrintAgent;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/


Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('print-agent')->name('print-agent.')->group(function () {

    Route::post('/pair', [PrintAgentApiController::class, 'pair'])
        ->middleware('throttle:5,1')
        ->name('pair');

    Route::middleware(AuthenticatePrintAgent::class)->group(function () {

        Route::post('/heartbeat', [PrintAgentApiController::class, 'heartbeat'])
            ->name('heartbeat');

        Route::post('/browser-link-code', [PrintAgentApiController::class, 'browserLinkCode'])
            ->middleware('throttle:10,1')
            ->name('browser-link-code');

        Route::get('/jobs/next', [PrintAgentApiController::class, 'next'])
            ->name('jobs.next');

        Route::get('/jobs/{localPrintJob}/render', [PrintAgentApiController::class, 'render'])
            ->name('jobs.render');

        Route::post('/jobs/{localPrintJob}/status', [PrintAgentApiController::class, 'status'])
            ->name('jobs.status');
    });
});

// Route::prefix('print-agent')->name('print-agent.')->group(function () {
//     Route::post('/pair', [PrintAgentApiController::class, 'pair'])->middleware('throttle:5,1')->name('pair');

//     Route::middleware(AuthenticatePrintAgent::class)->group(function () {
//         Route::post('/heartbeat', [PrintAgentApiController::class, 'heartbeat'])->name('heartbeat');
//         Route::get('/jobs/next', [PrintAgentApiController::class, 'next'])->name('jobs.next');
//         Route::get('/jobs/{localPrintJob}/render', [PrintAgentApiController::class, 'render'])->name('jobs.render');
//         Route::post('/jobs/{localPrintJob}/status', [PrintAgentApiController::class, 'status'])->name('jobs.status');
//     });
// });
