<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        // 'role' is deliberately excluded from User's #[Fillable(...)] list to
        // prevent privilege escalation via mass assignment, so it must be set
        // explicitly here rather than passed into create().
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->forceFill(['role' => UserRole::Customer])->save();

        $token = $user->createToken($data['device_name'])->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
        ], trans('auth.registered'), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var User|null $user */
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        $token = $user->createToken($data['device_name'])->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
        ], trans('auth.login_success'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()->delete();

        return ApiResponse::success(null, trans('auth.logged_out'));
    }

    public function logoutAllDevices(Request $request): JsonResponse
    {
        $request->user()?->tokens()->delete();

        return ApiResponse::success(null, trans('auth.logged_out_all'));
    }
}
