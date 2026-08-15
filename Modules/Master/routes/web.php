<?php

use Illuminate\Support\Facades\Route;
use Modules\Master\app\Http\Controllers\{BrandController,CategoryController,GroupController,SubcategoryController,ExcategoryController,CounterController};

Route::group(['middleware'=>'auth'],function(){

    $resources = [
        'brands'        => BrandController::class,
        'category'      => CategoryController::class,
        'subcategory'   => SubcategoryController::class,
        'groups'        => GroupController::class,
        'excategory'    => ExcategoryController::class,
        'counters'      => CounterController::class,
    ];
    
    foreach ($resources as $resource => $controller) {
        Route::get("$resource", [$controller, 'index'])->name("$resource.index");
        Route::get("$resource/create", [$controller, 'createOrEdit'])->name("$resource.create");
        Route::get("$resource/{id}", [$controller, 'show'])->name("$resource.show");
        Route::get("$resource/edit/{id}", [$controller, 'createOrEdit'])->name("$resource.edit");
        Route::post("$resource/update", [$controller, 'storeOrUpdate'])->name("$resource.update");
        Route::get("$resource/delete/{id}", [$controller, 'destroy'])->name("$resource.delete");
    }

});
