<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\app\Http\Controllers\{BankingController, ExpenseController, LoanController};

Route::group(['middleware'=>'auth'],function(){

    $resources = [
        'banks'         => BankingController::class,
        'expenses'       => ExpenseController::class,
    ];
    
    foreach ($resources as $resource => $controller) {
        Route::get("$resource", [$controller, 'index'])->name("$resource.index");
        Route::get("$resource/cancelled", [$controller, 'cancelled'])->name("$resource.cancelled");
        Route::get("$resource/transfer", [$controller, 'transferForm'])->name("$resource.transferform");
        Route::post("$resource/transfer", [$controller, 'transferAmount'])->name("$resource.transfer");
        Route::get("$resource/create", [$controller, 'createOrEdit'])->name("$resource.create");
        Route::get("$resource/{id}", [$controller, 'show'])->name("$resource.show");
        Route::get("$resource/cancelled/{id}", [$controller, 'showCancelled'])->name("$resource.showCancelled");
        Route::get("$resource/edit/{id}", [$controller, 'createOrEdit'])->name("$resource.edit");
        Route::post("$resource/update", [$controller, 'storeOrUpdate'])->name("$resource.update");
        Route::get("$resource/delete/{id}", [$controller, 'destroy'])->name("$resource.delete");
    }

    Route::get('loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('loans/create', [LoanController::class, 'create'])->name('loans.create');
    Route::post('loans/store', [LoanController::class, 'store'])->name('loans.store');
    Route::get('loans/delete/{id}', [LoanController::class, 'destroy'])->name('loans.delete');
    Route::get('loans/{id}', [LoanController::class, 'show'])->name('loans.show');

});
