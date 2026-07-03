<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasLabel
{
    case CashOnDelivery = 'cash_on_delivery';
    case CardOnDelivery = 'card_on_delivery';

    public function getLabel(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Cash on delivery',
            self::CardOnDelivery => 'Card on delivery',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'gray',
            self::CardOnDelivery => 'info',
        };
    }
}
