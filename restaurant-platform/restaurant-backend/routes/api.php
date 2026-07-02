<?php

declare(strict_types=1);

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return ApiResponse::success([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ], 'OK');
});

// Sanctum's scaffolded "who am I" route. Real domain endpoints must return
// an API Resource through ApiResponse::success(), per docs/API_CONVENTIONS.md.
Route::get('/user', function (Request $request) {
    return ApiResponse::success($request->user());
})->middleware('auth:sanctum');
