<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    /**
     * Labels/colors for the admin Orders screen (docs/ADMIN_ORDERS.md) only
     * — the public API always renders `status->value`, never these. Pending
     * is deliberately `warning` (amber): it's the "needs action" state, the
     * visual cue for a "new" order on the list.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Preparing => 'Preparing',
            self::Ready => 'Ready',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'info',
            self::Preparing => 'gray',
            self::Ready => 'primary',
            self::OutForDelivery, self::Delivered => 'success',
            self::Cancelled, self::Rejected => 'danger',
        };
    }

    /**
     * The order lifecycle graph: which statuses each status may legally move
     * to, structurally. This is the *shape* of the graph only — extra
     * business rules that depend on the specific order (delivery_type for
     * ready->delivered, and the two "special permission" cancellations) are
     * enforced by App\Services\OrderStatusTransitionService, not here. See
     * docs/ORDER_LIFECYCLE.md for the full picture.
     *
     * @return array<string, list<self>>
     */
    private static function transitionMap(): array
    {
        return [
            self::Pending->value => [self::Accepted, self::Rejected, self::Cancelled],
            self::Accepted->value => [self::Preparing, self::Cancelled],
            self::Preparing->value => [self::Ready, self::Cancelled],
            self::Ready->value => [self::OutForDelivery, self::Delivered, self::Cancelled],
            self::OutForDelivery->value => [self::Delivered, self::Cancelled],
            self::Delivered->value => [],
            self::Cancelled->value => [],
            self::Rejected->value => [],
        ];
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, self::transitionMap()[$this->value], strict: true);
    }

    public function isTerminal(): bool
    {
        return self::transitionMap()[$this->value] === [];
    }

    /**
     * Statuses from which a customer may still cancel their own order —
     * i.e. before the kitchen has started preparing it. Anything past this
     * point (preparing, ready, out_for_delivery) is an admin-only
     * cancellation. See App\Policies\OrderPolicy::cancelAsCustomer().
     */
    public function isCustomerCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Accepted], strict: true);
    }
}
