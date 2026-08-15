<?php

use Illuminate\Support\Facades\Route;
use Modules\Sale\app\Http\Controllers\{SaleController,SaleSettingController};

Route::group(['middleware'=>'auth'],function(){
    Route::middleware('can:sale.view')->group(function () {
        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('sales/cancelled', [SaleController::class, 'cancelled'])->name('sales.cancelled');
        Route::get('sales/cancelled/{id}', [SaleController::class, 'showCancelled'])->name('sales.showCancelled');
        Route::get('sales/print/{id}', [SaleController::class, 'print'])->name('sales.print');
    });

    Route::middleware('can:sale.create')->group(function () {
        Route::get('sales/create', [SaleController::class, 'createOrEdit'])->name('sales.create');
        Route::post('sales/update', [SaleController::class, 'storeOrUpdate'])->name('sales.update');
        Route::get('/search', [SaleController::class, 'search'])->name('search');
        Route::post('/add-customer', [SaleController::class, 'addCustomer'])->name('add.customer');
        Route::post('/add-product', [SaleController::class, 'addProduct'])->name('add.product');
        Route::post('products/get-new-code', [SaleController::class, 'newCode'])->name('product.new.code');
        Route::post('sales/get-voucher', [SaleController::class, 'getVoucher'])->name('sales.getVoucher');
        Route::get('sales/products/{product}/batches', [SaleController::class, 'batches'])->name('sales.product-batches');
        Route::get('sales/products/{product}/mrp-lots', [SaleController::class, 'mrpLots'])->name('sales.product-mrp-lots');
    });

    Route::get('sales/edit/{id}', [SaleController::class, 'createOrEdit'])
        ->middleware('can:sale.update')
        ->name('sales.edit');
    Route::delete('sales/{id}', [SaleController::class, 'destroy'])
        ->middleware('can:sale.cancel')
        ->name('sales.destroy');

    Route::get('salesettings', [SaleSettingController::class, 'index'])
        ->middleware('can:sale.settings')
        ->name('salesettings.index');
    Route::post('salesettings/update', [SaleSettingController::class, 'storeOrUpdate'])
        ->middleware('can:sale.settings')
        ->name('salesettings.update');

    Route::get('sales/{id}', [SaleController::class, 'show'])
        ->middleware('can:sale.view')
        ->name('sales.show');
});