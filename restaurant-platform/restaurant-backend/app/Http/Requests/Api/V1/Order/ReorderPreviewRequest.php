<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Order;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class ReorderPreviewRequest extends FormRequest
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
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
