<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchase\app\Http\Controllers\{PurchaseController};
use Modules\Sale\app\Http\Controllers\SaleController;

Route::group(['middleware'=>'auth'],function(){
    Route::middleware('can:purchase.view')->group(function () {
        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('purchases/cancelled', [PurchaseController::class, 'cancelled'])->name('purchases.cancelled');
        Route::get('purchases/cancelled/{id}', [PurchaseController::class, 'showCancelled'])->name('purchases.showCancelled');
        Route::get('purchases/print/{id}', [PurchaseController::class, 'print'])->name('purchases.print');
    });

    Route::middleware('can:purchase.create')->group(function () {
        Route::get('purchases/create', [PurchaseController::class, 'createOrEdit'])->name('purchases.create');
        Route::post('purchases/update', [PurchaseController::class, 'storeOrUpdate'])->name('purchases.update');
        Route::get('/psearch', [PurchaseController::class, 'psearch'])->name('purchase.search');
        Route::post('/add-vendor', [PurchaseController::class, 'addVendor'])->name('add.vendor');
        Route::post('/add-product', [SaleController::class, 'addProduct'])->name('add.product');
        Route::post('products/get-new-code', [SaleController::class, 'newCode'])->name('product.new.code');
        Route::post('/purchases/get-voucher', [PurchaseController::class, 'getVoucher'])->name('purchases.getVoucher');
    });

    Route::get('purchases/edit/{id}', [PurchaseController::class, 'createOrEdit'])
        ->middleware('can:purchase.update')
        ->name('purchases.edit');
    Route::delete('purchases/{id}', [PurchaseController::class, 'destroy'])
        ->middleware('can:purchase.cancel')
        ->name('purchases.destroy');
    Route::get('purchases/{id}', [PurchaseController::class, 'show'])
        ->middleware('can:purchase.view')
        ->name('purchases.show');
    
    // Route::get('/get-vendor', [PurchaseController::class, 'getVendor'])->name('get.vendor');
    // Route::get('/get-product', [PurchaseController::class, 'getProduct'])->name('get.product');
});
