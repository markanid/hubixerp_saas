<?php

use Illuminate\Support\Facades\Route;
use Modules\Consumption\app\Http\Controllers\ConsumptionController;

Route::group(['middleware'=>'auth'],function(){

    $resources = [
        'consumptions'     => ConsumptionController::class,
    ];
    
    foreach ($resources as $resource => $controller) {
        Route::get("$resource", [$controller, 'index'])->name("$resource.index");
        Route::get("$resource/cancelled", [$controller, 'cancelled'])->name("$resource.cancelled");
        Route::get("$resource/create", [$controller, 'createOrEdit'])->name("$resource.create");
        Route::get("$resource/print/{id}", [$controller, 'print'])->name("$resource.print");
        Route::get("$resource/products/{product}/batches", [$controller, 'batches'])->name("$resource.product-batches");
        Route::get("$resource/products/{product}/mrp-lots", [$controller, 'mrpLots'])->name("$resource.product-mrp-lots");
        Route::get("$resource/{id}", [$controller, 'show'])->name("$resource.show");
        Route::get("$resource/cancelled/{id}", [$controller, 'showCancelled'])->name("$resource.showCancelled");
        Route::get("$resource/edit/{id}", [$controller, 'createOrEdit'])->name("$resource.edit");
        Route::post("$resource/update", [$controller, 'storeOrUpdate'])->name("$resource.update");
        Route::get("$resource/delete/{id}", [$controller, 'destroy'])->name("$resource.delete");
    }
    Route::get('/consumptions-search', [ConsumptionController::class, 'search'])->name('consumptions.search');
    
});