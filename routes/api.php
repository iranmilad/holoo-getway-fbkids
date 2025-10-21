<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\HolooController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\API\AuthController;

Route::group([
    'middleware' => 'api',
], function ($router) {
    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
    Route::post('/profile', [AuthController::class, 'profile'])->middleware('auth:api');
    Route::post('/config', [ShopController::class, 'config']);
    Route::get('/getProductsWithQuantities', [ShopController::class, 'getProductsWithQuantities']);
    Route::get('/test', [ShopController::class, 'getLanguages']);
    Route::post('/updateAllProductFromHolooToWC', [ShopController::class, 'updateAllProductFromHolooToWC3']);
});

Route::post('/wcInvoicePayed', [HolooController::class, 'wcInvoicePayed']);
Route::post('/webhook', [ShopController::class, 'holooWebHookPrestaShop']);
