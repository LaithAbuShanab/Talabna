<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return ApiResponse::success(new UserResource($user), trans('auth.profile_updated'));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
        ])->save();

        // currentAccessToken() can be a TransientToken (no database id) if the
        // request was authenticated via the "web" session guard rather than a
        // bearer token — Sanctum's guard checks that guard first (see
        // config/sanctum.php). In that case there is no specific device token
        // to preserve, so every real token is revoked.
        $currentToken = $user->currentAccessToken();

        $user->tokens()
            ->when(
                $currentToken instanceof PersonalAccessToken,
                fn ($query) => $query->where('id', '!=', $currentToken->id),
            )
            ->delete();

        return ApiResponse::success(null, trans('auth.password_changed'));
    }
}
