<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeliveryType: string implements HasColor, HasLabel
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';

    public function getLabel(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery',
            self::Pickup => 'Pickup',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Delivery => 'info',
            self::Pickup => 'gray',
        };
    }
}
