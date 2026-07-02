<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Order;

use App\Enums\OrderStatus;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Everything App\Services\OrderStatusTransitionService needs to move an
 * order to a new status. $actor is nullable to represent a system/automated
 * transition (no user-level authorization is checked when it's null) — see
 * docs/ORDER_LIFECYCLE.md.
 */
final readonly class TransitionOrderStatusData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public OrderStatus $to,
        public ?User $actor = null,
        public ?string $reason = null,
        public array $metadata = [],
        public ?int $estimatedPreparationMinutes = null,
        public ?CarbonInterface $expectedDeliveryAt = null,
    ) {}
}
