<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\DeviceToken;

use App\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', new Enum(DevicePlatform::class)],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
