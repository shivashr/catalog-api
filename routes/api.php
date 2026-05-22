<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('products', ProductController::class);
    Route::post('products/{product}/images', [ProductController::class, 'storeImages']);
    Route::delete('products/{product}/images/{image}', [ProductController::class, 'destroyImage']);
    Route::patch('products/{product}/status', [ProductController::class, 'updateStatus']);
});
