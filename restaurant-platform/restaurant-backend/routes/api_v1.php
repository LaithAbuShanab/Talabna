<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerAddressController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ProfileController;
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
});
