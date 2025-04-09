<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\MomoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CartDetailController;

Route::apiResource('users', UserController::class);
Route::apiResource('products', ProductController::class);
Route::apiResource('categories', CategoryController::class);
Route::apiResource('carts', CartController::class);
Route::apiResource('carts_detail', CartDetailController::class);
Route::delete('/carts', [CartController::class, 'destroyByUser'])->middleware('auth:sanctum');
Route::post('/checkout', [App\Http\Controllers\CheckoutController::class, 'store'])->middleware('auth:sanctum'); // Example with Sanctum authentication
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Route xử lý đặt hàng, phân biệt COD và MoMo trong CheckoutController@store
    Route::post('/checkout', [CheckoutController::class, 'store']);

    // Route xử lý callback từ MoMo (GET)
    Route::get('/momo/callback', [MomoController::class, 'handleCallback']);

    // Route xử lý IPN từ MoMo (POST)
    Route::post('/momo/ipn', [MomoController::class, 'handleIpn']);
});
