<?php

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SubCategoryController;
use App\Http\Controllers\Api\TaxonomyOptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('products', ProductController::class);
    Route::patch('products/{product}/status', [ProductController::class, 'updateStatus']);

    Route::apiResource('categories', CategoryController::class);
    Route::put('categories/{category}/brands', [CategoryController::class, 'syncBrands']);

    Route::apiResource('brands', BrandController::class);
    Route::apiResource('sub-categories', SubCategoryController::class);

    Route::get('options/categories/active', [TaxonomyOptionController::class, 'activeCategories']);
    Route::get('options/categories/{category}/brands/active', [TaxonomyOptionController::class, 'activeBrandsByCategory']);
    Route::get('options/categories/{category}/sub-categories/active', [TaxonomyOptionController::class, 'activeSubCategoriesByCategory']);
});
