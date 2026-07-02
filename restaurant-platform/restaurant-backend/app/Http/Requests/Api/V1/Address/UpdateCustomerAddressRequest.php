<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Address;

use App\Models\CustomerAddress;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CustomerAddress $address */
        $address = $this->route('address');

        return $this->user()?->can('update', $address) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['sometimes', 'required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
