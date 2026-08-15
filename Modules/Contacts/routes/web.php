<?php

use Illuminate\Support\Facades\Route;
use Modules\Contacts\app\Http\Controllers\{CustomerController,VendorController};

Route::group(['middleware'=>'auth'],function(){

    Route::post('customers/allocate-advance', [CustomerController::class, 'allocateAdvance'])->name('customers.allocate-advance');
    Route::post('vendors/allocate-advance', [VendorController::class, 'allocateAdvance'])->name('vendors.allocate-advance');

    $resources = [
        'vendors'       => VendorController::class,
        'customers'     => CustomerController::class,
    ];
    
    foreach ($resources as $resource => $controller) {
        Route::get("$resource", [$controller, 'index'])->name("$resource.index");
        Route::get("$resource/create", [$controller, 'createOrEdit'])->name("$resource.create");
        Route::get("$resource/{id}", [$controller, 'show'])->name("$resource.show");
        Route::get("$resource/edit/{id}", [$controller, 'createOrEdit'])->name("$resource.edit");
        Route::post("$resource/update", [$controller, 'storeOrUpdate'])->name("$resource.update");
        Route::get("$resource/delete/{id}", [$controller, 'destroy'])->name("$resource.delete");
        Route::post("$resource/payment", [$controller, 'payment'])->name("$resource.payment");
        Route::post("$resource/fix-ledger", [$controller, 'fixLedger'])->name("$resource.fix-ledger");
    }

});
