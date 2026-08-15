<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\app\Http\Controllers\ProductController;

Route::group(['middleware'=>'auth'],function(){

    $resources = [
        'products'      => ProductController::class,
    ];
    
    foreach ($resources as $resource => $controller) {
        Route::get("$resource", [$controller, 'index'])->name("$resource.index");
        Route::get("$resource/create", [$controller, 'createOrEdit'])->name("$resource.create");
        Route::get("$resource/{id}", [$controller, 'show'])->name("$resource.show");
        Route::get("$resource/edit/{id}", [$controller, 'createOrEdit'])->name("$resource.edit");
        Route::post("$resource/update", [$controller, 'storeOrUpdate'])->name("$resource.update");
        Route::get("$resource/delete/{id}", [$controller, 'destroy'])->name("$resource.delete");
    }

    Route::get('products/{id}/barcode', [ProductController::class, 'printBarcode'])->name('products.barcode');
    Route::get('products/{id}/qr', [ProductController::class, 'printQrCode'])->name('products.qr');
    Route::post('products/barcodes/print', [ProductController::class, 'printSelectedBarcodes'])->name('products.barcodes.print');
    Route::post('products/qrcodes/print', [ProductController::class, 'printSelectedQrCodes'])->name('products.qrcodes.print');
    Route::get('inventory-labels/{label}/barcode.png', [ProductController::class, 'inventoryBarcode'])->name('inventory-labels.barcode');
    Route::get('inventory-labels/{label}/qr.png', [ProductController::class, 'inventoryQrCode'])->name('inventory-labels.qr');
    Route::get('products/{product}/inventory-labels/print', [ProductController::class, 'printInventoryLabels'])->name('products.inventory-labels.print');
    Route::get('products/{product}/inventory-labels/barcodes/print', [ProductController::class, 'printAllInventoryBarcodes'])->name('products.inventory-labels.barcodes.print');
    Route::get('products/{product}/inventory-labels/qrcodes/print', [ProductController::class, 'printAllInventoryQrCodes'])->name('products.inventory-labels.qrcodes.print');
    Route::get('products/{product}/inventory-labels/{label}/print', [ProductController::class, 'printInventoryLabel'])->name('products.inventory-label.print');
    Route::get('products/{product}/inventory-labels/{label}/barcode/print', [ProductController::class, 'printInventoryBarcode'])->name('products.inventory-label.barcode.print');
    Route::get('products/{product}/inventory-labels/{label}/qr/print', [ProductController::class, 'printInventoryQrCode'])->name('products.inventory-label.qr.print');

    Route::get('/get-subcategories/{category_id}', [ProductController::class, 'getSubcategories']);
});