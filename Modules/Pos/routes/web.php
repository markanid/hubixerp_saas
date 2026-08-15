<?php

use Illuminate\Support\Facades\Route;
use Modules\Pos\app\Http\Controllers\PosController;
use Modules\Pos\app\Http\Controllers\PosSessionController;

Route::middleware(['auth'])->group(function () {
    Route::middleware('can:pos.access')->prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('/products', [PosController::class, 'products'])->name('products');
        Route::post('/customers', [PosController::class, 'customer'])->name('customers.store');
        Route::post('/calculate', [PosController::class, 'calculate'])->name('calculate');
        Route::post('/sales', [PosController::class, 'store'])->name('sales.store');
        Route::get('/sales/{sale}/receipt', [PosController::class, 'receipt'])->name('receipt');
        Route::post('/holds', [PosController::class, 'hold'])->name('holds.store');
        Route::get('/holds', [PosController::class, 'holds'])->name('holds.index');
        Route::get('/holds/{hold}', [PosController::class, 'recall'])->name('holds.show');
        Route::delete('/holds/{hold}', [PosController::class, 'destroyHold'])->name('holds.destroy');

        Route::post('/sessions/open', [PosSessionController::class, 'open'])->name('sessions.open');
        Route::post('/sessions/{session}/close', [PosSessionController::class, 'close'])->name('sessions.close');
    });
});
