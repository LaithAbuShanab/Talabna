<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Order;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ownership only — whether cancellation is actually allowed at the
 * order's current status is App\Services\OrderStatusTransitionService's
 * job (via App\Policies\OrderPolicy::cancelAsCustomer()), which raises a
 * specific, translated OrderStatusTransitionException instead of a bare
 * 403 when the order simply isn't in a customer-cancellable state.
 */
class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()?->can('view', $order) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
