<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Cart;

use App\Enums\DeliveryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Structural validation only (types, required-ness). Business-rule
 * validation — does the product/option/zone/coupon actually exist, is it
 * available, does the cart meet the minimum order — is entirely
 * App\Services\CartPricingService's job (it throws CartPricingException,
 * rendered as a 422 by bootstrap/app.php) so there is exactly one place
 * that logic lives, matching how App\Actions\CreateOrderAction already
 * uses this same service for checkout.
 */
class CartPreviewRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.option_value_ids' => ['nullable', 'array'],
            'items.*.option_value_ids.*' => ['integer'],
            'delivery_type' => ['required', new Enum(DeliveryType::class)],
            'delivery_zone_id' => ['nullable', 'integer'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
