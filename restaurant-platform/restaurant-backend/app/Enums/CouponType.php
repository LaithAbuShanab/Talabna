<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CouponType: string implements HasColor, HasLabel
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';

    public function getLabel(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::FixedAmount => 'Fixed amount',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Percentage => 'info',
            self::FixedAmount => 'gray',
        };
    }
}
