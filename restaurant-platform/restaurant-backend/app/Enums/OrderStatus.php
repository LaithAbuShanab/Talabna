<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
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
     * The order lifecycle graph: which statuses each status may legally move to.
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
            self::OutForDelivery->value => [self::Delivered],
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
}
