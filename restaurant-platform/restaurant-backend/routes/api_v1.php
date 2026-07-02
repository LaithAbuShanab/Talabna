<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartPreviewController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerAddressController;
use App\Http\Controllers\Api\V1\DeliveryZoneController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderReviewController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RestaurantController;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return ApiResponse::success([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ], 'OK');
});

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->middleware('throttle:forgot-password');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all-devices', [AuthController::class, 'logoutAllDevices']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    // Sanctum's scaffolded "who am I" route, kept for compatibility.
    Route::get('/user', fn () => ApiResponse::success(request()->user()));

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);

    Route::get('/addresses', [CustomerAddressController::class, 'index']);
    Route::post('/addresses', [CustomerAddressController::class, 'store']);
    Route::put('/addresses/{address}', [CustomerAddressController::class, 'update']);
    Route::delete('/addresses/{address}', [CustomerAddressController::class, 'destroy']);
    Route::post('/addresses/{address}/default', [CustomerAddressController::class, 'setDefault']);

    // See docs/API_ORDERS.md.
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/timeline', [OrderController::class, 'timeline']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{order}/reorder-preview', [OrderController::class, 'reorderPreview']);
    Route::post('/orders/{order}/review', [OrderReviewController::class, 'store']);
});

// Public menu/catalog API — see docs/API_MENU.md. No auth:sanctum: anyone
// can browse the menu, delivery zones, and preview a cart before logging in.
Route::prefix('restaurant')->group(function (): void {
    Route::get('/', [RestaurantController::class, 'info']);
    Route::get('/hours', [RestaurantController::class, 'hours']);
    Route::get('/is-open', [RestaurantController::class, 'isOpen']);
});

Route::get('/categories', [CategoryController::class, 'index']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

Route::get('/delivery-zones', [DeliveryZoneController::class, 'index']);
Route::post('/delivery-zones/check', [DeliveryZoneController::class, 'check']);

Route::post('/cart/preview', [CartPreviewController::class, 'preview']);
