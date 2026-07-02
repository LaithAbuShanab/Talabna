<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\DeliveryZone;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Either `zone_id` (checking a specific zone the client already picked) or
 * `latitude`+`longitude` (checking an address before a zone is known) must
 * be given — never both required at once.
 */
class DeliveryZoneCheckRequest extends FormRequest
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
            'zone_id' => ['nullable', 'integer'],
            'latitude' => ['required_without:zone_id', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_without:zone_id', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
