<?php

use Illuminate\Support\Facades\Route;
use Modules\Estimation\app\Http\Controllers\{EstimationController, EstimationSettingController};
use Modules\Sale\app\Http\Controllers\SaleController;

Route::group(['middleware'=>'auth'],function(){

    Route::get('estimationsettings', [EstimationSettingController::class, 'index'])->name('estimationsettings.index');
    Route::post('estimationsettings/update', [EstimationSettingController::class, 'storeOrUpdate'])->name('estimationsettings.update');

    $resources = [
        'estimations'     => EstimationController::class,
    ];
    
    foreach ($resources as $resource => $controller) {
        Route::get("$resource", [$controller, 'index'])->name("$resource.index");
        Route::get("$resource/cancelled", [$controller, 'cancelled'])->name("$resource.cancelled");
        Route::get("$resource/create", [$controller, 'createOrEdit'])->name("$resource.create");
        Route::get("$resource/{id}", [$controller, 'show'])->name("$resource.show");
        Route::get("$resource/cancelled/{id}", [$controller, 'showCancelled'])->name("$resource.showCancelled");
        Route::get("$resource/edit/{id}", [$controller, 'createOrEdit'])->name("$resource.edit");
        Route::post("$resource/update", [$controller, 'storeOrUpdate'])->name("$resource.update");
        Route::get("$resource/print/{id}", [$controller, 'print'])->name("$resource.print");
        Route::get("$resource/products/{product}/mrp-lots", [$controller, 'mrpLots'])->name("$resource.product-mrp-lots");
        Route::get("$resource/delete/{id}", [$controller, 'destroy'])->name("$resource.delete");
    }

    Route::get('/estimation-search', [EstimationController::class, 'eSearch'])->name('estimations.search');
    Route::post('/add-customer', [SaleController::class, 'addCustomer'])->name('add.customer');
    Route::post('/estimations/add-product', [SaleController::class, 'addProduct'])->name('estimations.addProduct');
    Route::post('/estimations/products/get-new-code', [SaleController::class, 'newCode'])->name('estimations.productNewCode');
    
});