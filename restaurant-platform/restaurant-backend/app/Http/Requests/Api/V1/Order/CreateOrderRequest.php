<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Order;

use App\Enums\DeliveryType;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Structural validation only — see App\Http\Requests\Api\V1\Cart\CartPreviewRequest's
 * docblock for why. App\Services\CartPricingService (product/option/zone/
 * coupon/minimum-order) and App\Actions\CreateOrderAction (restaurant open,
 * delivery address ownership) own every business rule.
 */
class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The Idempotency-Key header is merged into the validated input so a
     * missing/malformed key fails validation the same way a missing body
     * field would, rather than needing a separate check in the controller.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.option_value_ids' => ['nullable', 'array'],
            'items.*.option_value_ids.*' => ['integer'],
            'delivery_type' => ['required', new Enum(DeliveryType::class)],
            'payment_method' => ['required', new Enum(PaymentMethod::class)],
            'delivery_zone_id' => ['nullable', 'integer'],
            'customer_address_id' => ['nullable', 'integer'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
