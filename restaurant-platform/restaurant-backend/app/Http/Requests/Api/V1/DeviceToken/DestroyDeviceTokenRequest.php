<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\DeviceToken;

use Illuminate\Foundation\Http\FormRequest;

class DestroyDeviceTokenRequest extends FormRequest
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
        ];
    }
}
