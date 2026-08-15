<?php

use Illuminate\Support\Facades\Route;
use Modules\Service\app\Http\Controllers\ServiceController;

Route::group(['middleware' => 'auth'], function () {
    Route::middleware('can:service.view')->group(function () {
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('services/cancelled', [ServiceController::class, 'cancelled'])->name('services.cancelled');
        Route::get('services/cancelled/{id}', [ServiceController::class, 'showCancelled'])->name('services.showCancelled');
        Route::get('services/print/{id}', [ServiceController::class, 'print'])->name('services.print');
    });

    Route::middleware('can:service.create')->group(function () {
        Route::get('services/create', [ServiceController::class, 'createOrEdit'])->name('services.create');
        Route::post('services/update', [ServiceController::class, 'storeOrUpdate'])->name('services.update');
        Route::get('/service-search', [ServiceController::class, 'svSearch'])->name('services.search');
        Route::post('/services/add-customer', [ServiceController::class, 'addCustomer'])->name('services.addCustomer');
        Route::post('/services/add-product', [ServiceController::class, 'addProduct'])->name('services.addProduct');
        Route::post('/services/products/get-new-code', [ServiceController::class, 'newCode'])->name('services.productNewCode');
        Route::post('services/get-voucher', [ServiceController::class, 'getVoucher'])->name('services.getVoucher');
        Route::get('services/products/{product}/batches', [ServiceController::class, 'batches'])->name('services.product-batches');
        Route::get('services/products/{product}/mrp-lots', [ServiceController::class, 'mrpLots'])->name('services.product-mrp-lots');
    });

    Route::get('services/edit/{id}', [ServiceController::class, 'createOrEdit'])
        ->middleware('can:service.update')
        ->name('services.edit');
    Route::delete('services/{id}', [ServiceController::class, 'destroy'])
        ->middleware('can:service.cancel')
        ->name('services.destroy');
    Route::get('services/{id}', [ServiceController::class, 'show'])
        ->middleware('can:service.view')
        ->name('services.show');
});