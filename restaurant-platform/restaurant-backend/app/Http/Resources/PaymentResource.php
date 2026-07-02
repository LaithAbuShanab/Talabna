<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
final class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'method' => $this->method->value,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
