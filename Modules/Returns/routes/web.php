<?php

use Illuminate\Support\Facades\Route;
use Modules\Returns\app\Http\Controllers\ReturnController;

Route::group(['middleware'=>'auth'],function(){

    // Purchase Return Routes
    Route::prefix('purchase-returns')->name('purchase-returns.')->group(function () {
        Route::get('/', [ReturnController::class, 'pindex'])->name('index');
        Route::get("/cancelled", [ReturnController::class, 'pcancelled'])->name("cancelled");
        Route::get('/create', [ReturnController::class, 'pcreateOrEdit'])->name('create');
        Route::get('/print/{id}', [ReturnController::class, 'pprint'])->name('print');
        Route::get('/{id}', [ReturnController::class, 'pshow'])->name('show');
        Route::get("/cancelled/{id}", [ReturnController::class, 'showPCancelled'])->name("showCancelled");
        Route::get('/edit/{id}', [ReturnController::class, 'pcreateOrEdit'])->name('edit');
        Route::post('/update', [ReturnController::class, 'pstoreOrUpdate'])->name('update');
        Route::delete('/{id}', [ReturnController::class, 'pdestroy'])->name('delete');
    });
    // Sales Return Routes
    Route::prefix('sales-returns')->name('sales-returns.')->group(function () {
        Route::middleware('can:sale.create')->group(function () {
            Route::get('/create', [ReturnController::class, 'screateOrEdit'])->name('create');
            Route::post('/update', [ReturnController::class, 'sstoreOrUpdate'])->name('update');
        });
        Route::middleware('can:sale.view')->group(function () {
            Route::get('/', [ReturnController::class, 'sindex'])->name('index');
            Route::get("/cancelled", [ReturnController::class, 'scancelled'])->name("cancelled");
            Route::get('/print/{id}', [ReturnController::class, 'sprint'])->name('print');
            Route::get('/{id}', [ReturnController::class, 'sshow'])->name('show');
            Route::get("/cancelled/{id}", [ReturnController::class, 'showSCancelled'])->name("showCancelled");
        });
        Route::get('/edit/{id}', [ReturnController::class, 'screateOrEdit'])
            ->middleware('can:sale.update')
            ->name('edit');
        Route::delete('/{id}', [ReturnController::class, 'sdestroy'])
            ->middleware('can:sale.cancel')
            ->name('delete');
    });
    Route::get('/preturn-search', [ReturnController::class, 'pRetSearch'])->name('purchase-returns.search');
    Route::get('/sreturn-search', [ReturnController::class, 'sRetSearch'])
        ->middleware('can:sale.create')
        ->name('sales-returns.search');
});
