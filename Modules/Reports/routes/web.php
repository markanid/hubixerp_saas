<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\app\Http\Controllers\ReportsController;
use Modules\Reports\app\Http\Controllers\BatchInventoryController;
use Modules\Reports\app\Http\Controllers\MrpInventoryController;

Route::group(['middleware'=>'auth'],function(){

    Route::get('reports', [ReportsController::class, 'center'])->name('reports.center');
    Route::get('reports/{type}', [ReportsController::class, 'report'])
        ->where('type', 'business|sales|purchases|services|returns|stock|outstanding|tax|expenses|estimations|consumption|finance|pos')
        ->name('reports.summary');

    $resources = [
        'dailyreports'       => ReportsController::class,
    ];
    
    foreach ($resources as $resource => $controller) {
        Route::get("$resource", [$controller, 'index'])->name("$resource.index");
        Route::post("$resource/search", [$controller, 'search'])->name("$resource.search");
    }
    Route::get('batch-reports/{type?}', [BatchInventoryController::class, 'index'])
        ->where('type', 'stock|near-expiry|expired|movements|ledger|profitability')
        ->name('batch-reports.index');
    Route::get('mrp-reports/{type?}', [MrpInventoryController::class, 'index'])
        ->where('type', 'stock|movements|profitability')
        ->name('mrp-reports.index');

});