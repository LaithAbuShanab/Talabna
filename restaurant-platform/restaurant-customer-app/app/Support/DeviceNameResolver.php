<?php

declare(strict_types=1);

namespace App\Support;

use Native\Mobile\Facades\Device;

/**
 * "تسجيل device name عند login" — restaurant-backend names each Sanctum
 * token after the device that requested it (`device_name` on
 * `POST /api/v1/auth/{login,register}` — see docs/API_AUTH.md), so a
 * customer can later see/manage which of their devices are signed in.
 *
 * `Native\Mobile\Facades\Device::getInfo()` only documents/guarantees a
 * `platform` key (`"ios"`/`"android"`) in the JSON it returns — see
 * `Native\Mobile\System`'s own use of it. Any other key (model name, etc.)
 * is read defensively, never assumed present, since the package doesn't
 * commit to a stable shape beyond `platform`. Returns `null` (the caller
 * decides the final fallback) when there's no real native bridge at all —
 * e.g. local `php artisan serve` browser development.
 */
final class DeviceNameResolver
{
    public function resolve(): string
    {
        $info = Device::getInfo();

        if ($info === null) {
            return 'Web Browser';
        }

        $decoded = json_decode($info);

        if (! is_object($decoded)) {
            return 'Mobile Device';
        }

        $model = $decoded->model ?? $decoded->name ?? null;
        $platform = $decoded->platform ?? null;

        return match (true) {
            is_string($model) && $model !== '' => $model,
            $platform === 'ios' => 'iOS Device',
            $platform === 'android' => 'Android Device',
            default => 'Mobile Device',
        };
    }
}
