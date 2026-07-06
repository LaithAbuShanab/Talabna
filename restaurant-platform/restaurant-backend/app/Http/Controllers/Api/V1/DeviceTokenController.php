<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceToken\DestroyDeviceTokenRequest;
use App\Http\Requests\Api\V1\DeviceToken\StoreDeviceTokenRequest;
use App\Http\Resources\DeviceTokenResource;
use App\Http\Responses\ApiResponse;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;

/**
 * "تخزين device tokens مع platform وdevice name وlast_used_at" — the
 * customer app calls store() once per install/login to register (or
 * re-register) the device it's running on for push notifications, and
 * destroy() on logout so a signed-out device stops receiving pushes for
 * that account. See App\Models\DeviceToken and docs/NOTIFICATIONS.md.
 */
class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // updateOrCreate on the globally-unique `token` column: a token
        // already registered (e.g. a reinstalled app, or the device
        // previously belonged to a different account) is reassigned to
        // whoever is currently authenticated — the provider-issued token is
        // the source of truth for "which device", not which account
        // registered it first.
        $token = DeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $user->id,
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ],
        );

        return ApiResponse::success(new DeviceTokenResource($token), '', 201);
    }

    public function destroy(DestroyDeviceTokenRequest $request): JsonResponse
    {
        $request->user()->deviceTokens()
            ->where('token', $request->validated('token'))
            ->update(['is_active' => false]);

        return ApiResponse::success(null);
    }
}
