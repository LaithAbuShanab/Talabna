<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Order;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ownership only — whether the order is actually reviewable (delivered,
 * not already reviewed) is App\Http\Controllers\Api\V1\OrderReviewController's
 * job, via App\Exceptions\OrderReviewException.
 */
class SubmitOrderReviewRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
