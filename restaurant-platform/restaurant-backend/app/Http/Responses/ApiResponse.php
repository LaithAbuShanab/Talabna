<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Builds the platform's unified API response envelope.
 *
 * Success: {"success": true, "message": string, "data": mixed}
 * Error:   {"success": false, "message": string, "errors": mixed}
 *
 * See docs/API_CONVENTIONS.md for the full contract.
 */
final class ApiResponse
{
    public static function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data ?? (object) [],
        ], $status);
    }

    public static function error(string $message, mixed $errors = null, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors ?? (object) [],
        ], $status);
    }
}
